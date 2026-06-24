<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreEmployeeRequest;
use App\Http\Requests\Dealer\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

final class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isOwner(), 403);

        $dealerId = (int) $request->user()->dealer_id;

        $employees = User::query()
            ->forDealer($dealerId)
            ->whereIn('role', UserRole::dealerStaff())
            ->orderByRaw("CASE role WHEN 'dealer' THEN 1 WHEN 'warehouse' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->get()
            ->each(function (User $u) use ($dealerId): void {
                if ($u->isDeliveryman()) {
                    $u->shops_count = Shop::query()->forDealer($dealerId)
                        ->where('deliveryman_id', $u->id)->count();
                    $u->active_orders_count = Order::query()->forDealer($dealerId)
                        ->where('deliveryman_id', $u->id)
                        ->whereIn('status', [
                            OrderStatus::PENDING,
                            OrderStatus::ASSEMBLING,
                            OrderStatus::DELIVERING,
                        ])->count();
                }
            });

        return Inertia::render('Dealer/Employees/Index', [
            'employees' => EmployeeResource::collection($employees),
            'roles' => collect(UserRole::dealerStaff())->map(fn (UserRole $r): array => [
                'value' => $r->value,
                'label' => $r->label(),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->isOwner(), 403);

        return Inertia::render('Dealer/Employees/Create', [
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::query()->create([
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $request->role(),
            'dealer_id' => $request->user()->dealer_id,
        ]);

        return redirect()
            ->route('dealer.employees.index')
            ->with('status', 'Xodim qo\'shildi');
    }

    public function edit(Request $request, User $employee): Response
    {
        abort_unless($request->user()->isOwner(), 403);
        $this->ensureSameDealer($request, $employee);

        return Inertia::render('Dealer/Employees/Edit', [
            'employee' => EmployeeResource::make($employee),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, User $employee): RedirectResponse
    {
        $this->ensureSameDealer($request, $employee);

        $data = $request->validated();
        $update = array_filter([
            'name' => $data['name'] ?? null,
            'username' => $data['username'] ?? null,
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'] ?? null,
        ], fn ($v) => $v !== null);

        if (! empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        $employee->update($update);

        return redirect()
            ->route('dealer.employees.index')
            ->with('status', 'Xodim yangilandi');
    }

    public function destroy(Request $request, User $employee): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $this->ensureSameDealer($request, $employee);

        if ($employee->id === $request->user()->id) {
            return back()->withErrors(['error' => 'O\'zingizni o\'chira olmaysiz']);
        }

        $employee->delete();

        return redirect()
            ->route('dealer.employees.index')
            ->with('status', 'Xodim o\'chirildi');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return collect(UserRole::dealerStaff())
            ->map(fn (UserRole $r): array => ['value' => $r->value, 'label' => $r->label()])
            ->values()
            ->all();
    }

    private function ensureSameDealer(Request $request, User $employee): void
    {
        abort_unless(
            $employee->isDealerStaff() && $employee->dealer_id === $request->user()->dealer_id,
            403,
        );
    }
}
