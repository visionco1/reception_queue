<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Bank;
use App\Models\BankAgent;
use App\Models\ClientType;
use App\Models\Deal;
use App\Models\DisplayCall;
use App\Models\Visit;
use App\Models\WhatsappLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** وكلاء البنوك، ربط البائع بالمشتري والاستدعاء معًا، والأنواع الأخرى للعملاء عن بعد */
class AgentsAndDealsTest extends TestCase
{
    use RefreshDatabase;

    private BankAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBasics();
        $this->agent = BankAgent::create(['bank_id' => Bank::first()->id, 'name' => 'خالد المندوب']);
    }

    private function register(string $mobile, string $type, ?string $agent = null): Visit
    {
        $this->registerAtKiosk(['mobile' => $mobile, 'customer_type' => $type, 'agent_id' => $agent ?? (string) $this->agent->id])->assertCreated();

        return Visit::latest('id')->first();
    }

    public function test_kiosk_requires_an_agent_choice_when_the_bank_has_agents(): void
    {
        $this->registerAtKiosk()->assertUnprocessable()->assertJsonValidationErrors('agent_id');

        $this->registerAtKiosk(['agent_id' => (string) $this->agent->id])->assertCreated()->assertJsonPath('agent', 'خالد المندوب');
        $this->assertSame($this->agent->id, Visit::sole()->agent_id);

        $this->registerAtKiosk(['mobile' => '0550000009', 'agent_id' => 'none'])->assertCreated()->assertJsonPath('agent', null);
    }

    public function test_kiosk_rejects_agent_of_another_bank_and_banks_without_agents_need_none(): void
    {
        $other = Bank::where('name', 'البنك الأهلي السعودي')->first();
        $this->registerAtKiosk(['bank_id' => (string) $other->id, 'agent_id' => (string) $this->agent->id])
            ->assertUnprocessable()->assertJsonValidationErrors('agent_id');

        $this->registerAtKiosk(['bank_id' => (string) $other->id])->assertCreated();
    }

    public function test_kiosk_only_accepts_buyer_or_seller(): void
    {
        $this->registerAtKiosk(['customer_type' => 'other', 'agent_id' => 'none'])->assertUnprocessable()->assertJsonValidationErrors('customer_type');
        $this->get('/kiosk/kiosk-secret')->assertSee('خالد المندوب');
    }

    public function test_candidates_suggest_same_agent_opposite_type_first(): void
    {
        $employee = $this->employee(1);
        $seller = $this->register('0550000001', 'seller');
        $this->register('0550000002', 'buyer', 'none');
        $buyer = $this->register('0550000003', 'buyer');

        $this->actingAs($employee)->postJson("/staff/visits/{$seller->id}/take")->assertOk();

        $this->actingAs($employee)->getJson("/staff/visits/{$seller->id}/candidates")
            ->assertOk()
            ->assertJsonPath('candidates.0.id', $buyer->id)
            ->assertJsonPath('candidates.0.suggested', true)
            ->assertJsonCount(2, 'candidates');
    }

    public function test_linking_calls_the_waiting_partner_to_the_same_desk(): void
    {
        $employee = $this->employee(2);
        $seller = $this->register('0550000001', 'seller');
        $buyer = $this->register('0550000002', 'buyer');

        $this->actingAs($employee)->postJson("/staff/visits/{$seller->id}/take")->assertOk();
        $this->actingAs($employee)->postJson("/staff/visits/{$seller->id}/link", ['other_id' => $buyer->id])
            ->assertOk()->assertJsonPath('message', 'رُبط M001 واستُدعي إلى مكتبك الآن');

        $buyer->refresh();
        $this->assertSame('called', $buyer->status->value);
        $this->assertSame($employee->id, $buyer->employee_id);
        $this->assertSame($seller->fresh()->deal_id, $buyer->deal_id);
        $this->assertSame('2', $buyer->ticket->desk_label);
        $this->assertSame(1, WhatsappLog::where('message_key', 'called')->where('visit_id', $buyer->id)->count());
        // الشاشة تعرض الطرفين معًا لنفس المكتب لأن البائع لم يصل بعد
        $this->getJson('/display/display-secret/feed')->assertJsonPath('current.tickets', ['M001', 'B001'])->assertJsonPath('current.desk', '2');

        // حالة الموظف: الطرف الآخر معه
        $this->actingAs($employee)->getJson('/staff/state')
            ->assertJsonPath('current.id', $seller->id)
            ->assertJsonPath('current.linked.0.id', $buyer->id)
            ->assertJsonPath('current.linked.0.mine', true);
    }

    public function test_taking_a_linked_customer_calls_both_together_and_display_shows_both(): void
    {
        $a = $this->employee(1);
        $b = $this->employee(3);
        $seller = $this->register('0550000001', 'seller');
        $buyer = $this->register('0550000002', 'buyer');
        $other = $this->register('0550000003', 'seller', 'none');

        // ربط مسبق ثم إرجاع الطرفين للطابور
        $this->actingAs($a)->postJson("/staff/visits/{$seller->id}/take");
        $this->actingAs($a)->postJson("/staff/visits/{$seller->id}/link", ['other_id' => $buyer->id]);
        $this->actingAs($a)->postJson("/staff/visits/{$buyer->id}/release")->assertOk();
        $this->actingAs($a)->postJson("/staff/visits/{$seller->id}/release")->assertOk();
        $this->assertSame(2, Visit::where('status', 'waiting')->whereNotNull('deal_id')->count());

        // موظف آخر يأخذ المشتري فيُستدعى البائع معه
        $this->actingAs($b)->postJson("/staff/visits/{$buyer->id}/take")
            ->assertOk()->assertJsonPath('message', 'تم استدعاء M001 مع الطرف الآخر B001 إلى مكتب 3');
        $this->assertSame($b->id, $seller->fresh()->employee_id);
        $this->assertSame('waiting', $other->fresh()->status->value);

        $this->getJson('/display/display-secret/feed')
            ->assertJsonPath('current.desk', '3')
            ->assertJsonPath('current.tickets', ['M001', 'B001']);

        // إنهاء الخدمة ينهي الطرفين ويرسل الشكر لكليهما
        $this->actingAs($b)->postJson("/staff/visits/{$buyer->id}/complete")->assertOk();
        $this->assertSame('completed', $seller->fresh()->status->value);
        $this->assertSame('completed', $buyer->fresh()->status->value);
        $this->assertSame(2, WhatsappLog::where('message_key', 'thanks')->count());
    }

    public function test_partner_already_with_another_employee_is_linked_but_not_moved(): void
    {
        $a = $this->employee(1);
        $b = $this->employee(2);
        $seller = $this->register('0550000001', 'seller');
        $buyer = $this->register('0550000002', 'buyer');

        $this->actingAs($a)->postJson("/staff/visits/{$seller->id}/take");
        $this->actingAs($b)->postJson("/staff/visits/{$buyer->id}/take");
        $this->actingAs($a)->postJson("/staff/visits/{$seller->id}/link", ['other_id' => $buyer->id])
            ->assertOk()->assertJsonPath('message', 'رُبط M001 بالصفقة');

        $this->assertSame($b->id, $buyer->fresh()->employee_id);
        $this->assertNotNull($buyer->fresh()->deal_id);
    }

    public function test_cannot_link_into_another_deal_and_unlink_removes_empty_deal(): void
    {
        $employee = $this->employee(1);
        $s1 = $this->register('0550000001', 'seller');
        $b1 = $this->register('0550000002', 'buyer');
        $this->actingAs($employee)->postJson("/staff/visits/{$s1->id}/take");
        $this->actingAs($employee)->postJson("/staff/visits/{$s1->id}/link", ['other_id' => $b1->id])->assertOk();
        $this->actingAs($employee)->postJson("/staff/visits/{$s1->id}/link", ['other_id' => $b1->id])->assertStatus(409);

        $this->actingAs($employee)->postJson("/staff/visits/{$b1->id}/unlink")->assertOk();
        $this->assertNull($b1->fresh()->deal_id);
        $this->assertNull($s1->fresh()->deal_id);
        $this->assertSame(0, Deal::count());
    }

    public function test_agent_propagates_to_the_partner_without_one(): void
    {
        $employee = $this->employee(1);
        $seller = $this->register('0550000001', 'seller');
        $buyer = $this->register('0550000002', 'buyer', 'none');

        $this->actingAs($employee)->postJson("/staff/visits/{$seller->id}/take");
        $this->actingAs($employee)->postJson("/staff/visits/{$seller->id}/link", ['other_id' => $buyer->id])->assertOk();
        $this->assertSame($this->agent->id, $buyer->fresh()->agent_id);
    }

    public function test_remote_customer_with_other_type_from_admin_list(): void
    {
        $type = ClientType::create(['name' => 'استشارة قانونية']);
        $employee = $this->employee();

        $this->actingAs($employee)->postJson('/staff/remote', [
            'mobile' => '0557770000', 'name' => 'عميل استشارة', 'customer_type' => 'other', 'source' => 'call',
        ])->assertUnprocessable()->assertJsonValidationErrors('client_type_id');

        $this->actingAs($employee)->postJson('/staff/remote', [
            'mobile' => '0557770000', 'name' => 'عميل استشارة', 'customer_type' => 'other', 'client_type_id' => $type->id, 'source' => 'call',
        ])->assertCreated();

        $visit = Visit::sole();
        $this->assertSame('other', $visit->customer_type->value);
        $this->assertSame('استشارة قانونية', $visit->typeLabel());
        $this->actingAs($employee)->get('/staff/customers/'.$visit->customer_id)->assertSee('استشارة قانونية');
    }

    public function test_remote_customer_with_agent_must_match_bank(): void
    {
        $employee = $this->employee();
        $other = Bank::where('name', 'البنك الأهلي السعودي')->first();

        $this->actingAs($employee)->postJson('/staff/remote', [
            'mobile' => '0557770001', 'name' => 'بائع', 'customer_type' => 'seller', 'source' => 'whatsapp',
            'bank_id' => $other->id, 'agent_id' => $this->agent->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('agent_id');

        $this->actingAs($employee)->postJson('/staff/remote', [
            'mobile' => '0557770001', 'name' => 'بائع', 'customer_type' => 'seller', 'source' => 'whatsapp',
            'bank_id' => $this->agent->bank_id, 'agent_id' => $this->agent->id,
        ])->assertCreated();
        $this->assertSame($this->agent->id, Visit::sole()->agent_id);
    }

    public function test_admin_manages_agents_and_client_types_and_reports_show_them(): void
    {
        $admin = $this->employee(1, Role::Admin);
        $bank = Bank::first();

        $this->actingAs($admin)->post('/admin/agents', ['bank_id' => $bank->id, 'name' => 'ناصر', 'mobile' => '0551112233'])->assertRedirect();
        $this->actingAs($admin)->post('/admin/agents', ['bank_id' => $bank->id, 'name' => 'ناصر'])->assertSessionHasErrors('name');
        $this->assertDatabaseHas('bank_agents', ['name' => 'ناصر', 'mobile' => '966551112233']);

        $this->actingAs($admin)->post('/admin/client-types', ['name' => 'مستثمر'])->assertRedirect();
        $this->assertDatabaseHas('client_types', ['name' => 'مستثمر']);

        foreach (['/admin/agents', '/admin/client-types', '/admin/reports'] as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }

        $this->register('0550000001', 'seller');
        $this->actingAs($admin)->get('/admin/reports?preset=today')->assertSee('خالد المندوب');
        $csv = $this->actingAs($admin)->get('/admin/export/visits?format=csv')->streamedContent();
        $this->assertStringContainsString('خالد المندوب', $csv);

        $this->actingAs($this->employee(2))->get('/admin/agents')->assertForbidden();
    }

    public function test_employee_can_correct_the_agent_of_the_current_customer(): void
    {
        $employee = $this->employee(1);
        $second = BankAgent::create(['bank_id' => $this->agent->bank_id, 'name' => 'عمر']);
        $foreign = BankAgent::create(['bank_id' => Bank::where('name', 'البنك الأهلي السعودي')->value('id'), 'name' => 'غريب']);
        $visit = $this->register('0550000001', 'seller');

        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/agent", ['agent_id' => $second->id])->assertForbidden();
        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/take")->assertOk();
        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/agent", ['agent_id' => $foreign->id])->assertUnprocessable();
        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/agent", ['agent_id' => $second->id])->assertOk();
        $this->assertSame($second->id, $visit->fresh()->agent_id);
        $this->actingAs($employee)->postJson("/staff/visits/{$visit->id}/agent", ['agent_id' => null])->assertOk();
        $this->assertNull($visit->fresh()->agent_id);
    }
}
