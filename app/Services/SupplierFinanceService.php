<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ta'minotchi balansi shop balansi bilan bir xil semantikada:
 *  - balans manfiy = diller supplierga qarzdor
 *  - balans musbat = diller ortiqcha to'lagan
 *
 * DEBIT (sign=-1) = qarz qo'shish (prixod kelgani)
 * CREDIT (sign=+1) = qarzni kamaytirish (supplierga to'lov)
 */
final class SupplierFinanceService
{
    public function debit(
        Supplier $supplier,
        int $amount,
        ?string $note = null,
        PaymentMethod $method = PaymentMethod::CASH,
        ?string $cardholderName = null,
        ?int $transactionId = null,
    ): SupplierPayment {
        return $this->register($supplier, $amount, PaymentType::DEBIT, $note, $method, $cardholderName, $transactionId);
    }

    public function credit(
        Supplier $supplier,
        int $amount,
        ?string $note = null,
        PaymentMethod $method = PaymentMethod::CASH,
        ?string $cardholderName = null,
        ?int $transactionId = null,
    ): SupplierPayment {
        return $this->register($supplier, $amount, PaymentType::CREDIT, $note, $method, $cardholderName, $transactionId);
    }

    private function register(
        Supplier $supplier,
        int $amount,
        PaymentType $type,
        ?string $note,
        PaymentMethod $method,
        ?string $cardholderName,
        ?int $transactionId,
    ): SupplierPayment {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Summa musbat son bo\'lishi kerak');
        }

        if ($method === PaymentMethod::CARD && trim((string) $cardholderName) === '') {
            throw new InvalidArgumentException('Karta orqali to\'lovda karta egasi ism-familiyasi majburiy');
        }

        $cardholder = $method === PaymentMethod::CARD ? trim((string) $cardholderName) : null;

        return DB::transaction(function () use ($supplier, $amount, $type, $note, $method, $cardholder, $transactionId): SupplierPayment {
            $payment = SupplierPayment::query()->create([
                'supplier_id' => $supplier->id,
                'dealer_id' => $supplier->dealer_id,
                'transaction_id' => $transactionId,
                'currency' => $supplier->dealer?->currency ?? Currency::UZS,
                'amount' => $amount,
                'type' => $type,
                'method' => $method,
                'cardholder_name' => $cardholder,
                'note' => $note,
            ]);

            Supplier::query()
                ->whereKey($supplier->id)
                ->update(['balance' => DB::raw("balance + ({$type->sign()} * {$amount})")]);

            $supplier->refresh();

            return $payment;
        });
    }
}
