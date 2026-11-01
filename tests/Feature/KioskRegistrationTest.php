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
            ->assertJson(['ticket_no' => 'M-001', 'type' => 'buyer', 'ahead' => 0, 'minutes' => 0, 'existing' => false, 'phone_masked' => '05X XXX 4567', 'via' => 'مصرف الراجحي']);

        $visit = Visit::with('ticket', 'customer')->sole();
        $this->assertSame('waiting', $visit->status->value);
        $this->assertSame('in_person', $visit->channel->value);
        $this->assertSame('966551234567', $visit->customer->mobile);
        $this->assertNotNull($visit->ticket->issued_at);

        $log = WhatsappLog::sole();
        $this->assertSame('registered', $log->message_key->value);
        $this->assertSame('sent', $log->status);
        // «أهلًا محمد، رقم انتظارك M-001 في ... أمامك 0. تابع دورك: [رابط]»
        $this->assertStringContainsString('أهلًا محمد،', $log->body);
        $this->assertStringContainsString('M-001', $log->body);
        $this->assertStringContainsString('أمامك 0', $log->body);
        $this->assertStringContainsString(route('track.show', $visit->track_token), $log->body);
    }

    public function test_numbering_is_sequential_per_type_and_resets_daily(): void
    {
        $this->registerAtKiosk(['mobile' => '0550000001'])->assertJson(['ticket_no' => 'M-001']);
        $this->registerAtKiosk(['mobile' => '0550000002'])->assertJson(['ticket_no' => 'M-002', 'ahead' => 1, 'minutes' => 15]);
        $this->registerAtKiosk(['mobile' => '0550000003', 'customer_type' => 'seller'])->assertJson(['ticket_no' => 'S-001', 'ahead' => 2, 'minutes' => 30]);

        $this->travel(1)->days();
        $this->registerAtKiosk(['mobile' => '0550000004'])->assertJson(['ticket_no' => 'M-001']);
        $this->assertSame(4, QueueTicket::count());
    }

    public function test_prefixes_are_configurable(): void
    {
        app(\App\Support\Settings::class)->set(['ticket_prefix_buyer' => 'P', 'ticket_digits' => 4]);
        $this->registerAtKiosk()->assertJson(['ticket_no' => 'P-0001']);
    }

    public function test_same_mobile_twice_today_returns_the_existing_ticket(): void
    {
        $this->registerAtKiosk()->assertCreated();
        $this->registerAtKiosk(['mobile' => '551234567'])->assertOk()->assertJson(['ticket_no' => 'M-001', 'existing' => true]);

        $this->assertSame(1, Customer::count());
        $this->assertSame(1, Visit::count());
        $this->assertSame(1, WhatsappLog::count());
    }

    public function test_returning_customer_reuses_record_and_is_flagged(): void
    {
        $this->registerAtKiosk();
        Visit::query()->update(['status' => 'completed']);

        $this->registerAtKiosk()->assertCreated()->assertJson(['ticket_no' => 'M-002']);

        $this->assertSame(1, Customer::count());
        $this->assertSame(2, Customer::first()->visits_count);
        $this->assertTrue(Visit::latest('id')->first()->is_returning);
    }

    public function test_validation_messages_are_arabic(): void
    {
        $this->registerAtKiosk(['name' => 'م', 'mobile' => '0451234567', 'customer_type' => '', 'bank_id' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'mobile', 'customer_type', 'bank_id'])
            ->assertJsonPath('errors.name.0', 'الاسم حرفين على الأقل')
            ->assertJsonPath('errors.mobile.0', 'رقم الجوال 9 أرقام يبدأ بـ 5');

        $this->registerAtKiosk(['via' => ''])->assertUnprocessable()->assertJsonValidationErrors('via');
    }

    public function test_other_with_optional_detail_needs_no_bank(): void
    {
        $this->registerAtKiosk(['via' => 'other', 'bank_id' => null, 'via_detail' => 'company'])
            ->assertCreated()
            ->assertJson(['via' => 'شركة عقارية']);

        $visit = Visit::sole();
        $this->assertNull($visit->bank_id);
        $this->assertSame('أخرى · شركة عقارية', $visit->viaLabel());
    }

    public function test_outside_working_hours_the_kiosk_does_not_register(): void
    {
        app(\App\Support\Settings::class)->set(['work_start' => '08:00', 'work_end' => '16:00']);
        $this->travelTo(today()->setTime(19, 0));

        $this->getJson('/kiosk/kiosk-secret/state')->assertJson(['open' => false]);
        $this->registerAtKiosk()->assertStatus(423);
        $this->assertSame(0, Visit::count());

        $this->travelTo(today()->setTime(9, 0));
        $this->registerAtKiosk()->assertCreated();
    }

    public function test_featured_banks_show_on_the_kiosk_and_others_behind_other_bank(): void
    {
        \App\Models\Bank::query()->where('name', 'مصرف الراجحي')->update(['is_featured' => true]);
        \App\Models\Bank::create(['name' => 'بنك البلاد', 'sort_order' => 3]);

        $this->get('/kiosk/kiosk-secret')->assertOk()
            ->assertSee('مصرف الراجحي')
            ->assertSee('بنك البلاد')  // في قائمة «بنك آخر»
            ->assertSee('English');
    }

    public function test_customer_without_bank_is_accepted(): void
    {
        $this->registerAtKiosk(['bank_id' => 'none'])->assertCreated();
        $this->assertNull(Visit::sole()->bank_id);
    }
}
