<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Visit;
use App\Services\PerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** تقارير أداء الموظفين ونتائج الصفقات، وصفحة متابعة الدور للعميل */
class PerformanceAndTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBasics();
    }

    /** يخدم الموظف عميلًا: انتظار $wait دقيقة قبل النداء، خدمة $service دقيقة، ثم خمول $idle دقيقة */
    private function serve($employee, string $mobile, int $wait, int $service, string $outcome = 'completed', int $idle = 0): void
    {
        $this->registerAtKiosk(['mobile' => $mobile])->assertCreated();
        $visit = Visit::latest('id')->first();
        $this->travel($wait)->minutes();
        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/take")->assertOk();
        $this->travel($service)->minutes();
        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/complete", ['outcome' => $outcome])->assertOk();
        $this->travel($idle)->minutes();
    }

    public function test_employee_metrics_team_average_ranking_and_outcomes(): void
    {
        $this->travelTo(today()->setTime(9, 0));
        $a = $this->employee(1);
        $b = $this->employee(2);

        // أ: ثلاثة عملاء، خمول 4 ثم 6 دقائق
        $this->serve($a, '0550000001', 2, 10, 'completed', 4);
        $this->serve($a, '0550000002', 0, 20, 'missing_docs', 6);
        $this->serve($a, '0550000003', 0, 30);
        // ب: عميل واحد ألغيت صفقته
        $this->serve($b, '0550000004', 8, 10, 'cancelled');

        $summary = app(PerformanceService::class)->summary(today(), today());
        [$first, $second] = $summary['employees'];

        $this->assertSame($a->id, $first['id']);
        $this->assertSame(1, $first['rank']);
        $this->assertSame(3, $first['served']);
        $this->assertSame(20 * 60, $first['avg_service']);
        $this->assertSame(5 * 60, $first['avg_idle']);
        $this->assertSame(1, $first['missing_docs']);
        $this->assertSame($b->id, $second['id']);
        $this->assertSame(1, $second['cancelled']);
        $this->assertSame(8 * 60, $second['avg_call_wait']);

        $this->assertEquals(2.0, $summary['team']['served']);
        $this->assertSame(50, $first['vs_team']['served']);   // 3 مقابل متوسط 2
        $this->assertSame(-50, $second['vs_team']['served']);

        $this->assertSame(4, $summary['outcomes']['total']);
        $this->assertSame(2, $summary['outcomes']['items']['completed']['count']);
        $this->assertSame(1, $summary['outcomes']['items']['missing_docs']['count']);
        $this->assertSame(1, $summary['outcomes']['items']['cancelled']['count']);

        $trend = app(PerformanceService::class)->trend($a, today()->subDays(2), today());
        $this->assertCount(3, $trend);
        $this->assertSame(3, $trend[2]['served']);
        $this->assertEquals(2.0, $trend[2]['team_served']);
    }

    public function test_report_pages_render_for_managers_and_employees(): void
    {
        $this->travelTo(today()->setTime(9, 0));
        $admin = $this->employee(1, Role::Admin);
        $employee = $this->employee(2);
        $this->serve($employee, '0550000001', 1, 5, 'missing_docs');

        $this->actingAs($admin)->get('/admin/reports?preset=today')->assertOk()
            ->assertSee('أداء الموظفين وترتيبهم')->assertSee('نتائج الصفقات')->assertSee($employee->name);
        $this->actingAs($admin)->get("/admin/reports/employees/{$employee->id}?preset=today")->assertOk()
            ->assertSee('متوسط الفريق')->assertSee('ناقصة أوراق');

        // الموظف يرى أداءه فقط، والمشرف يُحوَّل لتقارير الإدارة
        $this->actingAs($employee)->get('/staff/performance?preset=today')->assertOk()->assertSee('أدائي')->assertSee('#1');
        $this->actingAs($employee)->get('/admin/reports')->assertForbidden();
        $this->actingAs($admin)->get('/staff/performance')->assertRedirect();
    }

    public function test_customer_follows_the_turn_from_the_whatsapp_link(): void
    {
        $employee = $this->employee(2);
        $this->registerAtKiosk(['mobile' => '0550000001']);
        $this->registerAtKiosk(['mobile' => '0550000002']);
        [$first, $second] = Visit::orderBy('id')->get();

        $this->get('/t/'.$second->track_token)->assertOk()->assertSee('M-002');
        $this->getJson('/t/'.$second->track_token.'/state')->assertJson(['status' => 'waiting', 'ahead' => 1, 'minutes' => 15]);

        $this->actingAs($employee)->postJson("/staff/visits/{$second->id}/take")->assertOk();
        $this->getJson('/t/'.$second->track_token.'/state')->assertJson(['status' => 'called', 'desk' => '2']);
        $this->getJson('/t/'.$first->track_token.'/state')->assertJson(['ahead' => 0]);

        $this->get('/t/not-a-real-token-123456')->assertNotFound();
    }
}
