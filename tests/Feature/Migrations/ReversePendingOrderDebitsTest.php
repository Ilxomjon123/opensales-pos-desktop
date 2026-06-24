<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Saldoga ta'sir qiluvchi rejim o'zgarishi uchun ma'lumot migratsiyasi.
 * Eski model bo'yicha ochiq buyurtmalar yaratilganda ularning summasi
 * darhol qarz sifatida yozilardi. Yangi modelda qarz faqat yetkazib
 * berilganda yoziladi. Migratsiya ochiq buyurtmalarning eski debitlarini
 * qaytaradi va saldoni to'g'rilaydi.
 */
final class ReversePendingOrderDebitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_order_debit_is_reversed_and_balance_restored(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => -100_000]);

        $order = Order::factory()->for($dealer)->for($shop)->create([
            'status' => OrderStatus::PENDING,
            'total' => 100_000,
        ]);

        $payment = Payment::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'amount' => 100_000,
            'type' => PaymentType::DEBIT,
            'method' => PaymentMethod::CASH,
            'note' => "Buyurtma #{$order->id}",
        ]);

        $this->runMigration();

        $this->assertSame(0, (int) $shop->fresh()->balance);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }

    public function test_delivered_orders_are_left_untouched(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => -100_000]);

        $order = Order::factory()->for($dealer)->for($shop)->create([
            'status' => OrderStatus::DELIVERED,
            'total' => 100_000,
        ]);

        $payment = Payment::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'amount' => 100_000,
            'type' => PaymentType::DEBIT,
            'method' => PaymentMethod::CASH,
            'note' => "Buyurtma #{$order->id}",
        ]);

        $this->runMigration();

        $this->assertSame(-100_000, (int) $shop->fresh()->balance);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_cancelled_orders_are_left_untouched(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 0]);

        $order = Order::factory()->for($dealer)->for($shop)->create([
            'status' => OrderStatus::CANCELLED,
            'total' => 50_000,
        ]);

        // Bekor qilingan buyurtma uchun debit + uni qaytaradigan credit allaqachon yozilgan,
        // saldo 0 — daxlsiz qoldirish kerak.
        Payment::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'amount' => 50_000,
            'type' => PaymentType::DEBIT,
            'method' => PaymentMethod::CASH,
            'note' => "Buyurtma #{$order->id}",
        ]);
        Payment::query()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'amount' => 50_000,
            'type' => PaymentType::CREDIT,
            'method' => PaymentMethod::CASH,
            'note' => "Bekor qilindi: Buyurtma #{$order->id}",
        ]);

        $this->runMigration();

        $this->assertSame(0, (int) $shop->fresh()->balance);
        $this->assertSame(2, Payment::query()->where('shop_id', $shop->id)->count());
    }

    public function test_runs_safely_when_no_matching_rows_exist(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create(['balance' => 5_000]);

        $this->runMigration();

        $this->assertSame(5_000, (int) $shop->fresh()->balance);
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_04_30_133745_reverse_pending_order_debits.php');

        // RefreshDatabase migratsiyani allaqachon ishga tushirgan bo'ladi (bo'sh DB bo'yicha).
        // Test ma'lumotlari bilan qayta ishlatamiz — operatsiya idempotentligi ham tekshiriladi.
        DB::transaction(fn () => $migration->up());
    }
}
