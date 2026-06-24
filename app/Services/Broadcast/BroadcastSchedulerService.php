<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Enums\BroadcastScheduleType;
use App\Models\BroadcastCampaign;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Campaign uchun keyingi yuborilish vaqtini hisoblash.
 * Barcha vaqtlar campaign.timezone bo'yicha o'qiladi va UTC ga normallashtirilib qaytariladi.
 */
final class BroadcastSchedulerService
{
    public function nextRunAt(BroadcastCampaign $campaign, ?CarbonInterface $from = null): ?CarbonImmutable
    {
        $tz = $campaign->timezone ?: 'UTC';
        $from = ($from ? CarbonImmutable::instance($from) : CarbonImmutable::now())->setTimezone($tz);

        $candidate = match ($campaign->schedule_type) {
            BroadcastScheduleType::ONCE => $this->forOnce($campaign, $from, $tz),
            BroadcastScheduleType::DAILY => $this->forDaily($campaign, $from, $tz),
            BroadcastScheduleType::WEEKLY => $this->forWeekly($campaign, $from, $tz),
            BroadcastScheduleType::MONTHLY => $this->forMonthly($campaign, $from, $tz),
        };

        if ($candidate === null) {
            return null;
        }

        if ($campaign->starts_at !== null && $candidate->lt($campaign->starts_at)) {
            $candidate = CarbonImmutable::instance($campaign->starts_at)->setTimezone($tz);
        }

        if ($campaign->ends_at !== null && $candidate->gt($campaign->ends_at)) {
            return null;
        }

        return $candidate->setTimezone('UTC');
    }

    private function forOnce(BroadcastCampaign $campaign, CarbonImmutable $from, string $tz): ?CarbonImmutable
    {
        $dt = (string) ($campaign->schedule_config['datetime'] ?? '');

        if ($dt === '') {
            return null;
        }

        $candidate = CarbonImmutable::parse($dt, $tz);

        // ONCE — faqat bir marta. Vaqt o'tib ketgan bo'lsa (yuborilgandan keyin
        // qayta hisoblanganda) null qaytaramiz, aks holda next_run_at o'sha vaqtda
        // qotib qolib, har daqiqada qayta yuboriladi.
        return $candidate->gt($from) ? $candidate : null;
    }

    private function forDaily(BroadcastCampaign $campaign, CarbonImmutable $from, string $tz): ?CarbonImmutable
    {
        return $this->earliestSlot($from, $this->parseTimes($campaign), 2, static fn (): bool => true);
    }

    private function forWeekly(BroadcastCampaign $campaign, CarbonImmutable $from, string $tz): ?CarbonImmutable
    {
        $days = array_map('intval', (array) ($campaign->schedule_config['days'] ?? []));

        if ($days === []) {
            return null;
        }

        return $this->earliestSlot(
            $from,
            $this->parseTimes($campaign),
            14,
            static fn (CarbonImmutable $day): bool => in_array($day->dayOfWeek, $days, true),
        );
    }

    private function forMonthly(BroadcastCampaign $campaign, CarbonImmutable $from, string $tz): ?CarbonImmutable
    {
        $days = array_map('intval', (array) ($campaign->schedule_config['days'] ?? []));

        if ($days === []) {
            return null;
        }

        return $this->earliestSlot(
            $from,
            $this->parseTimes($campaign),
            62,
            static fn (CarbonImmutable $day): bool => in_array($day->day, $days, true),
        );
    }

    /**
     * Belgilangan kun oynasi ichida, kun-shartiga mos kunlarda, har kun uchun
     * sortlangan vaqtlar bo'yicha $from dan keyingi eng yaqin slotni topadi.
     *
     * @param  array<int,array{0:int,1:int}>  $times  sortlangan (h, m) juftliklari
     * @param  callable(CarbonImmutable):bool  $dayMatches
     */
    private function earliestSlot(CarbonImmutable $from, array $times, int $windowDays, callable $dayMatches): ?CarbonImmutable
    {
        for ($i = 0; $i < $windowDays; $i++) {
            $day = $from->copy()->addDays($i);

            if (! $dayMatches($day)) {
                continue;
            }

            foreach ($times as [$h, $m]) {
                $candidate = $day->setTime($h, $m, 0);

                if ($candidate->gt($from)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * schedule_config dan vaqtlarni o'qiydi. Yangi `times[]` formatini, eski
     * yagona `time` formatini ham qo'llab-quvvatlaydi. Natija deduplikatsiya
     * qilinadi va o'sish tartibida sortlanadi.
     *
     * @return array<int,array{0:int,1:int}>
     */
    private function parseTimes(BroadcastCampaign $campaign): array
    {
        $raw = $campaign->schedule_config['times'] ?? null;

        if (! is_array($raw) || $raw === []) {
            $raw = [(string) ($campaign->schedule_config['time'] ?? '09:00')];
        }

        $times = [];

        foreach ($raw as $value) {
            [$h, $m] = $this->parseTime((string) $value);
            $times[sprintf('%02d:%02d', $h, $m)] = [$h, $m];
        }

        ksort($times);

        return array_values($times);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function parseTime(string $time): array
    {
        $parts = explode(':', $time);
        $h = (int) ($parts[0] ?? 9);
        $m = (int) ($parts[1] ?? 0);

        return [max(0, min(23, $h)), max(0, min(59, $m))];
    }
}
