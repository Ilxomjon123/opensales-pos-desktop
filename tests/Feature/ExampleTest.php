<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_landing_page()
    {
        $this->withoutVite()
            ->get(route('home'))
            ->assertOk();
    }
}
