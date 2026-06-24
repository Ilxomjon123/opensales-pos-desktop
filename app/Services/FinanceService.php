<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FinanceService
{
    public function debit(Shop $shop, int $amount, ?string $note = null, PaymentMethod $method = PaymentMethod::CASH, ?string $cardholderName = null, ?Order $order = null, ?int $deliverymanId = null, ?int $shiftId = null): Payment
    {
        return $this->register($shop, $amount, PaymentType::DEBIT, $note, $method, $cardholderName, $order, $deliverymanId, $shiftId);
    }

    public function credit(Shop $shop, int $amount, ?string $note = null, PaymentMethod $method = PaymentMethod::CASH, ?string $cardholderName = null, ?Order $order = null, ?int $deliverymanId = null, ?int $shiftId = null): Payment
    {
        return $this->register($shop, $amount, PaymentType::CREDIT, $note, $method, $cardholderName, $order, $deliverymanId, $shiftId);
    }

    /**
     * Buyurtmaga bog'langan barcha payments qatorlarini o'chiradi va
     * ularning saldoga ta'sirini teskari qiladi. Tahrir uchun ishlatiladi —
     * eski moliyaviy hisob nolga keltiriladi.
     */
    public function revertOrderPayments(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $payments = Payment::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->get();

            if ($payments->isEmpty()) {
                return;
            }

            $delta = 0;
            foreach ($payments as $payment) {
                $delta -= (int) $payment->type->sign() * (int) $payment->amount;
            }

            if ($delta !== 0) {
                Shop::query()
                    ->whereKey($order->shop_id)
                    ->update(['balance' => DB::raw('balance + ('.$delta.')')]);
            }

            Payment::query()->where('order_id', $order->id)->delete();
        });
    }

    private function register(Shop $shop, int $amount, PaymentType $type, ?string $note, PaymentMethod $method, ?string $cardholderName, ?Order $order = null, ?int $deliverymanId = null, ?int $shiftId = null): Payment
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Summa musbat son bo\'lishi kerak');
        }

        if ($method === PaymentMethod::CARD && trim((string) $cardholderName) === '') {
            throw new InvalidArgumentException('Karta orqali to\'lovda karta egasi ism-familiyasi majburiy');
        }

        $cardholder = $method === PaymentMethod::CARD ? trim((string) $cardholderName) : null;

        // Faqat CASH CREDIT'lar yetkazib beruvchi qo'lidagi naqdga tegishli.
        $deliverymanIdForPayment = (
            $method === PaymentMethod::CASH
            && $type === PaymentType::CREDIT
        ) ? $deliverymanId : null;

        return DB::transaction(function () use ($shop, $amount, $type, $note, $method, $cardholder, $order, $deliverymanIdForPayment, $shiftId): Payment {
            $payment = Payment::query()->create([
                'shop_id' => $shop->id,
                'dealer_id' => $shop->dealer_id,
                'order_id' => $order?->id,
                'shift_id' => $shiftId,
                'currency' => $shop->dealer?->currency ?? Currency::UZS,
                'amount' => $amount,
                'type' => $type,
                'method' => $method,
                'cardholder_name' => $cardholder,
                'deliveryman_id' => $deliverymanIdForPayment,
                'note' => $note,
            ]);

            Shop::query()
                ->whereKey($shop->id)
                ->update(['balance' => DB::raw("balance + ({$type->sign()} * {$amount})")]);

            $shop->refresh();

            return $payment;
        });
    }
}
