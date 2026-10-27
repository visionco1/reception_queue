<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'mobile', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['last_visit_at' => 'datetime'];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function displayMobile(): string
    {
        return Phone::format($this->mobile);
    }

    public function firstName(): string
    {
        return (string) str($this->name)->trim()->explode(' ')->first();
    }

    /** اسم مختصر لقوائم الانتظار: "محمد أ." */
    public function shortName(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name)) ?: [];
        if (count($parts) < 2) {
            return $this->name;
        }

        return $parts[0].' '.mb_substr($parts[1], 0, 1).'.';
    }

    public function refreshVisitStats(): void
    {
        $this->forceFill([
            'visits_count' => $this->visits()->count(),
            'last_visit_at' => $this->visits()->max('created_at'),
        ])->saveQuietly();
    }
}
