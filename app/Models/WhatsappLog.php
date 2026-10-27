<?php

namespace App\Models;

use App\Enums\MessageKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappLog extends Model
{
    protected $fillable = [
        'visit_id', 'customer_id', 'message_key', 'to_phone', 'body', 'provider', 'template_name',
        'template_params', 'status', 'provider_message_id', 'error', 'attempts', 'sent_at',
        'delivered_at', 'read_at', 'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'message_key' => MessageKey::class,
            'template_params' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public const STATUSES = ['queued', 'sent', 'delivered', 'read', 'failed', 'skipped'];

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'queued' => __('بانتظار الإرسال'),
            'sent' => __('أُرسلت'),
            'delivered' => __('وصلت'),
            'read' => __('قُرئت'),
            'failed' => __('فشل الإرسال'),
            'skipped' => __('لم تُرسل'),
            default => $status,
        };
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
