<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\BankAgent;
use App\Rules\Mobile;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** وكلاء البنوك: مندوب لكل بنك يتابع أكثر من بائع ومشتري */
class AgentController extends Controller
{
    public function index(Request $request)
    {
        $bankFilter = $request->query('bank');

        return view('admin.agents', [
            'banks' => Bank::query()->ordered()->get(['id', 'name', 'is_active']),
            'agents' => BankAgent::query()
                ->select('bank_agents.*')
                ->with('bank:id,name')
                ->withCount(['visits', 'visits as sellers_count' => fn ($q) => $q->where('customer_type', 'seller'),
                    'visits as buyers_count' => fn ($q) => $q->where('customer_type', 'buyer')])
                ->when($bankFilter, fn ($q) => $q->where('bank_id', (int) $bankFilter))
                ->join('banks', 'banks.id', '=', 'bank_agents.bank_id')
                ->orderBy('banks.sort_order')->orderBy('bank_agents.sort_order')->orderBy('bank_agents.name')
                ->get(),
            'bankFilter' => $bankFilter,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $agent = BankAgent::create($data + ['sort_order' => (int) BankAgent::where('bank_id', $data['bank_id'])->max('sort_order') + 1]);
        AuditLog::record('agent.created', $agent, __('إضافة الوكيل :n لبنك :b', ['n' => $agent->name, 'b' => $agent->bank->name]));

        return back()->with('status', __('أُضيف الوكيل :n', ['n' => $agent->name]));
    }

    public function update(Request $request, BankAgent $agent)
    {
        $data = $this->validated($request, $agent);
        $data['is_active'] = $request->boolean('is_active');
        $agent->update($data);
        AuditLog::record('agent.updated', $agent, __('تعديل الوكيل :n', ['n' => $agent->name]), collect($agent->getChanges())->except('updated_at')->all());

        return back()->with('status', __('حُفظ الوكيل :n', ['n' => $agent->name]));
    }

    private function validated(Request $request, ?BankAgent $agent = null): array
    {
        $data = $request->validate([
            'bank_id' => ['required', 'exists:banks,id'],
            'name' => ['required', 'string', 'max:100',
                Rule::unique('bank_agents')->where('bank_id', $request->input('bank_id'))->ignore($agent)],
            'mobile' => ['nullable', 'string', 'max:20', new Mobile],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.unique' => __('يوجد وكيل بنفس الاسم لهذا البنك'),
        ]);
        $data['mobile'] = ($data['mobile'] ?? null) ? Phone::normalize($data['mobile']) : null;
        if (! isset($data['sort_order'])) {
            unset($data['sort_order']);
        }

        return $data;
    }
}
