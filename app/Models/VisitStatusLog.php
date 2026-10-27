<?php

namespace App\Models;

use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitStatusLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['visit_id', 'from_status', 'to_status', 'user_id', 'note'];

    protected function casts(): array
    {
        return [
            'from_status' => VisitStatus::class,
            'to_status' => VisitStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
