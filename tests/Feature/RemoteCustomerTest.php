<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\QueueTicket;
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

    public function test_remote_customer_is_saved_without_ticket_or_queue_messages(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)->postJson('/staff/remote', [
            'mobile' => '0559876543', 'name' => 'سارة محمد', 'customer_type' => 'seller',
            'bank_id' => '', 'source' => 'whatsapp', 'notes' => 'استفسار عن الإفراغ',
        ])->assertCreated()->assertJsonPath('existed', false);

        $visit = Visit::sole();
        $this->assertSame('remote', $visit->channel->value);
        $this->assertSame('whatsapp', $visit->source->value);
        $this->assertSame($employee->id, $visit->employee_id);
        $this->assertSame(0, QueueTicket::count());
        $this->assertSame(0, WhatsappLog::count());

        // لا يظهر في قائمة الانتظار
        $this->actingAs($employee)->getJson('/staff/state')->assertJsonCount(0, 'waiting');
    }

    public function test_lookup_warns_about_existing_customer_and_new_interaction_does_not_duplicate(): void
    {
        $employee = $this->employee();
        $this->registerAtKiosk(['mobile' => '0551112222', 'name' => 'خالد العتيبي']);

        $this->actingAs($employee)->getJson('/staff/lookup?mobile=٠٥٥١١١٢٢٢٢')
            ->assertOk()
            ->assertJsonPath('customer.name', 'خالد العتيبي')
            ->assertJsonPath('customer.visits_count', 1);

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
        $this->actingAs($employee)->get('/staff/customers/'.Customer::first()->id)->assertOk()->assertSee('M001');
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
