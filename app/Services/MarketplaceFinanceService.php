<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Dealer;
use App\Models\MarketplaceBalance;
use App\Models\MarketplacePayment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Dillerlararo (marketplace) moliya — shop FinanceService bilan bir xil semantika,
 * lekin counterparty diller. Har harakat marketplace_payments'ga yoziladi va
 * ikkala tomonning marketplace_balances qatori sinxron yangilanadi.
 *
 * Saldo `dealer_id` nuqtai nazaridan:
 *   musbat = hamkor menga qarzdor (haqdorlik)
 *   manfiy = men hamkorga qarzdorman (qarzdorlik)
 *
 * Buyer (xaridor) shop bilan bir xil rolda — debit qarz oshiradi, credit kamaytiradi.
 */
final class MarketplaceFinanceService
{
    /**
     * Buyurtma yetkazildi — xaridor (buyer) sotuvchiga (seller) qarzdor bo'ladi.
     */
    public function debit(
        Dealer $seller,
        Dealer $buyer,
        int $amount,
        ?int $orderId = null,
        ?string $note = null,
    ): MarketplacePayment {
        return $this->register($seller, $buyer, $amount, PaymentType::DEBIT, $orderId, $note, PaymentMethod::CASH, null);
    }

    /**
     * Xaridor to'lov qildi — qarz kamayadi.
     */
    public function credit(
        Dealer $seller,
        Dealer $buyer,
        int $amount,
        ?string $note = null,
        PaymentMethod $method = PaymentMethod::CASH,
        ?string $cardholderName = null,
        ?int $orderId = null,
    ): MarketplacePayment {
        return $this->register($seller, $buyer, $amount, PaymentType::CREDIT, $orderId, $note, $method, $cardholderName);
    }

    private function register(
        Dealer $seller,
        Dealer $buyer,
        int $amount,
        PaymentType $type,
        ?int $orderId,
        ?string $note,
        PaymentMethod $method,
        ?string $cardholderName,
    ): MarketplacePayment {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Summa musbat son bo\'lishi kerak');
        }

        if ($seller->id === $buyer->id) {
            throw new InvalidArgumentException('Sotuvchi va xaridor bir xil diller bo\'lishi mumkin emas');
        }

        if ($method === PaymentMethod::CARD && trim((string) $cardholderName) === '') {
            throw new InvalidArgumentException('Karta orqali to\'lovda karta egasi ism-familiyasi majburiy');
        }

        $cardholder = $method === PaymentMethod::CARD ? trim((string) $cardholderName) : null;

        return DB::transaction(function () use ($seller, $buyer, $amount, $type, $orderId, $note, $method, $cardholder): MarketplacePayment {
            $payment = MarketplacePayment::query()->create([
                'seller_dealer_id' => $seller->id,
                'buyer_dealer_id' => $buyer->id,
                'order_id' => $orderId,
                'currency' => $seller->currency ?? Currency::UZS,
                'amount' => $amount,
                'type' => $type,
                'method' => $method,
                'cardholder_name' => $cardholder,
                'note' => $note,
            ]);

            // Buyer shop bilan bir xil: debit (-1) qarzni oshiradi, credit (+1) kamaytiradi.
            $buyerDelta = $type->sign() * $amount;

            $this->adjustBalance($buyer->id, $seller->id, $buyerDelta);
            $this->adjustBalance($seller->id, $buyer->id, -$buyerDelta);

            return $payment;
        });
    }

    /**
     * `dealer_id` ning `partner_dealer_id` ga nisbatan saldosini delta ga o'zgartiradi.
     */
    private function adjustBalance(int $dealerId, int $partnerId, int $delta): void
    {
        $row = MarketplaceBalance::query()->lockForUpdate()->firstOrCreate(
            ['dealer_id' => $dealerId, 'partner_dealer_id' => $partnerId],
            ['balance' => 0],
        );

        $row->update(['balance' => $row->balance + $delta]);
    }

    /**
     * `dealer_id` ning `partner_dealer_id` ga nisbatan joriy saldosi.
     */
    public function balanceBetween(int $dealerId, int $partnerId): int
    {
        return (int) (MarketplaceBalance::query()
            ->where('dealer_id', $dealerId)
            ->where('partner_dealer_id', $partnerId)
            ->value('balance') ?? 0);
    }
}
