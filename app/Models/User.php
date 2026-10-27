<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\VisitStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'role', 'desk_id', 'current_desk_id', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function desk(): BelongsTo
    {
        return $this->belongsTo(Desk::class);
    }

    public function currentDesk(): BelongsTo
    {
        return $this->belongsTo(Desk::class, 'current_desk_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class, 'employee_id');
    }

    /** المكتب الفعلي اليوم: المختار عند بداية الدوام وإلا المكتب الافتراضي */
    public function workingDesk(): ?Desk
    {
        return $this->currentDesk ?? $this->desk;
    }

    public function activeVisit(): ?Visit
    {
        // العميل الأساسي = أول من استُدعي (إذا كان مع الموظف أطراف صفقة واحدة)
        return $this->visits()->whereIn('status', VisitStatus::active())->oldest('called_at')->oldest('id')->first();
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isManager(): bool
    {
        return in_array($this->role, [Role::Admin, Role::Supervisor], true);
    }
}
