<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Bank;
use App\Models\Desk;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function seedBasics(): void
    {
        foreach (range(1, 3) as $n) {
            Desk::create(['number' => (string) $n, 'sort_order' => $n]);
        }
        Bank::create(['name' => 'مصرف الراجحي', 'sort_order' => 1]);
        Bank::create(['name' => 'البنك الأهلي السعودي', 'sort_order' => 2]);
        app(Settings::class)->set(['kiosk_token' => 'kiosk-secret', 'display_token' => 'display-secret']);
    }

    protected function employee(int $desk = 1, Role $role = Role::Employee): User
    {
        return User::factory()->create([
            'role' => $role,
            'desk_id' => Desk::where('number', (string) $desk)->value('id'),
        ]);
    }

    protected function registerAtKiosk(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/kiosk/kiosk-secret/register', array_merge([
            'name' => 'محمد أحمد',
            'mobile' => '0551234567',
            'customer_type' => 'buyer',
            'bank_id' => (string) Bank::first()->id,
        ], $overrides));
    }
}
