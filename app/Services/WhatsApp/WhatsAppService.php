<?php

namespace App\Services\WhatsApp;

use App\Enums\MessageKey;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Visit;
use App\Models\WhatsappLog;
use App\Support\Phone;

/**
 * يجهز رسائل رحلة العميل الحضوري ويضعها في طابور الإرسال.
 * فشل الإرسال لا يعطل النظام: كل رسالة تُحفظ في whatsapp_logs بحالتها ووقتها
 * وتعاد محاولتها تلقائيًا، ويمكن إعادة إرسالها يدويًا من لوحة الإدارة.
 */
class WhatsAppService
{
    public const PLACEHOLDERS = ['name', 'first_name', 'ticket', 'desk', 'ahead', 'company'];

    public function driver(): WhatsAppDriver
    {
        return match (config('qms.whatsapp.driver')) {
            'meta' => new MetaCloudDriver(config('qms.whatsapp.meta')),
            default => new LogDriver,
        };
    }

    public function isEnabled(): bool
    {
        return (bool) setting('whatsapp_enabled', true);
    }

    public function messageEnabled(MessageKey $key): bool
    {
        return $this->isEnabled() && (bool) setting("wa_{$key->value}_enabled", false);
    }

    /** إرسال رسالة من رحلة العميل الحضوري (لا تُرسل رسائل الطابور للعميل عن بعد) */
    public function notify(Visit $visit, MessageKey $key, array $extra = []): ?WhatsappLog
    {
        if (! $visit->isInPerson() || ! $this->messageEnabled($key)) {
            return null;
        }

        $visit->loadMissing('customer', 'ticket', 'desk');
        $vars = $this->variables($visit, $extra);

        return $this->queue(
            key: $key,
            phone: $visit->customer->mobile,
            body: $this->render($key, $vars),
            template: trim((string) setting("wa_{$key->value}_template", '')) ?: null,
            params: $this->templateParams($key, $vars),
            visitId: $visit->id,
            customerId: $visit->customer_id,
        );
    }

    public function sendTest(string $phone, string $body): WhatsappLog
    {
        return $this->queue(MessageKey::Test, $phone, $body, null, [], null, null);
    }

    /** إعادة إرسال رسالة فشلت */
    public function retry(WhatsappLog $log): void
    {
        $log->update(['status' => 'queued', 'error' => null, 'attempts' => 0, 'failed_at' => null]);
        SendWhatsAppMessage::dispatch($log->id)->afterCommit();
    }

    public function variables(Visit $visit, array $extra = []): array
    {
        return array_merge([
            'name' => $visit->customer->name,
            'first_name' => $visit->customer->firstName(),
            'ticket' => $visit->ticket?->ticket_no ?? '',
            'desk' => $visit->ticket?->desk_label ?? $visit->desk?->number ?? '',
            'ahead' => '',
            'company' => (string) setting('company_name'),
        ], $extra);
    }

    public function render(MessageKey $key, array $vars): string
    {
        $text = (string) setting("wa_{$key->value}_text", '');

        if ($key === MessageKey::Thanks && setting('wa_services_line_enabled')) {
            $text = rtrim($text)."\n".setting('wa_services_line_text');
        }

        return $this->fill($text, $vars);
    }

    public function fill(string $text, array $vars): string
    {
        $replace = [];
        foreach ($vars as $k => $v) {
            $replace['{'.$k.'}'] = (string) $v;
        }

        return trim(strtr($text, $replace));
    }

    /** قيم معاملات قالب Meta بالترتيب المحدد في الإعدادات (مثل name,ticket) */
    public function templateParams(MessageKey $key, array $vars): array
    {
        $names = array_filter(array_map('trim', explode(',', (string) setting("wa_{$key->value}_params", ''))));

        return array_values(array_map(fn ($n) => (string) ($vars[$n] ?? ''), $names));
    }

    private function queue(MessageKey $key, string $phone, string $body, ?string $template, array $params, ?int $visitId, ?int $customerId): WhatsappLog
    {
        $log = WhatsappLog::create([
            'visit_id' => $visitId,
            'customer_id' => $customerId,
            'message_key' => $key,
            'to_phone' => Phone::normalize($phone) ?? $phone,
            'body' => $body,
            'provider' => $this->driver()->name(),
            'template_name' => $template,
            'template_params' => $template ? $params : null,
            'status' => 'queued',
        ]);

        SendWhatsAppMessage::dispatch($log->id)->afterCommit();

        return $log;
    }
}
