<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Broadcast;

use App\Models\BroadcastCampaign;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Services\Broadcast\BroadcastRenderer;
use Tests\TestCase;

final class BroadcastRendererTest extends TestCase
{
    public function test_replaces_shop_and_dealer_placeholders(): void
    {
        $dealer = new Dealer;
        $dealer->name = 'AlfaSavdo';

        $shop = new Shop;
        $shop->name = 'Olma do\'koni';
        $shop->phone = '+998901234567';
        $shop->balance = -50_000;

        $campaign = new BroadcastCampaign;
        $campaign->message_text = 'Salom {shop_name}! Saldo: {balance} so\'m. Distribyutor: {dealer_name}, tel: {shop_phone}';
        $campaign->timezone = 'Asia/Tashkent';

        $rendered = (new BroadcastRenderer)->render($campaign, $shop, $dealer);

        $this->assertStringContainsString('Olma do\'koni', $rendered);
        $this->assertStringContainsString('-50 000', $rendered);
        $this->assertStringContainsString('AlfaSavdo', $rendered);
        $this->assertStringContainsString('+998901234567', $rendered);
    }

    public function test_replaces_contact_person_and_member_name(): void
    {
        $shop = new Shop;
        $shop->name = 'Olma do\'koni';
        $shop->contact_person = 'Anvar aka';

        $member = new ShopMember;
        $member->name = 'Dilshod';

        $campaign = new BroadcastCampaign;
        $campaign->message_text = 'Hurmatli {member_name}! Aloqa: {contact_person}, do\'kon: {shop_name}';
        $campaign->timezone = 'Asia/Tashkent';

        $rendered = (new BroadcastRenderer)->render($campaign, $shop, null, $member);

        $this->assertStringContainsString('Dilshod', $rendered);
        $this->assertStringContainsString('Anvar aka', $rendered);
        $this->assertStringContainsString('Olma do\'koni', $rendered);
    }

    public function test_missing_member_and_contact_render_empty(): void
    {
        $campaign = new BroadcastCampaign;
        $campaign->message_text = 'X{member_name}Y{contact_person}Z';
        $campaign->timezone = 'Asia/Tashkent';

        $rendered = (new BroadcastRenderer)->render($campaign);

        $this->assertSame('XYZ', $rendered);
    }

    public function test_renders_date_and_time(): void
    {
        $campaign = new BroadcastCampaign;
        $campaign->message_text = 'Bugun: {date}, vaqt: {time}';
        $campaign->timezone = 'Asia/Tashkent';

        $rendered = (new BroadcastRenderer)->render($campaign);

        $this->assertMatchesRegularExpression('/\d{2}\.\d{2}\.\d{4}/', $rendered);
        $this->assertMatchesRegularExpression('/\d{2}:\d{2}/', $rendered);
    }
}
