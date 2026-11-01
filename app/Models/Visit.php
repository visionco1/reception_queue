<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\ClientSource;
use App\Enums\CustomerType;
use App\Enums\Outcome;
use App\Enums\ViaDetail;
use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'channel', 'customer_type', 'client_type_id', 'bank_id', 'via_detail', 'agent_id', 'deal_id', 'source', 'status',
        'outcome', 'track_token', 'employee_id', 'desk_id', 'assigned_desk_id', 'created_by', 'is_returning', 'notes', 'wait_seconds',
        'service_seconds', 'called_at', 'service_started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'customer_type' => CustomerType::class,
            'source' => ClientSource::class,
            'status' => VisitStatus::class,
            'outcome' => Outcome::class,
            'via_detail' => ViaDetail::class,
            'is_returning' => 'boolean',
            'called_at' => 'datetime',
            'service_started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // رابط متابعة الدور الذي يُرسل للعميل على واتساب
        static::creating(function (Visit $visit) {
            $visit->track_token ??= Str::random(24);
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(BankAgent::class, 'agent_id');
    }

    public function clientType(): BelongsTo
    {
        return $this->belongsTo(ClientType::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /** الأطراف الأخرى في نفس الصفقة */
    public function partners()
    {
        if (! $this->deal_id) {
            return collect();
        }

        return Visit::query()->where('deal_id', $this->deal_id)->whereKeyNot($this->id)->with('customer', 'ticket', 'employee', 'desk')->orderBy('id')->get();
    }

    /** الجهة كما تُعرض: «بنك · الأهلي» أو «أخرى · شركة عقارية» أو «أخرى» */
    public function viaLabel(): string
    {
        if ($this->bank_id) {
            return __('بنك').' · '.($this->bank?->name ?? '');
        }

        return $this->via_detail ? __('أخرى').' · '.$this->via_detail->label() : __('أخرى');
    }

    public function trackUrl(): string
    {
        return route('track.show', $this->track_token);
    }

    /** اسم النوع كما يُعرض: مشتري / بائع / اسم النوع الآخر */
    public function typeLabel(): string
    {
        if ($this->customer_type === CustomerType::Other) {
            return $this->clientType?->name ?? CustomerType::Other->label();
        }

        return $this->customer_type->label();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function desk(): BelongsTo
    {
        return $this->belongsTo(Desk::class);
    }

    /** المكتب الذي حُوِّل إليه العميل وهو في الانتظار */
    public function assignedDesk(): BelongsTo
    {
        return $this->belongsTo(Desk::class, 'assigned_desk_id');
    }

    public function ticket(): HasOne
    {
        return $this->hasOne(QueueTicket::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ServiceSession::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(VisitStatusLog::class)->orderBy('id');
    }

    public function whatsappLogs(): HasMany
    {
        return $this->hasMany(WhatsappLog::class)->orderBy('id');
    }

    public function scopeToday(Builder $q): Builder
    {
        return $q->whereBetween('visits.created_at', [today(), today()->endOfDay()]);
    }

    public function scopeWaiting(Builder $q): Builder
    {
        return $q->where('status', VisitStatus::Waiting);
    }

    public function isInPerson(): bool
    {
        return $this->channel === Channel::InPerson;
    }

    public function ticketNo(): ?string
    {
        return $this->ticket?->ticket_no;
    }
}
