<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Business Platform (Cloud API) من Meta.
 * - إذا حُدد اسم قالب معتمد للرسالة يُرسل كقالب (مطلوب للرسائل التي يبدأها النشاط التجاري).
 * - بدون قالب تُرسل كنص عادي (يعمل فقط داخل نافذة 24 ساعة من آخر رسالة للعميل).
 */
class MetaCloudDriver implements WhatsAppDriver
{
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'meta';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['phone_number_id']) && ! empty($this->config['access_token']);
    }

    public function send(WhatsappLog $log): ?string
    {
        if (! $this->isConfigured()) {
            throw new WhatsAppException('بيانات ربط واتساب غير مكتملة في ملف الإعدادات (.env).', true);
        }

        $url = sprintf('https://graph.facebook.com/%s/%s/messages', $this->config['api_version'], $this->config['phone_number_id']);

        try {
            $response = Http::withToken($this->config['access_token'])
                ->timeout($this->config['timeout'] ?? 15)
                ->acceptJson()
                ->post($url, $this->payload($log));
        } catch (ConnectionException $e) {
            throw new WhatsAppException('تعذر الاتصال بخدمة واتساب: '.$e->getMessage());
        }

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->body();
            $code = (int) $response->json('error.code');
            // 131026 رقم غير مسجل في واتساب، 132001 قالب غير موجود، 100 معاملات خاطئة
            $permanent = $response->status() === 400 || in_array($code, [100, 131026, 132000, 132001, 132005, 132007], true);

            throw new WhatsAppException(trim("[{$response->status()}] {$error}"), $permanent);
        }

        return $response->json('messages.0.id');
    }

    public function payload(WhatsappLog $log): array
    {
        $base = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $log->to_phone];

        if ($log->template_name) {
            $parameters = collect($log->template_params ?? [])
                ->map(fn ($v) => ['type' => 'text', 'text' => (string) $v])
                ->values()
                ->all();

            $template = [
                'name' => $log->template_name,
                'language' => ['code' => setting('whatsapp_template_lang', 'ar')],
            ];
            if ($parameters) {
                $template['components'] = [['type' => 'body', 'parameters' => $parameters]];
            }

            return $base + ['type' => 'template', 'template' => $template];
        }

        return $base + ['type' => 'text', 'text' => ['preview_url' => false, 'body' => $log->body]];
    }
}
