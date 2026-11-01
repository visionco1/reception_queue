<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Visit;
use App\Models\WhatsappLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** معياري القبول 6 و 7: العميل عن بعد والبحث برقم الجوال دون تكرار */
class RemoteCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBasics();
    }

    public function test_remote_customer_joins_the_waiting_list_with_a_number(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)->postJson('/staff/remote', [
            'mobile' => '0559876543', 'name' => 'سارة محمد', 'customer_type' => 'seller',
            'bank_id' => '', 'via_detail' => 'individuals', 'source' => 'whatsapp', 'notes' => 'استفسار عن الإفراغ',
        ])->assertCreated()->assertJsonPath('existed', false)->assertJsonPath('ticket_no', 'S-001');

        $visit = Visit::with('ticket')->sole();
        $this->assertSame('remote', $visit->channel->value);
        $this->assertSame('waiting', $visit->status->value);
        $this->assertSame('whatsapp', $visit->source->value);
        $this->assertSame($employee->id, $visit->created_by);
        $this->assertSame('أخرى · أفراد', $visit->viaLabel());
        $this->assertSame('registered', WhatsappLog::sole()->message_key->value);

        // يظهر في قائمة الانتظار مع شارة «عن بُعد»
        $this->actingAs($employee)->getJson('/staff/state')
            ->assertJsonCount(1, 'waiting')
            ->assertJsonPath('waiting.0.ticket_no', 'S-001')
            ->assertJsonPath('waiting.0.remote', true);

        // لا يُضاف مرتين ما دام دوره قائمًا
        $this->actingAs($employee)->postJson('/staff/remote', [
            'mobile' => '0559876543', 'name' => 'سارة محمد', 'customer_type' => 'seller', 'source' => 'whatsapp',
        ])->assertStatus(409)->assertJsonPath('message', 'العميل في قائمة الانتظار بالفعل برقم S-001');
    }

    public function test_other_client_types_get_their_own_prefix(): void
    {
        $employee = $this->employee();
        $type = \App\Models\ClientType::create(['name' => 'استشارة قانونية']);

        $this->actingAs($employee)->postJson('/staff/remote', [
            'mobile' => '0557770000', 'name' => 'عميل استشارة', 'customer_type' => 'other', 'client_type_id' => $type->id, 'source' => 'call',
        ])->assertCreated()->assertJsonPath('ticket_no', 'G-001');
    }

    public function test_lookup_warns_about_existing_customer_and_new_interaction_does_not_duplicate(): void
    {
        $employee = $this->employee();
        $this->registerAtKiosk(['mobile' => '0551112222', 'name' => 'خالد العتيبي']);

        $this->actingAs($employee)->getJson('/staff/lookup?mobile=٠٥٥١١١٢٢٢٢')
            ->assertOk()
            ->assertJsonPath('customer.name', 'خالد العتيبي')
            ->assertJsonPath('customer.visits_count', 1);

        Visit::query()->update(['status' => 'completed']);
        $this->actingAs($employee)->postJson('/staff/remote', [
            'mobile' => '+966551112222', 'name' => 'خالد العتيبي', 'customer_type' => 'buyer', 'source' => 'call',
        ])->assertCreated()->assertJsonPath('existed', true);

        $this->assertSame(1, Customer::count());
        $this->assertSame(2, Customer::first()->visits_count);
    }

    public function test_search_by_mobile_finds_previous_customer(): void
    {
        $employee = $this->employee();
        $this->registerAtKiosk(['mobile' => '0553334444', 'name' => 'نورة القحطاني']);

        $this->actingAs($employee)->get('/staff/customers?q=0553334444')->assertOk()->assertSee('نورة القحطاني');
        $this->actingAs($employee)->get('/staff/customers?q=3334444')->assertOk()->assertSee('نورة القحطاني');
        $this->actingAs($employee)->get('/staff/customers/'.Customer::first()->id)->assertOk()->assertSee('M-001');
    }

    public function test_search_scope_can_be_limited_to_own_customers(): void
    {
        app(\App\Support\Settings::class)->set(['employee_search_scope' => 'own']);
        $a = $this->employee(1);
        $b = $this->employee(2);
        $this->actingAs($a)->postJson('/staff/remote', ['mobile' => '0557778888', 'name' => 'عميل أ', 'customer_type' => 'buyer', 'source' => 'call']);

        $customer = Customer::sole();
        $this->actingAs($a)->get("/staff/customers/{$customer->id}")->assertOk();
        $this->actingAs($b)->get("/staff/customers/{$customer->id}")->assertForbidden();
    }

    public function test_editing_customer_mobile_cannot_collide_with_another_customer(): void
    {
        $employee = $this->employee();
        $this->registerAtKiosk(['mobile' => '0551000001', 'name' => 'أول']);
        Visit::query()->update(['status' => 'completed']);
        $this->registerAtKiosk(['mobile' => '0551000002', 'name' => 'ثاني']);
        $second = Customer::where('name', 'ثاني')->sole();

        $this->actingAs($employee)->put("/staff/customers/{$second->id}", ['name' => 'ثاني', 'mobile' => '0551000001'])
            ->assertSessionHasErrors('mobile');
        $this->actingAs($employee)->put("/staff/customers/{$second->id}", ['name' => 'ثاني معدل', 'mobile' => '0551000002'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.updated', 'subject_id' => $second->id]);
    }
}
