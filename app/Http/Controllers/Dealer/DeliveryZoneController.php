<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\UpdateDeliveryZonesRequest;
use App\Models\Dealer;
use App\Services\DeliveryZoneService;
use App\Services\Geo\GeoCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Diller yetkazib berish hududlarini sozlash (viloyat/tuman bo'yicha).
 * Zona yo'q = hamma joyga yetkazadi.
 */
final class DeliveryZoneController extends Controller
{
    public function __construct(
        private readonly DeliveryZoneService $deliveryZones,
        private readonly GeoCatalog $catalog,
    ) {}

    public function show(Request $request): Response
    {
        $dealer = $this->dealer($request);
        $country = $this->deliveryZones->countryFor($dealer);

        return Inertia::render('Dealer/Settings/DeliveryZones', [
            'regions' => $country !== null ? $this->catalog->regionOptions($country) : [],
            'zones' => $this->deliveryZones->selectionForDealer($dealer),
        ]);
    }

    public function update(UpdateDeliveryZonesRequest $request): RedirectResponse
    {
        $dealer = $this->dealer($request);
        $country = $this->deliveryZones->countryFor($dealer);

        /** @var array<int, array{region: string, whole_region: bool, districts?: array<int, string>}> $zones */
        $zones = $request->validated('zones', []);

        $rows = [];

        foreach ($zones as $zone) {
            if ($zone['whole_region']) {
                $rows[] = ['region' => $zone['region'], 'district' => null];

                continue;
            }

            foreach (array_unique($zone['districts'] ?? []) as $district) {
                $rows[] = ['region' => $zone['region'], 'district' => $district];
            }
        }

        DB::transaction(function () use ($dealer, $country, $rows): void {
            $dealer->deliveryZones()->delete();

            if ($rows !== []) {
                $now = now();
                $dealer->deliveryZones()->insert(array_map(function (array $r) use ($dealer, $country, $now): array {
                    $fk = $country !== null
                        ? $this->catalog->resolveByName($country, $r['region'], $r['district'])
                        : ['region_id' => null, 'district_id' => null];

                    return [
                        'dealer_id' => $dealer->id,
                        'country_id' => $country?->id,
                        'region' => $r['region'],
                        'district' => $r['district'],
                        'region_id' => $fk['region_id'],
                        'district_id' => $fk['district_id'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $rows));
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Yetkazib berish hududlari saqlandi']);

        return back();
    }

    private function dealer(Request $request): Dealer
    {
        $dealer = $request->user()?->dealer;

        abort_if($dealer === null, 403, 'Diller topilmadi');

        return $dealer;
    }
}
