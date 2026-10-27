<?php

namespace App\Services;

use App\Enums\Channel;
use App\Enums\ClientSource;
use App\Enums\CustomerType;
use App\Enums\VisitStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * قاعدة بيانات العملاء الموحدة: البحث برقم الجوال، منع التكرار،
 * وتسجيل العملاء عن بعد كتفاعل بدون رقم انتظار.
 */
class CustomerService
{
    public function __construct(private readonly QueueService $queue) {}

    public function findByMobile(string $mobile): ?Customer
    {
        return Customer::query()->where('mobile', $mobile)->first();
    }

    /** نطاق البحث المسموح للمستخدم (حسب إعداد صلاحيات بحث الموظفين) */
    public function scopeFor(User $user): Builder
    {
        $query = Customer::query();

        if (! $user->isManager() && setting('employee_search_scope') === 'own') {
            $query->whereHas('visits', fn ($q) => $q->where(fn ($w) => $w->where('employee_id', $user->id)->orWhere('created_by', $user->id)));
        }

        return $query;
    }

    public function canView(User $user, Customer $customer): bool
    {
        return $this->scopeFor($user)->whereKey($customer->id)->exists();
    }

    /**
     * إضافة عميل عن بعد (أو تفاعل جديد لعميل موجود). لا يُنشأ رقم انتظار
     * ولا تُرسل رسائل الطابور، ويُنسب التفاعل للموظف الذي أضافه.
     *
     * @return array{0: Visit, 1: bool} الزيارة، وهل كان العميل مسجلًا مسبقًا
     */
    public function addRemote(array $data, User $employee): array
    {
        return DB::transaction(function () use ($data, $employee) {
            $customer = Customer::query()->where('mobile', $data['mobile'])->lockForUpdate()->first();
            $existed = (bool) $customer;

            if (! $customer) {
                $customer = Customer::create([
                    'name' => $data['name'],
                    'mobile' => $data['mobile'],
                    'created_by' => $employee->id,
                ]);
            } elseif (! empty($data['name']) && $data['name'] !== $customer->name) {
                $old = $customer->name;
                $customer->update(['name' => $data['name']]);
                AuditLog::record('customer.updated', $customer, __('تحديث اسم العميل'), ['name' => [$old, $data['name']]]);
            }

            $now = now();
            $visit = $customer->visits()->create([
                'channel' => Channel::Remote,
                'customer_type' => CustomerType::from($data['customer_type']),
                'client_type_id' => $data['customer_type'] === CustomerType::Other->value ? ($data['client_type_id'] ?? null) : null,
                'bank_id' => $data['bank_id'] ?? null,
                'agent_id' => $data['agent_id'] ?? null,
                'source' => ClientSource::from($data['source']),
                'status' => VisitStatus::Completed,
                'employee_id' => $employee->id,
                'created_by' => $employee->id,
                'is_returning' => $existed && $customer->visits_count > 0,
                'notes' => $data['notes'] ?? null,
                'completed_at' => $now,
            ]);

            $this->queue->logStatus($visit, null, VisitStatus::Completed, $employee, __('تفاعل عن بعد'));
            $customer->refreshVisitStats();

            AuditLog::record('visit.remote_created', $visit, __(':user أضاف تفاعلًا عن بعد للعميل :name', [
                'user' => $employee->name, 'name' => $customer->name,
            ]));

            return [$visit, $existed];
        });
    }

    /** تعديل البيانات الأساسية للعميل مع تسجيل القيم القديمة والجديدة */
    public function update(Customer $customer, array $data): Customer
    {
        $before = $customer->only(array_keys($data));
        $customer->update($data);
        $changes = [];
        foreach ($customer->getChanges() as $key => $value) {
            if ($key !== 'updated_at') {
                $changes[$key] = [$before[$key] ?? null, $value];
            }
        }

        if ($changes) {
            AuditLog::record('customer.updated', $customer, __('تعديل بيانات العميل :name', ['name' => $customer->name]), $changes);
        }

        return $customer;
    }
}
