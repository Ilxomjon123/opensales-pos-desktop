<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\OrderStatus;
use Tests\TestCase;

final class OrderStatusTest extends TestCase
{
    public function test_pending_can_transition_to_assembling(): void
    {
        $this->assertTrue(OrderStatus::PENDING->canTransitionTo(OrderStatus::ASSEMBLING));
    }

    public function test_pending_can_transition_to_cancelled(): void
    {
        $this->assertTrue(OrderStatus::PENDING->canTransitionTo(OrderStatus::CANCELLED));
    }

    public function test_pending_cannot_jump_statuses(): void
    {
        $this->assertFalse(OrderStatus::PENDING->canTransitionTo(OrderStatus::DELIVERING));
        $this->assertFalse(OrderStatus::PENDING->canTransitionTo(OrderStatus::DELIVERED));
        $this->assertFalse(OrderStatus::PENDING->canTransitionTo(OrderStatus::RECEIVED));
    }

    public function test_assembling_can_transition_to_delivering_and_cancelled(): void
    {
        $this->assertTrue(OrderStatus::ASSEMBLING->canTransitionTo(OrderStatus::DELIVERING));
        $this->assertTrue(OrderStatus::ASSEMBLING->canTransitionTo(OrderStatus::CANCELLED));
        $this->assertFalse(OrderStatus::ASSEMBLING->canTransitionTo(OrderStatus::DELIVERED));
    }

    public function test_delivering_can_only_transition_to_delivered(): void
    {
        $this->assertTrue(OrderStatus::DELIVERING->canTransitionTo(OrderStatus::DELIVERED));
        $this->assertFalse(OrderStatus::DELIVERING->canTransitionTo(OrderStatus::CANCELLED));
        $this->assertFalse(OrderStatus::DELIVERING->canTransitionTo(OrderStatus::RECEIVED));
    }

    public function test_delivered_can_only_transition_to_received(): void
    {
        $this->assertTrue(OrderStatus::DELIVERED->canTransitionTo(OrderStatus::RECEIVED));
        $this->assertFalse(OrderStatus::DELIVERED->canTransitionTo(OrderStatus::CANCELLED));
    }

    public function test_received_is_final(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertFalse(OrderStatus::RECEIVED->canTransitionTo($status));
        }

        $this->assertTrue(OrderStatus::RECEIVED->isFinal());
        $this->assertFalse(OrderStatus::RECEIVED->isOpen());
    }

    public function test_cancelled_is_final(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertFalse(OrderStatus::CANCELLED->canTransitionTo($status));
        }

        $this->assertTrue(OrderStatus::CANCELLED->isFinal());
    }

    public function test_is_cancellable(): void
    {
        $this->assertTrue(OrderStatus::PENDING->isCancellable());
        $this->assertTrue(OrderStatus::ASSEMBLING->isCancellable());
        $this->assertFalse(OrderStatus::DELIVERING->isCancellable());
        $this->assertFalse(OrderStatus::DELIVERED->isCancellable());
        $this->assertFalse(OrderStatus::RECEIVED->isCancellable());
        $this->assertFalse(OrderStatus::CANCELLED->isCancellable());
    }

    public function test_label_returns_uzbek_text(): void
    {
        $this->assertSame('Kutilmoqda', OrderStatus::PENDING->label());
        $this->assertSame('Tayyorlandi', OrderStatus::ASSEMBLING->label());
        $this->assertSame('Yetkazilmoqda', OrderStatus::DELIVERING->label());
        $this->assertSame('Yetkazildi', OrderStatus::DELIVERED->label());
        $this->assertSame('Qabul qilindi', OrderStatus::RECEIVED->label());
        $this->assertSame('Bekor qilindi', OrderStatus::CANCELLED->label());
    }
}
