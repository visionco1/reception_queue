<?php

namespace App\Http\Controllers\Track;

use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Services\QueueService;

/** صفحة متابعة الدور التي يفتحها العميل من رابط واتساب (بدون تسجيل دخول) */
class TrackController extends Controller
{
    public function show(string $token, QueueService $queue)
    {
        $visit = $this->find($token);

        return view('track.show', ['token' => $token, 'state' => $this->payload($visit, $queue)]);
    }

    public function state(string $token, QueueService $queue)
    {
        return response()->json($this->payload($this->find($token), $queue))->header('Cache-Control', 'no-store');
    }

    private function find(string $token): Visit
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{16,40}$/', $token), 404);

        return Visit::query()->where('track_token', $token)->with('ticket', 'customer', 'desk', 'bank')->firstOrFail();
    }

    private function payload(Visit $visit, QueueService $queue): array
    {
        $today = $visit->ticket?->ticket_date?->isToday() ?? false;
        $status = $visit->status;
        $ahead = $status === VisitStatus::Waiting ? $queue->ahead($visit) : 0;

        return [
            'ticket_no' => $visit->ticketNo(),
            'first_name' => $visit->customer->firstName(),
            'type_label' => $visit->customer_type->label(),
            'via' => $visit->bank?->name ?? ($visit->via_detail?->label() ?? __('أخرى')),
            'status' => $today || $status->isFinal() ? $status->value : 'expired',
            'ahead' => $ahead,
            'minutes' => $queue->estimateMinutes($ahead),
            'desk' => in_array($status->value, VisitStatus::active(), true) ? ($visit->ticket?->desk_label ?? $visit->desk?->number) : null,
            'outcome' => $visit->outcome?->label(),
            'date' => $visit->ticket?->ticket_date?->translatedFormat('j F Y'),
        ];
    }
}
