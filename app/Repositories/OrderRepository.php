<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class OrderRepository
{
    public function paginateForDealer(int $dealerId, int $perPage = 20): LengthAwarePaginator
    {
        return Order::query()
            ->forDealer($dealerId)
            ->shopChannel()
            ->with(['shop', 'items.product'])
            ->latest()
            ->paginate($perPage);
    }

    /** @return Collection<int, Order> */
    public function pendingForDealer(int $dealerId): Collection
    {
        return Order::query()
            ->forDealer($dealerId)
            ->shopChannel()
            ->pending()
            ->with('shop')
            ->get();
    }
}
