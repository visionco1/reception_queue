<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Bank;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** معيار القبول 10 والصلاحيات ولوحة الإدارة */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBasics();
    }

    public function test_every_admin_page_renders(): void
    {
        $admin = $this->employee(1, Role::Admin);
        $this->registerAtKiosk();
        $employee = $this->employee(2);
        $this->actingAs($employee)->postJson('/staff/visits/'.Visit::sole()->id.'/take');

        $pages = ['/admin', '/admin/reports', '/admin/reports?preset=month&group=week', '/admin/reports?from=2025-01-01&to=2026-12-31',
            '/admin/visits', '/admin/visits/'.Visit::sole()->id, '/admin/customers', '/admin/whatsapp', '/admin/users',
            '/admin/users/create', '/admin/users/'.$employee->id.'/edit', '/admin/desks', '/admin/banks', '/admin/settings',
            '/admin/messages', '/admin/audit', '/staff', '/staff/today', '/staff/customers'];

        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
    }

    public function test_exports_visits_and_customers_to_excel_and_csv(): void
    {
        $admin = $this->employee(1, Role::Admin);
        $this->registerAtKiosk();

        $xlsx = $this->actingAs($admin)->get('/admin/export/visits?format=xlsx');
        $xlsx->assertOk();
        $content = $xlsx->streamedContent();
        $this->assertStringStartsWith('PK', $content);

        $csv = $this->actingAs($admin)->get('/admin/export/visits?format=csv')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('محمد أحمد', $csv);
        $this->assertStringContainsString('M-001', $csv);
        $this->assertStringContainsString('بنك · مصرف الراجحي', $csv);

        $customers = $this->actingAs($admin)->get('/admin/export/customers?format=csv')->streamedContent();
        $this->assertStringContainsString('055 123 4567', $customers);
    }

    public function test_permissions_by_role(): void
    {
        $employee = $this->employee(1);
        $supervisor = $this->employee(2, Role::Supervisor);

        $this->actingAs($employee)->get('/admin')->assertForbidden();
        $this->actingAs($employee)->get('/admin/export/visits')->assertForbidden();

        $this->actingAs($supervisor)->get('/admin/reports')->assertOk();
        $this->actingAs($supervisor)->get('/admin/export/visits')->assertOk();
        $this->actingAs($supervisor)->get('/admin/settings')->assertForbidden();
        $this->actingAs($supervisor)->get('/admin/users')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->get('/staff')->assertRedirect('/login');
    }

    public function test_login_and_deactivated_users_are_locked_out(): void
    {
        $user = User::factory()->create(['username' => 'salman', 'password' => 'secret-pass']);

        $this->post('/login', ['username' => 'salman', 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->post('/login', ['username' => 'salman', 'password' => 'secret-pass'])->assertRedirect('/staff');

        $user->update(['is_active' => false]);
        $this->app['auth']->forgetGuards();
        $this->get('/staff')->assertRedirect('/login');
    }

    public function test_admin_manages_users_banks_desks_and_settings(): void
    {
        $admin = $this->employee(1, Role::Admin);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'موظف جديد', 'username' => 'NewUser', 'role' => 'employee', 'password' => 'password123', 'is_active' => '1',
        ])->assertRedirect('/admin/users');
        $this->assertDatabaseHas('users', ['username' => 'newuser', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/banks', ['name' => 'بنك البلاد'])->assertRedirect();
        $this->actingAs($admin)->post('/admin/desks', ['number' => '9'])->assertRedirect();

        $settings = [
            'company_name' => 'شركة سلطان الهيضل', 'ticket_prefix_buyer' => 'b', 'ticket_prefix_seller' => 'b', 'ticket_prefix_other' => 'g',
            'ticket_digits' => 3, 'kiosk_reset_seconds' => 10, 'default_service_minutes' => 15, 'country_code' => '966',
            'employee_search_scope' => 'all', 'display_mode' => 'current', 'display_alert_seconds' => 10, 'display_idle_minutes' => 10, 'display_volume' => 80, 'display_recent_count' => 5,
            'ticker_messages' => "أهلًا بكم\nيرجى إحضار الهوية", 'work_start' => '', 'work_end' => '',
        ];
        $this->actingAs($admin)->put('/admin/settings', $settings)->assertSessionHasErrors('ticket_prefix_buyer');
        $this->actingAs($admin)->put('/admin/settings', ['work_start' => '08:00'] + $settings)->assertSessionHasErrors('work_end');

        $this->actingAs($admin)->put('/admin/settings', ['ticket_prefix_buyer' => 'p', 'ticket_prefix_seller' => 's', 'display_mode' => 'offices'] + $settings)
            ->assertSessionHasNoErrors();
        $this->assertSame('P', setting('ticket_prefix_buyer'));
        $this->assertSame('offices', setting('display_mode'));
        $this->registerAtKiosk()->assertJsonPath('ticket_no', 'P-001');
        $this->getJson('/display/display-secret/feed')->assertJsonPath('mode', 'offices')->assertJsonPath('ticker', ['أهلًا بكم', 'يرجى إحضار الهوية']);

        // 4 بنوك فقط تظهر على الآيباد
        foreach (Bank::query()->get() as $bank) {
            $bank->update(['is_featured' => true]);
        }
        Bank::create(['name' => 'بنك ثالث', 'is_featured' => true]);
        Bank::create(['name' => 'بنك رابع', 'is_featured' => true]);
        $fifth = Bank::create(['name' => 'بنك خامس']);
        $this->actingAs($admin)->put("/admin/banks/{$fifth->id}", ['name' => 'بنك خامس', 'is_active' => '1', 'is_featured' => '1'])
            ->assertSessionHasErrors('is_featured');
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $admin = $this->employee(1, Role::Admin);
        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => $admin->name, 'username' => $admin->username, 'role' => 'employee',
        ])->assertSessionHas('error');
        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    public function test_regenerating_device_link_invalidates_the_old_one(): void
    {
        $admin = $this->employee(1, Role::Admin);
        $this->actingAs($admin)->post('/admin/settings/token/kiosk')->assertRedirect();
        $this->get('/kiosk/kiosk-secret')->assertNotFound();
        $this->get('/kiosk/'.setting('kiosk_token'))->assertOk();
    }
}
