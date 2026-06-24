<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\ProductCategory;
use App\Models\User;
use App\Policies\ProductCategoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductCategoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ProductCategoryPolicy $policy;

    private Dealer $dealer;

    private ProductCategory $category;

    private User $owner;

    private User $warehouse;

    private User $deliveryman;

    private User $otherDealerOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ProductCategoryPolicy;
        $this->dealer = Dealer::factory()->create();
        $this->category = ProductCategory::factory()->for($this->dealer)->create();
        $this->owner = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DEALER]);
        $this->warehouse = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::WAREHOUSE]);
        $this->deliveryman = User::factory()->create(['dealer_id' => $this->dealer->id, 'role' => UserRole::DELIVERYMAN]);

        $otherDealer = Dealer::factory()->create();
        $this->otherDealerOwner = User::factory()->create(['dealer_id' => $otherDealer->id, 'role' => UserRole::DEALER]);
    }

    public function test_view_any_allows_all_dealer_staff(): void
    {
        $this->assertTrue($this->policy->viewAny($this->owner));
        $this->assertTrue($this->policy->viewAny($this->warehouse));
        $this->assertTrue($this->policy->viewAny($this->deliveryman));
    }

    public function test_view_allows_dealer_staff_within_same_dealer_only(): void
    {
        $this->assertTrue($this->policy->view($this->owner, $this->category));
        $this->assertTrue($this->policy->view($this->warehouse, $this->category));
        $this->assertFalse($this->policy->view($this->otherDealerOwner, $this->category));
    }

    public function test_write_actions_allow_owner_and_warehouse_only(): void
    {
        $this->assertTrue($this->policy->create($this->owner));
        $this->assertTrue($this->policy->create($this->warehouse));
        $this->assertFalse($this->policy->create($this->deliveryman));

        $this->assertTrue($this->policy->update($this->owner, $this->category));
        $this->assertTrue($this->policy->update($this->warehouse, $this->category));
        $this->assertFalse($this->policy->update($this->deliveryman, $this->category));
        $this->assertFalse($this->policy->update($this->otherDealerOwner, $this->category));

        $this->assertTrue($this->policy->delete($this->owner, $this->category));
        $this->assertTrue($this->policy->delete($this->warehouse, $this->category));
        $this->assertFalse($this->policy->delete($this->deliveryman, $this->category));
        $this->assertFalse($this->policy->delete($this->otherDealerOwner, $this->category));
    }
}
