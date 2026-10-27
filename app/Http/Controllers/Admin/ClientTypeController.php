<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ClientType;
use Illuminate\Http\Request;

/** أنواع العملاء الأخرى (غير البائع والمشتري) التي يختار منها الموظف للعميل عن بعد */
class ClientTypeController extends Controller
{
    public function index()
    {
        return view('admin.client-types', ['types' => ClientType::query()->ordered()->withCount('visits')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:client_types,name']]);
        $type = ClientType::create($data + ['sort_order' => (int) ClientType::max('sort_order') + 1]);
        AuditLog::record('client_type.created', $type, __('إضافة نوع العميل :n', ['n' => $type->name]));

        return back()->with('status', __('أُضيف :n', ['n' => $type->name]));
    }

    public function update(Request $request, ClientType $clientType)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:client_types,name,'.$clientType->id],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $clientType->update(array_filter($data, fn ($v) => $v !== null));
        AuditLog::record('client_type.updated', $clientType, __('تعديل نوع العميل :n', ['n' => $clientType->name]), collect($clientType->getChanges())->except('updated_at')->all());

        return back()->with('status', __('حُفظ :n', ['n' => $clientType->name]));
    }
}
