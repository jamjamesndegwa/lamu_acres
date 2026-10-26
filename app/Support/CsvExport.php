<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport
{
    /**
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, string|null>>  $rows
     */
    public static function download(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    fn (?string $value): string => self::escapeCell($value),
                    $row,
                ));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private static function escapeCell(?string $value): string
    {
        $value ??= '';

        if ($value !== '' && preg_match('/^[=+\-@]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
