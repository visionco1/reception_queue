<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisplayCall extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['visit_id', 'ticket_no', 'desk_label', 'employee_name', 'kind', 'batch'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
