<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\QueueTicket;
use App\Models\Visit;
use App\Models\WhatsappLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** معيار القبول 1 و 5: التسجيل من الآيباد ورقم صحيح ورسالة واتساب */
class KioskRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBasics();
    }

    public function test_kiosk_page_requires_the_secret_link(): void
    {
        $this->get('/kiosk/kiosk-secret')->assertOk()->assertSee('تسجيل وأخذ رقم');
        $this->get('/kiosk/wrong')->assertNotFound();
        $this->postJson('/kiosk/wrong/register', [])->assertNotFound();
    }

    public function test_walk_in_customer_gets_a_ticket_and_whatsapp_message(): void
    {
        $this->registerAtKiosk()
            ->assertCreated()
            ->assertJson(['ticket_no' => 'M001', 'type' => 'buyer', 'ahead' => 0, 'existing' => false, 'phone_masked' => '05• ••• 4567']);

        $visit = Visit::with('ticket', 'customer')->sole();
        $this->assertSame('waiting', $visit->status->value);
        $this->assertSame('in_person', $visit->channel->value);
        $this->assertSame('966551234567', $visit->customer->mobile);
        $this->assertNotNull($visit->ticket->issued_at);

        $log = WhatsappLog::sole();
        $this->assertSame('registered', $log->message_key->value);
        $this->assertSame('sent', $log->status);
        $this->assertStringContainsString('M001', $log->body);
        $this->assertStringContainsString('محمد أحمد', $log->body);
    }

    public function test_numbering_is_sequential_per_type_and_resets_daily(): void
    {
        $this->registerAtKiosk(['mobile' => '0550000001'])->assertJson(['ticket_no' => 'M001']);
        $this->registerAtKiosk(['mobile' => '0550000002'])->assertJson(['ticket_no' => 'M002', 'ahead' => 1]);
        $this->registerAtKiosk(['mobile' => '0550000003', 'customer_type' => 'seller'])->assertJson(['ticket_no' => 'B001', 'ahead' => 2]);

        $this->travel(1)->days();
        $this->registerAtKiosk(['mobile' => '0550000004'])->assertJson(['ticket_no' => 'M001']);
        $this->assertSame(4, QueueTicket::count());
    }

    public function test_prefixes_are_configurable(): void
    {
        app(\App\Support\Settings::class)->set(['ticket_prefix_buyer' => 'P', 'ticket_digits' => 4]);
        $this->registerAtKiosk()->assertJson(['ticket_no' => 'P0001']);
    }

    public function test_same_mobile_twice_today_returns_the_existing_ticket(): void
    {
        $this->registerAtKiosk()->assertCreated();
        $this->registerAtKiosk(['mobile' => '+966 55 123 4567'])->assertOk()->assertJson(['ticket_no' => 'M001', 'existing' => true]);

        $this->assertSame(1, Customer::count());
        $this->assertSame(1, Visit::count());
        $this->assertSame(1, WhatsappLog::count());
    }

    public function test_returning_customer_reuses_record_and_is_flagged(): void
    {
        $this->registerAtKiosk();
        Visit::query()->update(['status' => 'completed']);

        $this->registerAtKiosk()->assertCreated()->assertJson(['ticket_no' => 'M002']);

        $this->assertSame(1, Customer::count());
        $this->assertSame(2, Customer::first()->visits_count);
        $this->assertTrue(Visit::latest('id')->first()->is_returning);
    }

    public function test_validation_messages_are_arabic(): void
    {
        $this->registerAtKiosk(['name' => '', 'mobile' => '123', 'customer_type' => '', 'bank_id' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'mobile', 'customer_type', 'bank_id'])
            ->assertJsonPath('errors.mobile.0', 'رقم الجوال غير صحيح. اكتبه بالصيغة 05XXXXXXXX');
    }

    public function test_customer_without_bank_is_accepted(): void
    {
        $this->registerAtKiosk(['bank_id' => 'none'])->assertCreated();
        $this->assertNull(Visit::sole()->bank_id);
    }
}
