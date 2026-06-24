<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MapLinkParser;
use PHPUnit\Framework\TestCase;

final class MapLinkParserTest extends TestCase
{
    public function test_parses_plain_coordinates(): void
    {
        $this->assertSame(
            ['lat' => 41.3111, 'lng' => 69.2797],
            MapLinkParser::parse('41.3111, 69.2797'),
        );
    }

    public function test_parses_plain_coordinates_with_space(): void
    {
        $this->assertSame(
            ['lat' => 41.3111, 'lng' => 69.2797],
            MapLinkParser::parse('41.3111 69.2797'),
        );
    }

    public function test_parses_google_at_pattern(): void
    {
        $coords = MapLinkParser::parse('https://www.google.com/maps/place/Toshkent/@41.3111,69.2797,15z');

        $this->assertSame(['lat' => 41.3111, 'lng' => 69.2797], $coords);
    }

    public function test_parses_google_q_pattern(): void
    {
        $coords = MapLinkParser::parse('https://www.google.com/maps?q=41.3111,69.2797');

        $this->assertSame(['lat' => 41.3111, 'lng' => 69.2797], $coords);
    }

    public function test_parses_google_3d_4d_pattern(): void
    {
        $coords = MapLinkParser::parse('https://www.google.com/maps/place/Toshkent/data=!3m1!4b1!4m5!3m4!1s0x123!8m2!3d41.3111!4d69.2797');

        $this->assertSame(['lat' => 41.3111, 'lng' => 69.2797], $coords);
    }

    public function test_parses_yandex_ll_with_lng_lat_order(): void
    {
        $coords = MapLinkParser::parse('https://yandex.uz/maps/?ll=69.2797%2C41.3111&z=15');

        $this->assertSame(['lat' => 41.3111, 'lng' => 69.2797], $coords);
    }

    public function test_parses_yandex_ll_with_decoded_comma(): void
    {
        $coords = MapLinkParser::parse('https://yandex.com/maps/?ll=69.2797,41.3111');

        $this->assertSame(['lat' => 41.3111, 'lng' => 69.2797], $coords);
    }

    public function test_parses_yandex_pt_point(): void
    {
        $coords = MapLinkParser::parse('https://yandex.ru/maps/?pt=69.2797,41.3111');

        $this->assertSame(['lat' => 41.3111, 'lng' => 69.2797], $coords);
    }

    public function test_parses_google_search_path_with_plus_sign(): void
    {
        // maps.app.goo.gl redirect tugashi shakli: /maps/search/lat,+lng
        $coords = MapLinkParser::parse('https://www.google.com/maps/search/41.001531,+71.672291?entry=tts');

        $this->assertSame(['lat' => 41.001531, 'lng' => 71.672291], $coords);
    }

    public function test_parses_google_search_path_without_plus_sign(): void
    {
        $coords = MapLinkParser::parse('https://www.google.com/maps/search/41.001531,71.672291');

        $this->assertSame(['lat' => 41.001531, 'lng' => 71.672291], $coords);
    }

    public function test_parses_google_dir_path(): void
    {
        $coords = MapLinkParser::parse('https://www.google.com/maps/dir//41.3111,69.2797/@41.3,69.2,12z');

        $this->assertSame(['lat' => 41.3111, 'lng' => 69.2797], $coords);
    }

    public function test_returns_null_for_zero_coordinates(): void
    {
        $this->assertNull(MapLinkParser::parse('0, 0'));
    }

    public function test_returns_null_for_invalid_input(): void
    {
        $this->assertNull(MapLinkParser::parse('hello world'));
        $this->assertNull(MapLinkParser::parse(''));
        $this->assertNull(MapLinkParser::parse('https://example.com/'));
    }

    public function test_returns_null_for_out_of_range_coordinates(): void
    {
        $this->assertNull(MapLinkParser::parse('200, 500'));
    }
}
