<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ClientSource;
use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Customer;
use App\Rules\Mobile;
use App\Services\CustomerService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    /** البحث عن عميل برقم الجوال أو الاسم */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $query = $this->customers->scopeFor($request->user());

        if ($q !== '') {
            $digits = preg_replace('/\D+/', '', Phone::toAsciiDigits($q));
            $query->where(function ($w) use ($q, $digits) {
                if (strlen($digits) >= 4) {
                    $normalized = Phone::normalize($digits);
                    $w->where('mobile', 'like', '%'.ltrim($digits, '0').'%');
                    if ($normalized) {
                        $w->orWhere('mobile', $normalized);
                    }
                } else {
                    $w->where('name', 'like', '%'.$q.'%');
                }
            });
        }

        $results = $q === '' && ! $request->user()->isManager()
            ? null
            : $query->latest('last_visit_at')->paginate(20)->withQueryString();

        // بحث برقم جوال كامل غير موجود: نقترح إضافته مباشرة
        $newMobile = $q !== '' && $results && $results->total() === 0 ? Phone::normalize($q) : null;

        return view('staff.customers.index', compact('q', 'results', 'newMobile') + [
            'banks' => Bank::query()->active()->ordered()->get(['id', 'name']),
            'sources' => ClientSource::options(),
            'types' => CustomerType::options(),
        ]);
    }

    public function show(Request $request, Customer $customer)
    {
        abort_unless($this->customers->canView($request->user(), $customer), 403, __('هذا العميل خارج نطاق صلاحيات البحث لديك.'));

        $customer->load(['visits' => fn ($q) => $q->latest('id')->with('ticket', 'bank', 'agent', 'clientType', 'employee', 'creator', 'deal.visits.customer', 'deal.visits.ticket')]);

        return view('staff.customers.show', [
            'customer' => $customer,
            'banks' => Bank::query()->active()->ordered()->get(['id', 'name']),
            'sources' => ClientSource::options(),
            'types' => CustomerType::options(),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        abort_unless($this->customers->canView($request->user(), $customer), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'mobile' => ['required', 'string', 'max:20', new Mobile],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['mobile'] = Phone::normalize($data['mobile']);

        if (Customer::query()->where('mobile', $data['mobile'])->whereKeyNot($customer->id)->exists()) {
            throw ValidationException::withMessages(['mobile' => __('هذا الرقم مسجل لعميل آخر.')]);
        }

        $this->customers->update($customer, $data);

        return back()->with('status', __('حُفظت بيانات العميل'));
    }

    /** فحص رقم الجوال قبل إضافة عميل عن بعد لمنع التكرار */
    public function lookup(Request $request)
    {
        $request->validate(['mobile' => ['required', 'string', 'max:20', new Mobile]]);
        $customer = $this->customers->findByMobile(Phone::normalize($request->query('mobile')));

        return response()->json([
            'mobile' => Phone::normalize($request->query('mobile')),
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'visits_count' => $customer->visits_count,
                'last_visit' => $customer->last_visit_at?->translatedFormat('j F Y'),
                'url' => $this->customers->canView($request->user(), $customer) ? route('staff.customers.show', $customer) : null,
            ] : null,
        ]);
    }

    /** إضافة عميل عن بعد: يُسجل في قاعدة البيانات بدون رقم انتظار */
    public function storeRemote(Request $request)
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'max:20', new Mobile],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'customer_type' => ['required', Rule::enum(CustomerType::class)],
            'client_type_id' => ['nullable', 'required_if:customer_type,other', Rule::exists('client_types', 'id')->where('is_active', true)],
            'bank_id' => ['nullable', Rule::exists('banks', 'id')],
            'agent_id' => ['nullable', Rule::exists('bank_agents', 'id')->where('bank_id', $request->input('bank_id') ?: 0)],
            'source' => ['required', Rule::enum(ClientSource::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'customer_type.required' => __('اختر نوع العميل'),
            'client_type_id.required_if' => __('اختر نوع العميل من القائمة'),
            'agent_id.exists' => __('الوكيل لا يتبع البنك المختار'),
            'source.required' => __('اختر مصدر العميل'),
        ]);

        $data['mobile'] = Phone::normalize($data['mobile']);
        $data['name'] = trim(preg_replace('/\s+/u', ' ', $data['name']));
        $data['bank_id'] = ($data['bank_id'] ?? null) ?: null;
        $data['agent_id'] = $data['bank_id'] ? (($data['agent_id'] ?? null) ?: null) : null;

        [$visit, $existed] = $this->customers->addRemote($data, $request->user());

        $message = $existed
            ? __('أُضيف تفاعل جديد لسجل العميل :name', ['name' => $visit->customer->name])
            : __('أُضيف العميل :name إلى قاعدة البيانات', ['name' => $visit->customer->name]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'existed' => $existed,
                'customer_url' => route('staff.customers.show', $visit->customer_id),
            ], 201);
        }

        return redirect()->route('staff.customers.show', $visit->customer_id)->with('status', $message);
    }
}
