<?php

namespace Tests\Feature;

use App\Exceptions\QueueException;
use App\Models\DisplayCall;
use App\Models\ServiceSession;
use App\Models\Visit;
use App\Models\VisitStatusLog;
use App\Models\WhatsappLog;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** معايير القبول 2، 3، 4، 5، 8: الطابور المشترك والقفل والشاشة والأوقات */
class QueueFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBasics();
    }

    public function test_new_customer_appears_immediately_in_every_employees_queue(): void
    {
        $a = $this->employee(1);
        $b = $this->employee(2);
        $this->registerAtKiosk();

        foreach ([$a, $b] as $user) {
            $this->actingAs($user)->getJson('/staff/state')
                ->assertOk()
                ->assertJsonCount(1, 'waiting')
                ->assertJsonPath('waiting.0.ticket_no', 'M-001')
                ->assertJsonPath('waiting.0.name', 'محمد أحمد')
                ->assertJsonPath('waiting.0.phone', '05X XXX 4567')
                ->assertJsonPath('waiting.0.type_label', 'مشتري')
                ->assertJsonPath('waiting.0.via', 'بنك · مصرف الراجحي')
                ->assertJsonPath('counts.bank', 1)
                ->assertJsonPath('stats.waiting', 1);
        }
    }

    public function test_two_employees_cannot_take_the_same_customer(): void
    {
        $a = $this->employee(1);
        $b = $this->employee(2);
        $this->registerAtKiosk();
        $visit = Visit::sole();

        $this->actingAs($a)->postJson("/staff/visits/{$visit->id}/take")->assertOk();
        $this->actingAs($b)->postJson("/staff/visits/{$visit->id}/take")
            ->assertStatus(409)
            ->assertJsonPath('message', 'أخذ موظف آخر هذا العميل للتو. اختر عميلًا آخر من القائمة.');

        $this->assertSame($a->id, $visit->fresh()->employee_id);
        $this->assertSame(1, ServiceSession::count());
        $this->assertSame(1, DisplayCall::count());
    }

    public function test_stale_model_cannot_be_taken_twice_at_service_level(): void
    {
        // يحاكي طلبين وصلا في نفس اللحظة بنسخة قديمة من السجل لكل منهما
        $a = $this->employee(1);
        $b = $this->employee(2);
        $this->registerAtKiosk();
        $copyA = Visit::sole();
        $copyB = Visit::sole();
        $queue = app(QueueService::class);

        $queue->take($copyA, $a);
        $this->expectException(QueueException::class);
        $queue->take($copyB, $b);
    }

    public function test_taking_a_customer_shows_ticket_and_desk_on_the_display_and_sends_whatsapp(): void
    {
        $employee = $this->employee(3);
        $this->registerAtKiosk();
        $visit = Visit::sole();

        $this->getJson('/display/display-secret/feed')->assertOk()->assertJsonPath('current', null)
            ->assertJsonPath('waiting.total', 1)->assertJsonPath('waiting.buyer', 1);

        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/take")->assertOk();

        $this->getJson('/display/display-secret/feed')
            ->assertOk()
            ->assertJsonPath('current.tickets', ['M-001'])
            ->assertJsonPath('current.desk', '3')
            ->assertJsonPath('current.names', ['محمد أ.'])
            ->assertJsonPath('waiting.total', 0)
            ->assertJsonPath('offices.2.tickets', ['M-001']);

        $called = WhatsappLog::where('message_key', 'called')->sole();
        $this->assertSame('حان دورك، تفضّل إلى مكتب 3.', $called->body);
        $this->assertSame('called', $visit->fresh()->status->value);
    }

    public function test_full_service_records_wait_service_times_employee_and_thanks_message(): void
    {
        $employee = $this->employee(2);
        $this->registerAtKiosk();
        $visit = Visit::sole();

        $this->travel(7)->minutes();
        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/take")->assertOk();
        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/start")->assertOk();
        $this->travel(12)->minutes();
        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/complete", ['notes' => 'تم توقيع العقد'])->assertOk();
        $this->assertSame('completed', $visit->fresh()->outcome->value);

        $visit->refresh();
        $this->assertSame('completed', $visit->status->value);
        $this->assertSame($employee->id, $visit->employee_id);
        $this->assertEqualsWithDelta(7 * 60, $visit->wait_seconds, 2);
        $this->assertEqualsWithDelta(12 * 60, $visit->service_seconds, 2);
        $this->assertSame('تم توقيع العقد', $visit->notes);
        $this->assertNotNull($visit->ticket->called_at);
        $this->assertSame('2', $visit->ticket->desk_label);

        $this->assertSame(
            ['waiting', 'called', 'in_service', 'completed'],
            VisitStatusLog::where('visit_id', $visit->id)->orderBy('id')->get()->map(fn ($l) => $l->to_status->value)->all()
        );
        $this->assertSame(['registered', 'called', 'thanks'], WhatsappLog::orderBy('id')->get()->map(fn ($l) => $l->message_key->value)->all());
    }

    public function test_employee_must_finish_current_customer_before_taking_another(): void
    {
        $employee = $this->employee(1);
        $this->registerAtKiosk(['mobile' => '0550000001']);
        $this->registerAtKiosk(['mobile' => '0550000002']);
        [$first, $second] = Visit::orderBy('id')->get();

        $this->actingAs($employee)->postJson("/staff/visits/{$first->id}/take")->assertOk();
        $this->actingAs($employee)->postJson("/staff/visits/{$second->id}/take")->assertStatus(422);
        $this->assertSame('waiting', $second->fresh()->status->value);
    }

    public function test_employee_without_a_desk_is_asked_to_choose_one(): void
    {
        $employee = \App\Models\User::factory()->create(['desk_id' => null]);
        $this->registerAtKiosk();

        $this->actingAs($employee)->postJson('/staff/visits/'.Visit::sole()->id.'/take')
            ->assertStatus(422)->assertJsonPath('message', 'اختر رقم مكتبك أولًا قبل أخذ العملاء.');

        $this->actingAs($employee)->post('/staff/desk', ['desk_id' => \App\Models\Desk::where('number', '2')->value('id')])->assertRedirect();
        $this->actingAs($employee->fresh())->postJson('/staff/visits/'.Visit::sole()->id.'/take')->assertOk();
    }

    public function test_release_returns_customer_to_queue_for_another_employee(): void
    {
        $a = $this->employee(1);
        $b = $this->employee(2);
        $this->registerAtKiosk();
        $visit = Visit::sole();

        $this->actingAs($a)->postJson("/staff/visits/{$visit->id}/take")->assertOk();
        $this->actingAs($a)->postJson("/staff/visits/{$visit->id}/release")->assertOk();
        $this->assertSame('waiting', $visit->fresh()->status->value);
        $this->assertNull($visit->fresh()->employee_id);

        $this->actingAs($b)->postJson("/staff/visits/{$visit->id}/take")->assertOk();
        $this->assertSame($b->id, $visit->fresh()->employee_id);
    }

    public function test_only_the_assigned_employee_can_act_on_a_customer(): void
    {
        $a = $this->employee(1);
        $b = $this->employee(2);
        $this->registerAtKiosk();
        $visit = Visit::sole();

        $this->actingAs($a)->postJson("/staff/visits/{$visit->id}/take")->assertOk();
        $this->actingAs($b)->postJson("/staff/visits/{$visit->id}/complete")->assertStatus(409);
        $this->actingAs($b)->postJson("/staff/visits/{$visit->id}/no-show")->assertStatus(409);
    }

    public function test_no_show_moves_to_end_of_queue_once_then_cancels_the_ticket(): void
    {
        $employee = $this->employee(1);
        $this->registerAtKiosk(['mobile' => '0550000001']);
        $this->registerAtKiosk(['mobile' => '0550000002']);
        [$first, $second] = Visit::orderBy('id')->get();

        $this->actingAs($employee)->postJson("/staff/visits/{$first->id}/take")->assertOk();
        $this->actingAs($employee)->postJson("/staff/visits/{$first->id}/recall")->assertOk();
        $this->assertSame(2, DisplayCall::count());
        $this->getJson('/display/display-secret/feed')->assertJsonPath('current.recall', true);

        // أول مرة: يعود لآخر الدور
        $this->travel(1)->minutes();
        $this->actingAs($employee)->postJson("/staff/visits/{$first->id}/no-show")->assertOk()
            ->assertJsonPath('message', 'M-001 لم يحضر، وأُعيد لآخر قائمة الانتظار');
        $this->assertSame('waiting', $first->fresh()->status->value);
        $this->assertSame(1, $first->fresh()->ticket->no_show_count);
        $this->assertSame([$second->id, $first->id], app(QueueService::class)->waitingQuery()->pluck('visits.id')->all());

        // المرة الثانية: تُلغى التذكرة
        $this->actingAs($employee)->postJson("/staff/visits/{$first->id}/take")->assertOk();
        $this->actingAs($employee)->postJson("/staff/visits/{$first->id}/no-show")->assertOk()
            ->assertJsonPath('message', 'M-001 لم يحضر للمرة الثانية، وأُلغيت التذكرة');
        $this->assertSame('no_show', $first->fresh()->status->value);
        $this->assertSame(0, WhatsappLog::where('message_key', 'thanks')->count());
    }

    public function test_finishing_records_the_deal_outcome(): void
    {
        $employee = $this->employee(1);
        $this->registerAtKiosk();
        $visit = Visit::sole();

        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/take")->assertOk();
        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/complete", ['outcome' => 'missing_docs'])
            ->assertOk()->assertJsonPath('message', 'انتهت الخدمة (ناقصة أوراق). يمكنك نداء العميل التالي.');
        $this->assertSame('missing_docs', $visit->fresh()->outcome->value);

        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/complete", ['outcome' => 'bad'])->assertStatus(422);
    }

    public function test_transfer_to_another_desk_keeps_seniority_and_only_that_desk_can_call(): void
    {
        $a = $this->employee(1);
        $b = $this->employee(2);
        $c = $this->employee(3);
        $this->registerAtKiosk(['mobile' => '0550000001']);
        $this->registerAtKiosk(['mobile' => '0550000002']);
        [$first] = Visit::orderBy('id')->get();
        $desk2 = \App\Models\Desk::where('number', '2')->first();

        $this->actingAs($a)->postJson("/staff/visits/{$first->id}/take")->assertOk();
        $this->actingAs($a)->postJson("/staff/visits/{$first->id}/transfer", ['desk_id' => $desk2->id])
            ->assertOk()->assertJsonPath('message', 'حُوّل M-001 إلى مكتب 2');

        $first->refresh();
        $this->assertSame('waiting', $first->status->value);
        $this->assertSame($desk2->id, $first->assigned_desk_id);

        $this->actingAs($b)->getJson('/staff/state')->assertJsonPath('waiting.0.ticket_no', 'M-001')->assertJsonPath('waiting.0.assigned_to_me', true);
        $this->actingAs($c)->postJson("/staff/visits/{$first->id}/take")->assertStatus(409);
        $this->actingAs($b)->postJson("/staff/visits/{$first->id}/take")->assertOk();
        $this->assertNull($first->fresh()->assigned_desk_id);
        $this->getJson('/display/display-secret/feed')->assertJsonPath('current.desk', '2');
    }

    public function test_incomplete_customer_can_be_deleted_but_not_a_completed_service(): void
    {
        $employee = $this->employee(1);
        $this->registerAtKiosk(['mobile' => '0550000001']);
        $this->registerAtKiosk(['mobile' => '0550000002']);
        [$first, $second] = Visit::orderBy('id')->get();

        $this->actingAs($employee)->deleteJson("/staff/visits/{$first->id}")->assertOk()->assertJsonPath('message', 'حُذف M-001');
        $this->assertModelMissing($first);
        $this->assertSame(1, \App\Models\Customer::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'visit.deleted']);

        $this->actingAs($employee)->postJson("/staff/visits/{$second->id}/take")->assertOk();
        $this->actingAs($employee)->postJson("/staff/visits/{$second->id}/complete")->assertOk();
        $this->actingAs($employee)->deleteJson("/staff/visits/{$second->id}")->assertStatus(409);
    }

    public function test_today_list_shows_all_of_todays_tickets(): void
    {
        $employee = $this->employee(1);
        $this->registerAtKiosk(['mobile' => '0550000001']);
        $this->registerAtKiosk(['mobile' => '0550000002', 'customer_type' => 'seller']);

        $this->actingAs($employee)->get('/staff/today')->assertOk()
            ->assertSee('قائمة انتظار اليوم')->assertSee('M-001')->assertSee('S-001');
        $this->actingAs($employee)->get('/staff/today?status=completed')->assertOk()->assertDontSee('S-001');
    }

    public function test_estimate_uses_average_service_today_and_working_desks(): void
    {
        $queue = app(QueueService::class);
        $this->assertSame(30, $queue->estimateMinutes(2)); // 15 دقيقة افتراضيًا ومكتب واحد

        $a = $this->employee(1);
        $b = $this->employee(2);
        $a->forceFill(['current_desk_id' => $a->desk_id, 'last_login_at' => now()])->save();
        $b->forceFill(['current_desk_id' => $b->desk_id, 'last_login_at' => now()])->save();
        $this->registerAtKiosk();
        $visit = Visit::sole();
        $this->actingAs($a)->postJson("/staff/visits/{$visit->id}/take");
        $this->travel(10)->minutes();
        $this->actingAs($a)->postJson("/staff/visits/{$visit->id}/complete");

        // 4 عملاء × 10 دقائق ÷ مكتبين
        $this->assertSame(20, $queue->estimateMinutes(4));
    }

    public function test_waiting_customer_can_be_cancelled_once(): void
    {
        $employee = $this->employee(1);
        $this->registerAtKiosk();
        $visit = Visit::sole();

        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/cancel")->assertOk();
        $this->assertSame('cancelled', $visit->fresh()->status->value);
        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/cancel")->assertStatus(409);
    }

    public function test_near_turn_message_is_sent_once_when_enabled(): void
    {
        app(\App\Support\Settings::class)->set(['wa_near_turn_enabled' => true, 'wa_near_turn_threshold' => 1]);
        $employee = $this->employee(1);
        foreach (['0550000001', '0550000002', '0550000003'] as $m) {
            $this->registerAtKiosk(['mobile' => $m]);
        }
        $first = Visit::orderBy('id')->first();

        $this->actingAs($employee)->postJson("/staff/visits/{$first->id}/take")->assertOk();

        // بعد أخذ الأول: الثالث أصبح أمامه عميل واحد
        $near = WhatsappLog::where('message_key', 'near_turn')->sole();
        $this->assertSame('966550000003', $near->to_phone);
    }

    public function test_display_feed_sends_state_settings_and_is_idle_outside_work_hours(): void
    {
        app(\App\Support\Settings::class)->set(['display_alert_seconds' => 8, 'display_idle_minutes' => 15, 'display_volume' => 40]);

        $this->getJson('/display/display-secret/feed')->assertOk()
            ->assertJsonPath('open', true)
            ->assertJsonPath('alert_seconds', 8)
            ->assertJsonPath('idle_minutes', 15)
            ->assertJsonPath('volume', 40);

        $this->travelTo(today()->setTime(23, 0));
        app(\App\Support\Settings::class)->set(['work_start' => '08:00', 'work_end' => '16:00']);
        $this->getJson('/display/display-secret/feed')->assertOk()->assertJsonPath('open', false);
    }

    public function test_display_feed_requires_secret(): void
    {
        $this->get('/display/display-secret')->assertOk();
        $this->getJson('/display/nope/feed')->assertNotFound();
    }

    public function test_stale_visits_are_closed_overnight(): void
    {
        $this->registerAtKiosk();
        $this->travel(1)->days();
        $this->artisan('qms:close-stale')->assertSuccessful();
        $this->assertSame('cancelled', Visit::sole()->status->value);
    }
}
