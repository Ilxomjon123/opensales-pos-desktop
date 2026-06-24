<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\InnLookupServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportDirectoryShopsRequest;
use App\Http\Requests\Admin\StoreDirectoryShopRequest;
use App\Http\Requests\Admin\UpdateDirectoryShopRequest;
use App\Models\DirectoryShop;
use App\Services\DirectoryShopService;
use App\Services\GeoResolver;
use App\Support\UzRegions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DirectoryShopController extends Controller
{
    private const PHOTO_DIR = 'directory';

    public function __construct(private readonly DirectoryShopService $directory) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $source = trim((string) $request->string('source'));

        $entries = DirectoryShop::query()
            ->withCount(['shops as linked_count', 'activatedShops as activated_count'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($q2) use ($search): void {
                    $q2->where('name', 'ilike', '%'.$search.'%')
                        ->orWhere('legal_name', 'ilike', '%'.$search.'%')
                        ->orWhere('phone', 'ilike', '%'.$search.'%')
                        ->orWhere('inn', 'ilike', '%'.$search.'%')
                        ->orWhere('region', 'ilike', '%'.$search.'%')
                        ->orWhere('district', 'ilike', '%'.$search.'%');
                });
            })
            ->when($source !== '', fn ($q) => $q->where('source', $source))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Directory/Index', [
            'entries' => $this->transform($entries),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'source' => $source !== '' ? $source : null,
            ],
            'totals' => $this->totals(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Directory/Create', [
            'regions' => UzRegions::all(),
        ]);
    }

    public function edit(DirectoryShop $directoryShop): Response
    {
        return Inertia::render('Admin/Directory/Edit', [
            'entry' => [
                'id' => $directoryShop->id,
                'name' => $directoryShop->name,
                'legal_name' => $directoryShop->legal_name,
                'inn' => $directoryShop->inn,
                'phone' => $directoryShop->phone,
                'contact_person' => $directoryShop->contact_person,
                'address' => $directoryShop->address,
                'landmark' => $directoryShop->landmark,
                'region' => $directoryShop->region,
                'district' => $directoryShop->district,
                'latitude' => $directoryShop->latitude,
                'longitude' => $directoryShop->longitude,
                'photo' => $directoryShop->photo,
                'photo_url' => $directoryShop->photo ? Storage::disk('public')->url($directoryShop->photo) : null,
            ],
            'regions' => UzRegions::all(),
        ]);
    }

    public function store(StoreDirectoryShopRequest $request): RedirectResponse
    {
        $attrs = $request->safe()->except(['photo', 'photo_source_path', 'map_provider']);
        $attrs['photo'] = $this->resolvePhoto($request);

        $this->directory->findOrCreate($attrs, source: 'manual');

        return redirect()
            ->route('admin.directory.index')
            ->with('flash', ['type' => 'success', 'message' => "Spravochnikka qo'shildi"]);
    }

    public function update(UpdateDirectoryShopRequest $request, DirectoryShop $directoryShop): RedirectResponse
    {
        $data = $request->safe()->except(['photo', 'photo_source_path', 'remove_photo', 'map_provider']);
        $data['phone_normalized'] = DirectoryShopService::normalizePhone($data['phone'] ?? null);

        $photo = $this->resolvePhoto($request);

        if ($photo !== null) {
            if ($directoryShop->photo) {
                Storage::disk('public')->delete($directoryShop->photo);
            }
            $data['photo'] = $photo;
        } elseif ($request->boolean('remove_photo') && $directoryShop->photo) {
            Storage::disk('public')->delete($directoryShop->photo);
            $data['photo'] = null;
        }

        $directoryShop->update($data);

        return redirect()
            ->route('admin.directory.index')
            ->with('flash', ['type' => 'success', 'message' => 'Yangilandi']);
    }

    public function destroy(DirectoryShop $directoryShop): RedirectResponse
    {
        // Bog'langan shoplarda directory_id null bo'ladi (FK nullOnDelete).
        $directoryShop->delete();

        return back()->with('flash', ['type' => 'success', 'message' => "O'chirildi"]);
    }

    public function lookupInn(string $inn, InnLookupServiceInterface $lookup): JsonResponse
    {
        if (! preg_match('/^\d{9}$/', $inn)) {
            return response()->json(['message' => 'STIR 9 xonali raqam bo\'lishi kerak'], 422);
        }

        $entries = DirectoryShop::query()->forInn($inn)->latest('id')->limit(20)->get();

        if ($entries->isNotEmpty()) {
            return response()->json(['shops' => $this->mapForLookup($entries)]);
        }

        $result = $lookup->lookup($inn);

        if ($result === null) {
            return response()->json(['message' => 'Xizmat vaqtincha ishlamayapti'], 503);
        }

        return response()->json($result);
    }

    public function lookupPhone(Request $request): JsonResponse
    {
        $phone = (string) $request->query('phone', '');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 7) {
            return response()->json(['message' => 'Telefon raqam to\'liq kiritilishi kerak'], 422);
        }

        $entries = DirectoryShop::query()
            ->forPhoneTail(substr($digits, -9))
            ->latest('id')
            ->limit(20)
            ->get();

        if ($entries->isEmpty()) {
            return response()->json(['shops' => []], 404);
        }

        return response()->json(['shops' => $this->mapForLookup($entries)]);
    }

    public function reverseGeocode(Request $request, GeoResolver $geo): JsonResponse
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return response()->json(['message' => 'Koordinatalar noto\'g\'ri'], 422);
        }

        $latF = (float) $lat;
        $lngF = (float) $lng;

        if ($latF < -90 || $latF > 90 || $lngF < -180 || $lngF > 180) {
            return response()->json(['message' => 'Koordinatalar diapazondan tashqari'], 422);
        }

        return response()->json($geo->reverse($latF, $lngF));
    }

    public function resolveMapLink(Request $request, GeoResolver $geo): JsonResponse
    {
        $result = $geo->resolveMapLink((string) $request->query('url', ''));

        return response()->json($result['body'], $result['status']);
    }

    public function template(): StreamedResponse
    {
        $columns = [
            'name', 'legal_name', 'inn', 'phone', 'contact_person',
            'address', 'landmark', 'region', 'district', 'latitude', 'longitude',
        ];

        $example = [
            'Zeytun market', 'MChJ Zeytun Savdo', '123456789', '+998901234567', 'Ahmad Karimov',
            'Chilonzor 12-mavze', 'Metro yonida', 'Toshkent shahri', 'Chilonzor tumani', '41.2856', '69.2034',
        ];

        return response()->streamDownload(function () use ($columns, $example): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            fputcsv($handle, $example);
            fclose($handle);
        }, 'directory-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(ImportDirectoryShopsRequest $request): RedirectResponse
    {
        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return back()->with('flash', ['type' => 'error', 'message' => "Faylni o'qib bo'lmadi"]);
        }

        $header = fgetcsv($handle);

        if ($header === false || $header === null) {
            fclose($handle);

            return back()->with('flash', ['type' => 'error', 'message' => "Fayl bo'sh"]);
        }

        // Excel CSV ko'pincha UTF-8 BOM qo'shadi — birinchi ustun nomidan tozalaymiz.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $columns = array_map(fn ($h): string => mb_strtolower(trim((string) $h)), $header);
        $created = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $attrs = $this->mapCsvRow($columns, $row);

            if (($attrs['name'] ?? '') === '') {
                $skipped++;

                continue;
            }

            $entry = $this->directory->findOrCreate($attrs, source: 'manual');
            $entry->wasRecentlyCreated ? $created++ : $skipped++;
        }

        fclose($handle);

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Import yakunlandi: {$created} ta yangi, {$skipped} ta o'tkazib yuborildi (dublikat)",
        ]);
    }

    /**
     * Yuklangan rasm fayli yoki lookup'dan kelgan mavjud rasm yo'lini qaytaradi.
     */
    private function resolvePhoto(Request $request): ?string
    {
        if ($request->hasFile('photo')) {
            return $request->file('photo')->store(self::PHOTO_DIR, 'public');
        }

        $sourcePath = trim((string) $request->string('photo_source_path'));

        if ($sourcePath === '') {
            return null;
        }

        // Faqat ma'lum kataloglardagi mavjud rasmlardan nusxa olishga ruxsat —
        // public disk ichidagi ixtiyoriy faylni nusxalashning oldini olamiz.
        if (! Str::startsWith($sourcePath, ['dealers/', 'directory/'])) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($sourcePath)) {
            return null;
        }

        $newPath = self::PHOTO_DIR.'/'.Str::uuid()->toString().'.'.pathinfo($sourcePath, PATHINFO_EXTENSION);
        $disk->copy($sourcePath, $newPath);

        return $newPath;
    }

    /**
     * @param  Collection<int, DirectoryShop>  $entries
     * @return list<array<string, mixed>>
     */
    private function mapForLookup($entries): array
    {
        return $entries->map(fn (DirectoryShop $entry): array => [
            'id' => $entry->id,
            'name' => $entry->name,
            'legal_name' => $entry->legal_name,
            'phone' => $entry->phone,
            'contact_person' => $entry->contact_person,
            'address' => $entry->address,
            'landmark' => $entry->landmark,
            'region' => $entry->region,
            'district' => $entry->district,
            'inn' => $entry->inn,
            'latitude' => $entry->latitude,
            'longitude' => $entry->longitude,
            'photo' => $entry->photo,
            'photo_url' => $entry->photo ? Storage::disk('public')->url($entry->photo) : null,
            'is_own' => false,
        ])->values()->all();
    }

    /**
     * CSV qatorini ustun nomlariga moslab atributlarga aylantiradi.
     *
     * @param  list<string>  $columns
     * @param  list<string|null>  $row
     * @return array<string, mixed>
     */
    private function mapCsvRow(array $columns, array $row): array
    {
        $allowed = [
            'name', 'legal_name', 'inn', 'phone', 'contact_person',
            'address', 'landmark', 'region', 'district', 'latitude', 'longitude',
        ];

        $attrs = [];

        foreach ($columns as $index => $column) {
            if (! in_array($column, $allowed, true)) {
                continue;
            }

            $value = trim((string) ($row[$index] ?? ''));
            $attrs[$column] = $value === '' ? null : $value;
        }

        // Noto'g'ri STIR (9 raqam emas) — null qilamiz, dedup telefon/nom bo'yicha ketadi.
        if (isset($attrs['inn']) && ! preg_match('/^\d{9}$/', (string) $attrs['inn'])) {
            $attrs['inn'] = null;
        }

        return $attrs;
    }

    /**
     * @return array{total: int, by_source: array<string, int>, linked: int, activated: int}
     */
    private function totals(): array
    {
        return [
            'total' => DirectoryShop::query()->count(),
            'by_source' => DirectoryShop::query()
                ->selectRaw('source, count(*) as aggregate')
                ->groupBy('source')
                ->pluck('aggregate', 'source')
                ->map(fn ($v): int => (int) $v)
                ->all(),
            'linked' => DirectoryShop::query()->has('shops')->count(),
            'activated' => DirectoryShop::query()->has('activatedShops')->count(),
        ];
    }

    /**
     * @return array{
     *     data: list<array<string, mixed>>,
     *     meta: array{total: int, last_page: int, current_page: int, per_page: int},
     *     links: array{prev: string|null, next: string|null}
     * }
     */
    private function transform(LengthAwarePaginator $page): array
    {
        return [
            'data' => collect($page->items())
                ->map(fn (DirectoryShop $d): array => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'legal_name' => $d->legal_name,
                    'inn' => $d->inn,
                    'phone' => $d->phone,
                    'contact_person' => $d->contact_person,
                    'address' => $d->address,
                    'landmark' => $d->landmark,
                    'region' => $d->region,
                    'district' => $d->district,
                    'latitude' => $d->latitude,
                    'longitude' => $d->longitude,
                    'source' => $d->source,
                    'linked_count' => (int) $d->getAttribute('linked_count'),
                    'activated_count' => (int) $d->getAttribute('activated_count'),
                ])
                ->all(),
            'meta' => [
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
            ],
            'links' => [
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
        ];
    }
}
