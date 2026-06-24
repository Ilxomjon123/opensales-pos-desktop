<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeliverymanRequestLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * DELIVERYMAN akkountining bir vaqtda bir nechta IP'dan ishlatilishini aniqlaydi.
 * Maqsad: diller bitta akkount bilan ko'p dostavchikni ishlatib, FIXED_PER_DELIVERYMAN
 * komissiyasini chetlab o'tishini cheklash.
 *
 * Progressiv chora:
 *   1-buzilish (24h): faqat audit log + signal
 *   2-buzilish: soft block — barcha sessiya/token o'chadi (qayta login kerak)
 *   3-4 buzilish: temp lock — 1 soatga login bloklanadi
 *   5+ buzilish: hard lock — admin reset qilmaguncha bloklanadi
 */
final class DeliverymanSecurityService
{
    /**
     * 30 soniya ichida boshqa IP topilsa — bir vaqtda ishlatilayotgan deyiladi.
     * Wifi → 4G almashtirish bu oynadan tashqarida bo'ladi (qurilma parallel
     * ikki tarmoqdan request yuborolmaydi).
     */
    private const CONCURRENT_WINDOW_SECONDS = 30;

    private const VIOLATION_TTL_HOURS = 24;

    private const TEMP_LOCK_MINUTES = 60;

    /**
     * Hard lock — admin aralashguncha (juda uzoq muddat).
     */
    private const HARD_LOCK_HOURS = 24 * 30;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Har request da chaqiriladi. Ip log ga yoziladi va concurrent IP tekshiriladi.
     * Buzilish topilsa, progressiv jazo qo'llanadi.
     *
     * @return bool true = request davom ettirilishi mumkin, false = hozir block bo'ldi
     */
    public function track(User $user, string $ip): bool
    {
        $now = Carbon::now();

        $hasOtherIp = DeliverymanRequestLog::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>', $now->copy()->subSeconds(self::CONCURRENT_WINDOW_SECONDS))
            ->where('ip', '!=', $ip)
            ->exists();

        DeliverymanRequestLog::query()->create([
            'user_id' => $user->id,
            'ip' => $ip,
            'created_at' => $now,
        ]);

        if (! $hasOtherIp) {
            return true;
        }

        return $this->handleViolation($user);
    }

    /**
     * 24 soatlik hisoblagichni oshirib, qaroriga ko'ra jazo qo'llaydi.
     */
    private function handleViolation(User $user): bool
    {
        $count = $this->incrementViolationCount($user->id);

        $this->auditLogger->log(
            action: 'deliveryman.concurrent_ip_detected',
            subject: $user,
            changes: ['violation_count_24h' => $count],
        );

        return match (true) {
            $count >= 5 => $this->hardLock($user),
            $count >= 3 => $this->tempLock($user),
            $count >= 2 => $this->softBlock($user),
            default => true,
        };
    }

    /**
     * Sessiya/tokenlarni o'chiradi — klonlangan token darhol o'ladi.
     * Akkount ochiq qoladi, qayta login mumkin.
     */
    private function softBlock(User $user): bool
    {
        $this->killSessions($user);

        return false;
    }

    /**
     * Vaqtinchalik bloklash — `security_locked_until` keyingi 1 soatga belgilanadi.
     */
    private function tempLock(User $user): bool
    {
        $user->forceFill(['security_locked_until' => Carbon::now()->addMinutes(self::TEMP_LOCK_MINUTES)])
            ->save();
        $this->killSessions($user);

        return false;
    }

    /**
     * Qattiq bloklash — admin aralashishi kerak. UI da diller "Qulfni ochish"
     * tugmasi orqali qaytara oladi.
     */
    private function hardLock(User $user): bool
    {
        $user->forceFill(['security_locked_until' => Carbon::now()->addHours(self::HARD_LOCK_HOURS)])
            ->save();
        $this->killSessions($user);

        return false;
    }

    private function killSessions(User $user): void
    {
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function incrementViolationCount(int $userId): int
    {
        $key = $this->violationKey($userId);

        if (! Cache::has($key)) {
            Cache::put($key, 0, Carbon::now()->addHours(self::VIOLATION_TTL_HOURS));
        }

        return (int) Cache::increment($key);
    }

    public function violationCount(int $userId): int
    {
        return (int) Cache::get($this->violationKey($userId), 0);
    }

    /**
     * Diller yoki super admin tomonidan qulfni qo'lda ochish.
     */
    public function unlock(User $user): void
    {
        $user->forceFill(['security_locked_until' => null])->save();
        Cache::forget($this->violationKey($user->id));

        $this->auditLogger->log(
            action: 'deliveryman.security_lock_cleared',
            subject: $user,
        );
    }

    private function violationKey(int $userId): string
    {
        return "deliveryman:violations:{$userId}";
    }
}
