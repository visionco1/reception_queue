<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bank;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function index()
    {
        return view('admin.banks', ['banks' => Bank::query()->ordered()->withCount('visits')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:banks,name']]);
        $bank = Bank::create($data + ['sort_order' => (int) Bank::max('sort_order') + 1]);
        AuditLog::record('bank.created', $bank, __('إضافة بنك :n', ['n' => $bank->name]));

        return back()->with('status', __('أُضيف :n', ['n' => $bank->name]));
    }

    public function update(Request $request, Bank $bank)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:banks,name,'.$bank->id],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $bank->update($data);
        AuditLog::record('bank.updated', $bank, __('تعديل بنك :n', ['n' => $bank->name]), $bank->getChanges());

        return back()->with('status', __('حُفظ :n', ['n' => $bank->name]));
    }
}
