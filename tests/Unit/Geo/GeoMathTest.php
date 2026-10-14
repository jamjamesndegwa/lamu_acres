<?php

namespace Tests\Unit\Geo;

use App\Support\Geo\GeoMath;
use App\Support\Geo\Wkt;
use PHPUnit\Framework\TestCase;

class GeoMathTest extends TestCase
{
    public function test_distance_and_polygon_checks_use_the_centre_and_ring(): void
    {
        $this->assertSame(0.0, GeoMath::distanceKm(-2.27, 40.9, -2.27, 40.9));
        $this->assertEqualsWithDelta(1.11, GeoMath::distanceKm(-2.27, 40.9, -2.28, 40.9), 0.05);

        $ring = [[-2.28, 40.89], [-2.28, 40.91], [-2.26, 40.91], [-2.26, 40.89]];

        $this->assertTrue(GeoMath::contains(-2.27, 40.9, $ring));
        $this->assertFalse(GeoMath::contains(-2.3, 40.9, $ring));
    }

    public function test_wkt_is_longitude_latitude_and_can_feed_mysql_spatial_functions(): void
    {
        $point = Wkt::point(-2.27, 40.9);
        $polygon = Wkt::polygon([
            ['lat' => -2.28, 'lng' => 40.89],
            ['lat' => -2.28, 'lng' => 40.91],
            ['lat' => -2.26, 'lng' => 40.91],
            ['lat' => -2.26, 'lng' => 40.89],
        ]);

        $this->assertSame('POINT(40.9 -2.27)', $point);
        $this->assertSame('POLYGON((40.89 -2.28, 40.91 -2.28, 40.91 -2.26, 40.89 -2.26, 40.89 -2.28))', $polygon);
        $this->assertTrue(Wkt::isPoint($point));
        $this->assertTrue(Wkt::isPolygon($polygon));
        $this->assertSame("ST_GeomFromText('POINT(40.9 -2.27)')", Wkt::geomFromText($point));
        $this->assertSame("ST_GeomFromText('{$polygon}')", Wkt::geomFromText($polygon));
    }
}
