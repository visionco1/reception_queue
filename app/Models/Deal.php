<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** صفقة تربط البائع بالمشتري (وكيل البنك نفسه قد يتابع صفقات كثيرة) */
class Deal extends Model
{
    protected $fillable = ['bank_id', 'agent_id', 'outcome', 'created_by'];

    protected function casts(): array
    {
        return ['outcome' => \App\Enums\Outcome::class];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class)->orderBy('id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(BankAgent::class, 'agent_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
