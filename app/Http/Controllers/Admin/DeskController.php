<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Desk;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeskController extends Controller
{
    public function index()
    {
        return view('admin.desks', ['desks' => Desk::query()->ordered()->withCount('users')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:20', 'unique:desks,number'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);
        $desk = Desk::create($data + ['sort_order' => (int) Desk::max('sort_order') + 1]);
        AuditLog::record('desk.created', $desk, __('إضافة مكتب :n', ['n' => $desk->number]));

        return back()->with('status', __('أُضيف مكتب :n', ['n' => $desk->number]));
    }

    public function update(Request $request, Desk $desk)
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:20', Rule::unique('desks')->ignore($desk)],
            'name' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $desk->update($data);
        AuditLog::record('desk.updated', $desk, __('تعديل مكتب :n', ['n' => $desk->number]), $desk->getChanges());

        return back()->with('status', __('حُفظ مكتب :n', ['n' => $desk->number]));
    }
}
