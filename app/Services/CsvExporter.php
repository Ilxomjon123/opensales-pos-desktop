<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UTF-8 BOM bilan CSV stream eksport.
 * Excel BOM ni o'qib, kirill/o'zbek harflarini to'g'ri ko'rsatadi.
 * Stream orqali xotiradan tejash — katta hisobotlarda muhim.
 */
final class CsvExporter
{
    /**
     * @param  list<string>  $headers
     * @param  Closure(): iterable  $rowsProvider  iterable of list<string|int|float|null>
     */
    public function stream(string $filename, array $headers, Closure $rowsProvider): StreamedResponse|JsonResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rowsProvider): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            // UTF-8 BOM (Excel kodlashni avtomatik aniqlaydi)
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $headers, ',', '"', '\\');

            foreach ($rowsProvider() as $row) {
                fputcsv($out, array_map(static fn ($v) => $v ?? '', $row), ',', '"', '\\');
            }

            fclose($out);
        });

        $safeName = str_replace(['"', "\n", "\r"], '_', $filename);

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $safeName));

        return $response;
    }
}
