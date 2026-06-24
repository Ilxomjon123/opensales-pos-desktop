<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommissionType;
use App\Enums\Currency;
use App\Enums\ShopType;
use App\Enums\UserRole;
use App\Events\DealerRegistered;
use App\Models\Country;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class RegisterDealerAction
{
    /**
     * Diller + User yaratadi.
     *
     * Self-registratsiya'da bot_token bo'lmasligi mumkin — diller keyin ichkarida qo'shadi.
     *
     * @param  array{name: string, username: string, password: string, phone?: string|null, country_id?: int|null, currency?: Currency|string|null, bot_token?: string|null, bot_username?: string|null, telegram_chat_id?: int|null, is_active?: bool, min_order_amount?: int|null, commission_type?: CommissionType|string|null, fixed_commission_amount?: int|null, is_self_registered?: bool, trial_ends_at?: CarbonInterface|null}  $data
     */
    public function execute(array $data): Dealer
    {
        return DB::transaction(function () use ($data): Dealer {
            $botToken = $data['bot_token'] ?? null;
            $phone = $data['phone'] ?? null;

            // Davlat berilsa — undan valyuta default'i olinadi. Berilmasa O'zbekiston.
            $country = $this->resolveCountry($data['country_id'] ?? null);

            $attributes = [
                'name' => $data['name'],
                'country_id' => $country?->id,
                'currency' => $data['currency'] ?? $country?->currency ?? Currency::UZS,
                'bot_token' => $botToken,
                // Token bor-u username yo'q bo'lsa (admin oqimi) — vaqtinchalik nom.
                // Tokensiz (self-registratsiya) — null, keyin ichkarida o'rnatiladi.
                'bot_username' => $data['bot_username'] ?? ($botToken !== null ? 'bot_'.uniqid() : null),
                'contact_phone' => $phone,
                'telegram_chat_id' => $data['telegram_chat_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_self_registered' => $data['is_self_registered'] ?? false,
                'trial_ends_at' => $data['trial_ends_at'] ?? null,
                'min_order_amount' => (int) ($data['min_order_amount'] ?? 0),
            ];

            // commission_type berilganda — DB default'ni buzmaslik uchun faqat shartli qo'shamiz.
            if (! empty($data['commission_type'])) {
                $attributes['commission_type'] = $data['commission_type'];
                $attributes['fixed_commission_amount'] = $data['fixed_commission_amount'] ?? null;
            }

            $dealer = Dealer::create($attributes);

            User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'phone' => $phone,
                'password' => Hash::make($data['password']),
                'role' => UserRole::DEALER,
                'dealer_id' => $dealer->id,
            ]);

            // POS terminalda yo'lakay xaridor uchun standart "Walk-in" do'kon
            Shop::query()->create([
                'dealer_id' => $dealer->id,
                'type' => ShopType::WALK_IN,
                'name' => 'Yo\'lakay xaridor',
                'phone' => null,
                'address' => null,
                'inn' => null,
                'balance' => 0,
                'is_active' => true,
            ]);

            event(new DealerRegistered($dealer));

            return $dealer;
        });
    }

    private function resolveCountry(?int $countryId): ?Country
    {
        if ($countryId !== null) {
            return Country::query()->find($countryId);
        }

        return Country::query()->where('code', 'uz')->first();
    }
}
