<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\InvalidInviteException;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\ShopInvite;

/**
 * Login sahifasidan QR (diller invite) orqali PAROLSIZ kirish. QR faqat shopni
 * bildiradi — kim ekanini emas, shuning uchun identity shop telefoni bo'yicha
 * aniqlanadi: shop.phone → Customer (topiladi/yaratiladi) → o'sha shopga member.
 *
 * Invite tokeni BIR MARTALIK: ShopInviteService::redeem orqali ishlatiladi —
 * used_at qo'yiladi, shop uchun yangi (ishlatilmagan) invite rotate qilinadi,
 * va already-used / expired / not-found tekshiruvlari shu yerda qo'llanadi.
 */
final class MobileQrLoginService
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly ShopInviteService $inviteService,
    ) {}

    /**
     * @return array{customer: Customer, shop: Shop}
     */
    public function login(string $token): array
    {
        // Customer'ni aniqlash uchun avval shop telefoni kerak (redeem'dan oldin).
        $invite = ShopInvite::query()
            ->where('token', $token)
            ->with('shop.dealer')
            ->first();

        if ($invite === null || $invite->shop === null) {
            throw InvalidInviteException::notFound();
        }

        $shop = $invite->shop;

        // Telefon yo'q shopga parolsiz kirish mumkin emas (identity yo'q).
        if ($shop->phone === null || $shop->phone === '') {
            throw InvalidInviteException::notFound();
        }

        $phone = $this->otp->normalize($shop->phone);

        $customer = Customer::query()->firstOrCreate(
            ['phone' => $phone],
            ['is_active' => true],
        );

        // Bir martalik token: used_at qo'yadi + shop uchun yangi invite rotate qiladi.
        // Allaqachon ishlatilgan / muddati o'tgan bo'lsa InvalidInviteException tashlaydi.
        $this->inviteService->redeem(
            token: $token,
            customerId: $customer->id,
            name: $shop->contact_person ?? $shop->name,
        );

        return ['customer' => $customer, 'shop' => $shop];
    }
}
