<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    /**
     * Diller paneli ichida buyurtmani ko'rish — barcha xodim rollari
     * (owner, skladchi, yetkazib beruvchi) o'z dilleridagi buyurtmalarni
     * ko'rishi mumkin.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->sameDealer($user, $order) && $user->isDealerStaff();
    }

    /**
     * Diller panelidan buyurtma yaratish: owner yoki skladchi.
     */
    public function create(User $user): bool
    {
        return $user->dealer_id !== null && ($user->isOwner() || $user->isWarehouse());
    }

    /**
     * Eski kontrakt — generic update. Faqat owner.
     * Yangi controller'lar specific ability'lardan foydalanadi.
     */
    public function update(User $user, Order $order): bool
    {
        return $this->sameDealer($user, $order) && $user->isOwner();
    }

    /**
     * pending → assembling: owner yoki skladchi.
     * Tovar skladdan shu bosqichda chiqariladi (picked_qty kiritiladi).
     *
     * Backfill: assembling/delivering statusda lekin hech narsa olib chiqilmagan
     * (eski oqim qoldig'i — ASSEMBLING→DELIVERING'da pick'siz o'tib ketgan
     * buyurtmalar) bo'lsa, status'ni o'zgartirmasdan picked_qty kiritishga
     * ruxsat beriladi.
     */
    public function assemble(User $user, Order $order): bool
    {
        if (! $this->sameDealer($user, $order) || ! ($user->isOwner() || $user->isWarehouse())) {
            return false;
        }

        if ($order->status === OrderStatus::PENDING) {
            return true;
        }

        if (! in_array($order->status, [OrderStatus::ASSEMBLING, OrderStatus::DELIVERING], true)) {
            return false;
        }

        return $order->items->every(fn ($item) => (float) ($item->picked_qty ?? 0) <= 0);
    }

    /**
     * assembling → delivering ("yo'lga chiqish"): tovar oldin chiqarilgan,
     * bu yerda faqat status flip. Owner/skladchi yoki biriktirilgan
     * yetkazib beruvchi o'zi bosishi mumkin.
     */
    public function dispatch(User $user, Order $order): bool
    {
        if (! $this->sameDealer($user, $order) || $order->status !== OrderStatus::ASSEMBLING) {
            return false;
        }

        if ($user->isOwner() || $user->isWarehouse()) {
            return true;
        }

        return $user->isDeliveryman() && $order->deliveryman_id === $user->id;
    }

    /**
     * Owner yetkazilgan yoki qabul qilingan buyurtmani tahrirlaydi
     * (tovar/narx/miqdor/to'lov). Saldo va sklad qayta hisoblanadi.
     */
    public function edit(User $user, Order $order): bool
    {
        return $this->sameDealer($user, $order)
            && $user->isOwner()
            && in_array($order->status, [OrderStatus::DELIVERED, OrderStatus::RECEIVED], true);
    }

    /**
     * Owner skladdan olib chiqilgan miqdorni (picked_qty) yetkazishdan oldin
     * tahrirlaydi. Faqat tayyorlanayotgan yoki yo'ldagi buyurtmalarda; sklad
     * qoldig'i farq bo'yicha moslashtiriladi, to'lovga ta'sir qilmaydi.
     */
    public function editPicked(User $user, Order $order): bool
    {
        return $this->sameDealer($user, $order)
            && $user->isOwner()
            && in_array($order->status, [OrderStatus::ASSEMBLING, OrderStatus::DELIVERING], true);
    }

    /**
     * Vozvrat qabul qilish (yetkazib beruvchidagi qoldiqni skladga qaytarish):
     * faqat owner yoki skladchi. Buyurtma yetkazilgan yoki qabul qilingan
     * bo'lishi shart va yetkazib beruvchida hali qoldiq bo'lishi kerak.
     */
    public function acceptReturn(User $user, Order $order): bool
    {
        return $this->sameDealer($user, $order)
            && ($user->isOwner() || $user->isWarehouse())
            && in_array($order->status, [OrderStatus::DELIVERED, OrderStatus::RECEIVED], true);
    }

    /**
     * delivering → delivered: owner yoki o'ziga biriktirilgan yetkazib beruvchi.
     */
    public function deliver(User $user, Order $order): bool
    {
        if (! $this->sameDealer($user, $order) || $order->status !== OrderStatus::DELIVERING) {
            return false;
        }

        if ($user->isOwner()) {
            return true;
        }

        return $user->isDeliveryman() && $order->deliveryman_id === $user->id;
    }

    /**
     * Bekor qilish: owner yoki skladchi, faqat pending/assembling bosqichida.
     */
    public function cancel(User $user, Order $order): bool
    {
        return $this->sameDealer($user, $order)
            && ($user->isOwner() || $user->isWarehouse())
            && $order->status->isCancellable();
    }

    /**
     * Yetkazib beruvchi o'ziga biriktirilgan buyurtmadan voz kechadi
     * (boshqa biriktirilmaydi, status saqlanadi). Faqat delivering'gacha.
     */
    public function releaseSelf(User $user, Order $order): bool
    {
        return $this->sameDealer($user, $order)
            && $user->isDeliveryman()
            && $order->deliveryman_id === $user->id
            && in_array($order->status, [OrderStatus::PENDING, OrderStatus::ASSEMBLING], true);
    }

    /**
     * Yetkazib beruvchini biriktirish/o'zgartirish: faqat owner,
     * pending/assembling bosqichida. Skladchiga ruxsat berilmaydi —
     * sklad rolida bu mas'uliyat yo'q.
     */
    public function assignDeliveryman(User $user, Order $order): bool
    {
        return $this->sameDealer($user, $order)
            && $user->isOwner()
            && in_array($order->status, [OrderStatus::PENDING, OrderStatus::ASSEMBLING], true);
    }

    /**
     * Yetkazib beruvchi bo'sh buyurtmani o'ziga olishi.
     */
    public function selfAssign(User $user, Order $order): bool
    {
        return $this->sameDealer($user, $order)
            && $user->isDeliveryman()
            && $order->deliveryman_id === null
            && in_array($order->status, [OrderStatus::PENDING, OrderStatus::ASSEMBLING], true);
    }

    private function sameDealer(User $user, Order $order): bool
    {
        return $user->dealer_id !== null && $user->dealer_id === $order->dealer_id;
    }
}
