<?php

namespace App\Services;

use App\Enums\ClientSource;
use App\Enums\CustomerType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * قاعدة بيانات العملاء الموحدة: البحث برقم الجوال، منع التكرار،
 * وإضافة العملاء عن بُعد إلى قائمة الانتظار برقم.
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
     * إضافة عميل عن بُعد إلى قائمة الانتظار برقم (أو عميل موجود برقم جديد اليوم).
     * يُنسب للموظف الذي أضافه، وتصله رسالة التسجيل ورابط متابعة الدور.
     *
     * @return array{0: Visit, 1: bool} الزيارة، وهل كان العميل مسجلًا مسبقًا
     */
    public function addRemote(array $data, User $employee): array
    {
        [$customer, $existed] = DB::transaction(function () use ($data, $employee) {
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

            return [$customer, $existed];
        });

        $type = CustomerType::from($data['customer_type']);
        $bankId = $type->isParty() ? ($data['bank_id'] ?? null) : null;

        $visit = $this->queue->enqueueRemote($customer, [
            'customer_type' => $type,
            'client_type_id' => $type === CustomerType::Other ? ($data['client_type_id'] ?? null) : null,
            'bank_id' => $bankId,
            'via_detail' => $bankId || ! $type->isParty() ? null : ($data['via_detail'] ?? null),
            'agent_id' => $bankId ? ($data['agent_id'] ?? null) : null,
            'source' => ClientSource::from($data['source']),
            'notes' => $data['notes'] ?? null,
        ], $employee);

        return [$visit, $existed];
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
