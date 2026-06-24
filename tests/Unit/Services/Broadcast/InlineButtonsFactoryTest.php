<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Broadcast;

use App\Services\Broadcast\InlineButtonsFactory;
use Tests\TestCase;

final class InlineButtonsFactoryTest extends TestCase
{
    public function test_returns_null_for_empty_rows(): void
    {
        $factory = new InlineButtonsFactory;

        $this->assertNull($factory->fromRows(null));
        $this->assertNull($factory->fromRows([]));
    }

    public function test_skips_buttons_without_text_or_url(): void
    {
        $factory = new InlineButtonsFactory;

        $markup = $factory->fromRows([
            [['text' => '', 'url' => 'https://example.com']],
            [['text' => 'Bo\'sh url', 'url' => '']],
        ]);

        $this->assertNull($markup);
    }

    public function test_builds_plain_url_button_for_external_link(): void
    {
        $factory = new InlineButtonsFactory;

        $markup = $factory->fromRows([
            [['text' => 'Sayt', 'url' => 'https://example.com/page']],
        ]);

        $button = $markup->inline_keyboard[0][0];

        $this->assertSame('https://example.com/page', $button->url);
        $this->assertNull($button->web_app);
    }

    public function test_builds_web_app_button_for_miniapp_url(): void
    {
        $factory = new InlineButtonsFactory;

        $markup = $factory->fromRows([
            [['text' => 'Buyurtma berish', 'url' => 'https://opensales.uz/miniapp/3']],
        ]);

        $button = $markup->inline_keyboard[0][0];

        $this->assertNull($button->url);
        $this->assertNotNull($button->web_app);
        $this->assertSame('https://opensales.uz/miniapp/3', $button->web_app->url);
    }

    public function test_keeps_plain_url_for_non_miniapp_path(): void
    {
        $factory = new InlineButtonsFactory;

        $markup = $factory->fromRows([
            [['text' => 'Profil', 'url' => 'https://opensales.uz/dashboard']],
        ]);

        $button = $markup->inline_keyboard[0][0];

        $this->assertSame('https://opensales.uz/dashboard', $button->url);
        $this->assertNull($button->web_app);
    }

    public function test_keeps_plain_url_for_non_https_miniapp(): void
    {
        $factory = new InlineButtonsFactory;

        $markup = $factory->fromRows([
            [['text' => 'Test', 'url' => 'http://opensales.uz/miniapp/3']],
        ]);

        $button = $markup->inline_keyboard[0][0];

        $this->assertSame('http://opensales.uz/miniapp/3', $button->url);
        $this->assertNull($button->web_app);
    }
}
