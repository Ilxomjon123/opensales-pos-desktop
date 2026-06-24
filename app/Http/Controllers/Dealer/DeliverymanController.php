<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreDeliverymanRequest;
use App\Http\Requests\Dealer\UpdateDeliverymanRequest;
use App\Http\Resources\DeliverymanResource;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

final class DeliverymanController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isDealer(), 403);

        $dealerId = (int) $request->user()->dealer_id;

        $list = User::query()
            ->deliverymenFor($dealerId)
            ->orderBy('name')
            ->get()
            ->each(function (User $u) use ($dealerId): void {
                $u->shops_count = Shop::query()
                    ->forDeliveryman($u->id)
                    ->forDealer($dealerId)
                    ->count();
            });

        return Inertia::render('Dealer/Deliverymen/Index', [
            'deliverymen' => DeliverymanResource::collection($list),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->isDealer(), 403);

        return Inertia::render('Dealer/Deliverymen/Create');
    }

    public function store(StoreDeliverymanRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::query()->create([
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $request->user()->dealer_id,
        ]);

        return redirect()
            ->route('dealer.deliverymen.index')
            ->with('status', 'Yetkazib beruvchi qo\'shildi');
    }

    public function edit(Request $request, User $deliveryman): Response
    {
        abort_unless($request->user()->isDealer(), 403);
        $this->ensureSameDealer($request, $deliveryman);

        return Inertia::render('Dealer/Deliverymen/Edit', [
            'deliveryman' => DeliverymanResource::make($deliveryman),
        ]);
    }

    public function update(UpdateDeliverymanRequest $request, User $deliveryman): RedirectResponse
    {
        $this->ensureSameDealer($request, $deliveryman);

        $data = $request->validated();
        $update = array_filter([
            'name' => $data['name'] ?? null,
            'username' => $data['username'] ?? null,
            'phone' => $data['phone'] ?? null,
        ], fn ($v) => $v !== null);

        if (! empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        $deliveryman->update($update);

        return redirect()
            ->route('dealer.deliverymen.index')
            ->with('status', 'Yetkazib beruvchi yangilandi');
    }

    public function destroy(Request $request, User $deliveryman): RedirectResponse
    {
        abort_unless($request->user()->isDealer(), 403);
        $this->ensureSameDealer($request, $deliveryman);

        $deliveryman->delete();

        return redirect()
            ->route('dealer.deliverymen.index')
            ->with('status', 'Yetkazib beruvchi o\'chirildi');
    }

    private function ensureSameDealer(Request $request, User $deliveryman): void
    {
        abort_unless(
            $deliveryman->isDeliveryman() && $deliveryman->dealer_id === $request->user()->dealer_id,
            403,
        );
    }
}
