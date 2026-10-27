<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MessageKey;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WhatsappLog;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;

class WhatsAppLogController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $key = $request->query('key');

        $logs = WhatsappLog::query()
            ->with('customer', 'visit.ticket')
            ->when(in_array($status, WhatsappLog::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when(MessageKey::tryFrom((string) $key), fn ($q) => $q->where('message_key', $key))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $counts = WhatsappLog::query()
            ->where('created_at', '>=', today()->subDays(6))
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return view('admin.whatsapp', compact('logs', 'status', 'key', 'counts'));
    }

    public function retry(WhatsappLog $log, WhatsAppService $whatsapp)
    {
        abort_unless($log->status === 'failed', 422);
        $whatsapp->retry($log);
        AuditLog::record('whatsapp.retry', $log, __('إعادة إرسال رسالة واتساب إلى :phone', ['phone' => $log->to_phone]));

        return back()->with('status', __('أُعيدت الرسالة إلى طابور الإرسال'));
    }
}
