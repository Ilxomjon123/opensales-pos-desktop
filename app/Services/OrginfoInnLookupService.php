<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\InnLookupServiceInterface;
use App\Support\UzRegions;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * orginfo.uz saytidan STIR bo'yicha tashkilot ma'lumotlarini
 * schema.org itemprop atributlari orqali oladi (bepul, auth talab qilmaydi).
 *
 * Oqim:
 *  1. /uz/search/organizations/?q={inn} — natija sahifasida havola topamiz
 *  2. /uz/organization/{slug}/ — detail sahifadan itemproplarni chiqaramiz
 *
 * Natija 24 soat kesh qilinadi.
 */
final class OrginfoInnLookupService implements InnLookupServiceInterface
{
    private const BASE_URL = 'https://orginfo.uz';

    private const USER_AGENT = 'Mozilla/5.0 (compatible; DealerBot/1.0)';

    private const CACHE_TTL_SECONDS = 86_400;

    private const HTTP_TIMEOUT_SECONDS = 12;

    public function __construct(private readonly CacheRepository $cache) {}

    public function lookup(string $inn): ?array
    {
        if (! preg_match('/^\d{9}$/', $inn)) {
            return null;
        }

        return $this->cache->remember(
            "inn-lookup:{$inn}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->fetch($inn),
        );
    }

    private function fetch(string $inn): ?array
    {
        try {
            $searchHtml = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get(self::BASE_URL.'/uz/search/organizations/', ['q' => $inn])
                ->throw()
                ->body();
        } catch (Throwable $e) {
            Log::warning('INN search failed', ['inn' => $inn, 'error' => $e->getMessage()]);

            return null;
        }

        $slug = $this->extractSlug($searchHtml);
        if ($slug === null) {
            return ['inn' => $inn, 'name' => null, 'legal_name' => null, 'region' => null, 'district' => null, 'address' => null];
        }

        try {
            $detailHtml = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get(self::BASE_URL."/uz/organization/{$slug}/")
                ->throw()
                ->body();
        } catch (Throwable $e) {
            Log::warning('INN detail fetch failed', ['inn' => $inn, 'slug' => $slug, 'error' => $e->getMessage()]);

            return null;
        }

        return $this->parseDetail($inn, $detailHtml);
    }

    private function extractSlug(string $html): ?string
    {
        if (preg_match('#/uz/organization/([a-z0-9]+)/#i', $html, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /** @return array{inn:string,name:?string,legal_name:?string,region:?string,district:?string,address:?string} */
    private function parseDetail(string $inn, string $html): array
    {
        $name = $this->extractItemprop($html, 'name');
        $legalName = $this->extractItemprop($html, 'legalName') ?? $name;
        $locality = $this->extractItemprop($html, 'addressLocality');
        $street = $this->extractItemprop($html, 'streetAddress');

        [$rawRegion, $rawDistrict] = $this->splitLocality($locality);
        $matched = UzRegions::match($rawRegion, $rawDistrict);

        return [
            'inn' => $inn,
            'name' => $name,
            'legal_name' => $legalName,
            'region' => $matched['region'] ?? $rawRegion,
            'district' => $matched['district'] ?? $rawDistrict,
            'address' => $street,
        ];
    }

    private function extractItemprop(string $html, string $prop): ?string
    {
        $quoted = preg_quote($prop, '#');

        // content="value" atributida bo'lishi mumkin
        if (preg_match('#itemprop="'.$quoted.'"[^>]*content="([^"]+)"#i', $html, $m) === 1) {
            return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        // tag ichida matn sifatida
        if (preg_match('#itemprop="'.$quoted.'"[^>]*>([^<]+)<#i', $html, $m) === 1) {
            return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return null;
    }

    /**
     * "Toshkent shahri, Bektemir tumani" → [viloyat, tuman]
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function splitLocality(?string $locality): array
    {
        if ($locality === null || $locality === '') {
            return [null, null];
        }

        $parts = array_map('trim', explode(',', $locality, 2));

        return [$parts[0] ?? null, $parts[1] ?? null];
    }
}
