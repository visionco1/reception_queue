<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueTicket extends Model
{
    protected $fillable = [
        'visit_id', 'ticket_date', 'prefix', 'number', 'ticket_no', 'issued_at', 'called_at',
        'desk_label', 'recall_count', 'near_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'ticket_date' => 'date',
            'issued_at' => 'datetime',
            'called_at' => 'datetime',
            'near_notified_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
