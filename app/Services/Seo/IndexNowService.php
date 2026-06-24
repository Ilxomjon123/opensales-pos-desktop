<?php

declare(strict_types=1);

namespace App\Services\Seo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class IndexNowService
{
    /**
     * @param  array<int, string>  $urls
     */
    public function submit(array $urls): bool
    {
        $key = (string) config('services.indexnow.key', '');
        $host = (string) config('services.indexnow.host', '');
        $endpoint = (string) config('services.indexnow.endpoint', '');

        if ($key === '' || $host === '' || $endpoint === '' || $urls === []) {
            return false;
        }

        $response = Http::asJson()
            ->acceptJson()
            ->timeout(5)
            ->post($endpoint, [
                'host' => $host,
                'key' => $key,
                'keyLocation' => "https://{$host}/{$key}.txt",
                'urlList' => array_values($urls),
            ]);

        if ($response->failed()) {
            Log::warning('IndexNow submit failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'urls' => $urls,
            ]);

            return false;
        }

        return true;
    }
}
