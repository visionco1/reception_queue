<?php

namespace App\Http\Controllers;

use App\Models\WhatsappLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * يستقبل تحديثات حالة الرسائل من WhatsApp Cloud API (وصلت، قُرئت، فشلت)
 * ويحدّث سجل الرسائل. يُضبط في لوحة Meta على: https://النطاق/webhooks/whatsapp
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $token = (string) config('qms.whatsapp.meta.webhook_verify_token');

        if ($token !== '' && $request->query('hub_mode') === 'subscribe'
            && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200);
        }

        abort(403);
    }

    public function handle(Request $request)
    {
        $secret = (string) config('qms.whatsapp.meta.app_secret');
        if ($secret === '') {
            abort(403);
        }

        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        if (! hash_equals($expected, $signature)) {
            abort(403);
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['statuses'] ?? []) as $status) {
                    $this->apply($status);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    private function apply(array $status): void
    {
        $log = WhatsappLog::query()->where('provider_message_id', $status['id'] ?? '')->first();
        if (! $log) {
            return;
        }

        $at = isset($status['timestamp']) ? Carbon::createFromTimestamp((int) $status['timestamp'])->setTimezone(config('app.timezone')) : now();
        $rank = ['queued' => 0, 'failed' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

        switch ($status['status'] ?? null) {
            case 'sent':
                $log->sent_at ??= $at;
                break;
            case 'delivered':
                $log->delivered_at = $at;
                break;
            case 'read':
                $log->read_at = $at;
                break;
            case 'failed':
                $log->failed_at = $at;
                $log->error = $status['errors'][0]['title'] ?? $status['errors'][0]['message'] ?? 'failed';
                break;
        }

        $new = $status['status'] ?? null;
        if ($new === 'failed' || (isset($rank[$new]) && $rank[$new] > ($rank[$log->status] ?? 0))) {
            $log->status = $new;
        }

        $log->save();
    }
}
