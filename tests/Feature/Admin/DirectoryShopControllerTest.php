<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Contracts\InnLookupServiceInterface;
use App\Contracts\ReverseGeocoderInterface;
use App\Enums\UserRole;
use App\Models\DirectoryShop;
use App\Models\Shop;
use App\Models\User;
use App\Services\DirectoryShopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DirectoryShopControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'dealer_id' => null]);
    }

    public function test_index_renders_with_entries_and_totals(): void
    {
        DirectoryShop::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.directory.index'))
            ->assertOk();
    }

    public function test_store_creates_a_directory_entry(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.directory.store'), [
                'name' => 'Yangi do\'kon',
                'inn' => '123456789',
                'phone' => '+998 90 123-45-67',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('directory_shops', [
            'inn' => '123456789',
            'source' => 'manual',
            'phone_normalized' => '901234567',
        ]);
    }

    public function test_store_dedups_existing_inn(): void
    {
        DirectoryShop::factory()->create(['inn' => '555555555']);

        $this->actingAs($this->admin)
            ->post(route('admin.directory.store'), [
                'name' => 'Boshqa nom',
                'inn' => '555555555',
            ])
            ->assertSessionHasErrors('inn');
    }

    public function test_store_requires_name(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.directory.store'), ['inn' => '123456789'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, DirectoryShop::query()->count());
    }

    public function test_store_rejects_malformed_inn(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.directory.store'), ['name' => 'X', 'inn' => '123'])
            ->assertSessionHasErrors('inn');
    }

    public function test_update_keeps_own_inn_without_unique_error(): void
    {
        $entry = DirectoryShop::factory()->create(['inn' => '444444444']);

        $this->actingAs($this->admin)
            ->patch(route('admin.directory.update', $entry), ['name' => 'Yangi nom', 'inn' => '444444444'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_update_rejects_inn_colliding_with_other_entry(): void
    {
        DirectoryShop::factory()->create(['inn' => '111222333']);
        $entry = DirectoryShop::factory()->create(['inn' => '999888777']);

        $this->actingAs($this->admin)
            ->patch(route('admin.directory.update', $entry), ['name' => 'X', 'inn' => '111222333'])
            ->assertSessionHasErrors('inn');
    }

    public function test_inn_lookup_rejects_short_inn(): void
    {
        // Marshrut [0-9]+ talab qiladi → harfli STIR 404.
        $this->actingAs($this->admin)
            ->get('/admin/directory/inn-lookup/abc')
            ->assertNotFound();

        // 9 raqamdan kam → 422.
        $this->actingAs($this->admin)
            ->getJson(route('admin.directory.inn-lookup', ['inn' => '123']))
            ->assertStatus(422);
    }

    public function test_phone_lookup_rejects_short_phone(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.directory.phone-lookup', ['phone' => '123']))
            ->assertStatus(422);
    }

    public function test_phone_lookup_returns_404_when_no_match(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.directory.phone-lookup', ['phone' => '998901234567']))
            ->assertStatus(404)
            ->assertJsonPath('shops', []);
    }

    public function test_update_changes_fields_and_recomputes_phone(): void
    {
        $entry = DirectoryShop::factory()->create(['phone' => '+998 90 000-00-00']);

        $this->actingAs($this->admin)
            ->patch(route('admin.directory.update', $entry), [
                'name' => 'Yangilangan',
                'phone' => '+998 91 222-33-44',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('directory_shops', [
            'id' => $entry->id,
            'name' => 'Yangilangan',
            'phone_normalized' => '912223344',
        ]);
    }

    public function test_destroy_removes_entry_and_unlinks_shops(): void
    {
        $shop = Shop::factory()->create(['inn' => '777777777']);
        $entry = $shop->fresh()->directory;

        $this->assertNotNull($entry);

        $this->actingAs($this->admin)
            ->delete(route('admin.directory.destroy', $entry))
            ->assertRedirect();

        $this->assertDatabaseMissing('directory_shops', ['id' => $entry->id]);
        $this->assertNull($shop->fresh()->directory_id);
    }

    public function test_import_csv_creates_and_dedups(): void
    {
        DirectoryShop::factory()->create(['inn' => '111111111', 'name' => 'Mavjud']);

        $csv = "name,inn,phone,region\n"
            ."Mavjud,111111111,,Toshkent\n"
            ."Yangi A,222222222,,Toshkent\n"
            ."Yangi B,333333333,,Samarqand\n";

        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('admin.directory.import'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('directory_shops', ['inn' => '222222222']);
        $this->assertDatabaseHas('directory_shops', ['inn' => '333333333']);
        // Mavjud (111111111) dublikat — bitta qoladi.
        $this->assertSame(1, DirectoryShop::query()->where('inn', '111111111')->count());
        $this->assertSame(3, DirectoryShop::query()->count());
    }

    public function test_import_handles_bom_header_and_invalid_inn(): void
    {
        // Excel BOM + noto'g'ri STIR ("123") bo'lgan qator.
        $csv = "\xEF\xBB\xBFname,inn,phone\n"
            ."BOM mijoz,123,+998901112233\n";

        $file = UploadedFile::fake()->createWithContent('bom.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('admin.directory.import'), ['file' => $file])
            ->assertRedirect();

        // BOM tozalangani uchun "name" ustuni topildi → yozuv yaratildi.
        $entry = DirectoryShop::query()->where('name', 'BOM mijoz')->first();

        $this->assertNotNull($entry);
        // Noto'g'ri STIR null bo'ldi, telefon esa normalizatsiya qilindi.
        $this->assertNull($entry->inn);
        $this->assertSame('901112233', $entry->phone_normalized);
    }

    public function test_store_uploads_photo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.directory.store'), [
                'name' => 'Rasmli mijoz',
                'photo' => UploadedFile::fake()->image('shop.jpg'),
            ])
            ->assertRedirect();

        $entry = DirectoryShop::query()->where('name', 'Rasmli mijoz')->firstOrFail();

        $this->assertNotNull($entry->photo);
        Storage::disk('public')->assertExists($entry->photo);
    }

    public function test_update_can_remove_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('directory/old.jpg', 'x');
        $entry = DirectoryShop::factory()->create(['photo' => 'directory/old.jpg']);

        $this->actingAs($this->admin)
            ->patch(route('admin.directory.update', $entry), [
                'name' => $entry->name,
                'remove_photo' => true,
            ])
            ->assertRedirect();

        $this->assertNull($entry->fresh()->photo);
        Storage::disk('public')->assertMissing('directory/old.jpg');
    }

    public function test_find_or_create_is_idempotent_under_repeated_inn(): void
    {
        $service = app(DirectoryShopService::class);

        $a = $service->findOrCreate(['name' => 'A', 'inn' => '123123123'], source: 'manual');
        $b = $service->findOrCreate(['name' => 'B boshqa nom', 'inn' => '123123123'], source: 'manual');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, DirectoryShop::query()->where('inn', '123123123')->count());
    }

    public function test_reverse_geocode_returns_address(): void
    {
        $this->app->instance(ReverseGeocoderInterface::class, new class implements ReverseGeocoderInterface
        {
            public function reverse(float $lat, float $lng, ?string $lang = null): array
            {
                return [
                    'region' => 'Toshkent shahri',
                    'district' => 'Chilonzor tumani',
                    'address' => 'Bunyodkor 12',
                    'country_code' => 'uz',
                ];
            }
        });

        $this->actingAs($this->admin)
            ->getJson(route('admin.directory.reverse-geocode', ['lat' => 41.3111, 'lng' => 69.2797]))
            ->assertOk()
            ->assertJsonPath('region', 'Toshkent shahri')
            ->assertJsonPath('district', 'Chilonzor tumani')
            ->assertJsonPath('outside_uz', false);
    }

    public function test_reverse_geocode_rejects_invalid_coordinates(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.directory.reverse-geocode', ['lat' => 'x', 'lng' => 'y']))
            ->assertStatus(422);
    }

    public function test_resolve_map_link_rejects_empty(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.directory.resolve-map-link', ['url' => '']))
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_access(): void
    {
        $dealer = User::factory()->create(['role' => UserRole::DEALER]);

        $this->actingAs($dealer)
            ->get(route('admin.directory.index'))
            ->assertRedirect();
    }

    public function test_create_and_edit_pages_render(): void
    {
        $entry = DirectoryShop::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.directory.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.directory.edit', $entry))->assertOk();
    }

    public function test_inn_lookup_returns_existing_entry(): void
    {
        DirectoryShop::factory()->create(['inn' => '123456789', 'name' => 'Bor mijoz']);

        $this->app->instance(InnLookupServiceInterface::class, new class implements InnLookupServiceInterface
        {
            public function lookup(string $inn): ?array
            {
                throw new \RuntimeException('orginfo must not be called when local match exists');
            }
        });

        $this->actingAs($this->admin)
            ->getJson(route('admin.directory.inn-lookup', ['inn' => '123456789']))
            ->assertOk()
            ->assertJsonPath('shops.0.name', 'Bor mijoz');
    }

    public function test_inn_lookup_falls_back_to_orginfo(): void
    {
        $this->app->instance(InnLookupServiceInterface::class, new class implements InnLookupServiceInterface
        {
            public function lookup(string $inn): ?array
            {
                return ['inn' => $inn, 'name' => 'OrgInfo Inc', 'legal_name' => null, 'region' => null, 'district' => null, 'address' => null];
            }
        });

        $this->actingAs($this->admin)
            ->getJson(route('admin.directory.inn-lookup', ['inn' => '987654321']))
            ->assertOk()
            ->assertJsonMissingPath('shops')
            ->assertJsonPath('name', 'OrgInfo Inc');
    }

    public function test_phone_lookup_returns_directory_entries(): void
    {
        DirectoryShop::factory()->create(['phone' => '+998 90 123-45-67', 'phone_normalized' => '901234567', 'name' => 'Tel mijoz']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.directory.phone-lookup', ['phone' => '901234567']))
            ->assertOk()
            ->assertJsonPath('shops.0.name', 'Tel mijoz');
    }

    public function test_template_downloads_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.directory.template'))
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('name,legal_name,inn', $response->streamedContent());
    }
}
