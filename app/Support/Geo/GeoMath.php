<?php

namespace App\Support\Geo;

class GeoMath
{
    public static function distanceKm(float $latitude, float $longitude, float $otherLatitude, float $otherLongitude): float
    {
        $earth = 6371.0088;
        $latitudeDelta = deg2rad($otherLatitude - $latitude);
        $longitudeDelta = deg2rad($otherLongitude - $longitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude)) * cos(deg2rad($otherLatitude)) * sin($longitudeDelta / 2) ** 2;

        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $ring
     */
    public static function contains(float $latitude, float $longitude, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$latitudeI, $longitudeI] = $ring[$i];
            [$latitudeJ, $longitudeJ] = $ring[$j];
            $crosses = ($longitudeI > $longitude) !== ($longitudeJ > $longitude)
                && ($latitude < ($latitudeJ - $latitudeI) * ($longitude - $longitudeI) / (($longitudeJ - $longitudeI) ?: 1e-12) + $latitudeI);

            if ($crosses) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $ring
     */
    public static function withinKilometresOfRing(float $latitude, float $longitude, array $ring, float $kilometres): bool
    {
        foreach ($ring as [$pointLatitude, $pointLongitude]) {
            if (self::distanceKm($latitude, $longitude, $pointLatitude, $pointLongitude) <= $kilometres) {
                return true;
            }
        }

        return false;
    }
}
