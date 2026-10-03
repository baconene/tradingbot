<?php

namespace Tests\Feature;

use Tests\TestCase;

final class WelcomeTest extends TestCase
{
    public function test_fresh_homepage_loads_without_trading_features(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('A fresh start.')
            ->assertSee('Trading disabled');
    }

    public function test_old_trading_endpoints_are_not_available(): void
    {
        $this->get('/api/chart')->assertNotFound();
        $this->get('/api/futures/terminal')->assertNotFound();
        $this->get('/api/research/predictions')->assertNotFound();
    }
}
