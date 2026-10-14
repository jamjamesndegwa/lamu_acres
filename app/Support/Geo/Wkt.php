<?php

namespace App\Support\Geo;

class Wkt
{
    /**
     * @param  array<int, array{lat: float|int|string, lng: float|int|string}>  $points
     */
    public static function polygon(array $points): string
    {
        $ring = array_map(
            fn (array $point): string => self::pair((float) $point['lng'], (float) $point['lat']),
            $points,
        );

        if ($ring !== [] && $ring[0] !== $ring[array_key_last($ring)]) {
            $ring[] = $ring[0];
        }

        return 'POLYGON(('.implode(', ', $ring).'))';
    }

    public static function point(float $latitude, float $longitude): string
    {
        return 'POINT('.self::pair($longitude, $latitude).')';
    }

    public static function isPoint(string $wkt): bool
    {
        return preg_match('/^POINT\(-?\d+(\.\d+)? -?\d+(\.\d+)?\)$/', $wkt) === 1;
    }

    public static function isPolygon(string $wkt): bool
    {
        return preg_match('/^POLYGON\(\((-?\d+(\.\d+)? -?\d+(\.\d+)?)(, -?\d+(\.\d+)? -?\d+(\.\d+)?){3,}\)\)$/', $wkt) === 1;
    }

    /**
     * Stored polygon vertices for display. An empty result means nothing verified can be drawn.
     *
     * @return array<int, array{lat: float, lng: float}>
     */
    public static function latLngRing(string $wkt): array
    {
        $wkt = trim($wkt);

        if (preg_match('/^(POLYGON|MULTIPOLYGON)\s*\(/i', $wkt) !== 1) {
            return [];
        }

        preg_match_all('/(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)/', $wkt, $pairs, PREG_SET_ORDER);

        if (count($pairs) < 4) {
            return [];
        }

        return array_map(fn (array $pair): array => [
            'lng' => (float) $pair[1],
            'lat' => (float) $pair[2],
        ], $pairs);
    }

    public static function geomFromText(string $wkt): string
    {
        if (! self::isPoint($wkt) && ! self::isPolygon($wkt)) {
            throw new \InvalidArgumentException('Geographic criteria must be valid WKT.');
        }

        return "ST_GeomFromText('".$wkt."')";
    }

    private static function pair(float $longitude, float $latitude): string
    {
        return self::number($longitude).' '.self::number($latitude);
    }

    private static function number(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 7, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }
}
