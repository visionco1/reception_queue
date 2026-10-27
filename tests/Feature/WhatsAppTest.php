<?php

namespace Tests\Feature;

use App\Models\Visit;
use App\Models\WhatsappLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** تكامل واتساب: الإرسال عبر Meta، معالجة الفشل دون تعطيل النظام، وتحديثات الحالة */
class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBasics();
        config([
            'qms.whatsapp.driver' => 'meta',
            'qms.whatsapp.meta.phone_number_id' => '123456',
            'qms.whatsapp.meta.access_token' => 'token',
            'qms.whatsapp.meta.app_secret' => 'app-secret',
        ]);
    }

    public function test_sends_text_message_through_meta_cloud_api(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);

        $this->registerAtKiosk()->assertCreated();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/123456/messages')
            && $r['to'] === '966551234567'
            && $r['type'] === 'text'
            && str_contains($r['text']['body'], 'M001'));

        $log = WhatsappLog::sole();
        $this->assertSame('sent', $log->status);
        $this->assertSame('wamid.ABC', $log->provider_message_id);
        $this->assertNotNull($log->sent_at);
    }

    public function test_uses_approved_template_with_ordered_parameters(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.T']]])]);
        app(\App\Support\Settings::class)->set(['wa_registered_template' => 'queue_registered', 'wa_registered_params' => 'name,ticket']);

        $this->registerAtKiosk();

        Http::assertSent(fn ($r) => $r['type'] === 'template'
            && $r['template']['name'] === 'queue_registered'
            && $r['template']['language']['code'] === 'ar'
            && $r['template']['components'][0]['parameters'][0]['text'] === 'محمد أحمد'
            && $r['template']['components'][0]['parameters'][1]['text'] === 'M001');
    }

    public function test_provider_failure_never_blocks_registration(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid parameter', 'code' => 100]], 400)]);

        $this->registerAtKiosk()->assertCreated()->assertJsonPath('ticket_no', 'M001');

        $log = WhatsappLog::sole();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Invalid parameter', $log->error);
        $this->assertSame('waiting', Visit::sole()->status->value);
    }

    public function test_network_error_is_retried_then_can_be_resent_manually(): void
    {
        $down = true;
        Http::fake(['graph.facebook.com/*' => function () use (&$down) {
            if ($down) {
                throw new \Illuminate\Http\Client\ConnectionException('timeout');
            }

            return Http::response(['messages' => [['id' => 'wamid.R']]]);
        }]);
        $this->registerAtKiosk()->assertCreated();
        $log = WhatsappLog::sole();
        $this->assertSame('queued', $log->status); // سيُعاد تلقائيًا
        $this->assertSame(1, $log->attempts);

        $log->update(['status' => 'failed']);
        $down = false;
        $admin = $this->employee(1, \App\Enums\Role::Admin);
        $this->actingAs($admin)->post("/admin/whatsapp/{$log->id}/retry")->assertRedirect();
        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_disabled_messages_are_not_sent(): void
    {
        Http::fake();
        app(\App\Support\Settings::class)->set(['wa_registered_enabled' => false]);
        $this->registerAtKiosk()->assertCreated()->assertJsonPath('whatsapp', false);
        $this->assertSame(0, WhatsappLog::count());
        Http::assertNothingSent();
    }

    public function test_webhook_updates_delivery_status_with_valid_signature_only(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.D']]])]);
        $this->registerAtKiosk();

        $payload = json_encode(['entry' => [['changes' => [['value' => ['statuses' => [
            ['id' => 'wamid.D', 'status' => 'delivered', 'timestamp' => (string) now()->timestamp],
            ['id' => 'wamid.D', 'status' => 'read', 'timestamp' => (string) now()->timestamp],
        ]]]]]]]);

        $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'], $payload)->assertForbidden();

        $sig = 'sha256='.hash_hmac('sha256', $payload, 'app-secret');
        $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $sig], $payload)->assertOk();

        $log = WhatsappLog::sole();
        $this->assertSame('read', $log->status);
        $this->assertNotNull($log->delivered_at);
        $this->assertNotNull($log->read_at);
    }
}
