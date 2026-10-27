<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Channel;
use App\Enums\CustomerType;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankAgent;
use App\Models\User;
use App\Models\Visit;
use App\Support\VisitFilters;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function index(Request $request)
    {
        $filters = VisitFilters::values($request);

        return view('admin.visits.index', [
            'visits' => VisitFilters::query($filters)->paginate(30)->withQueryString(),
            'filters' => $filters,
            'banks' => Bank::query()->ordered()->get(['id', 'name']),
            'agents' => BankAgent::query()->with('bank:id,name')->ordered()->get(['id', 'name', 'bank_id']),
            'employees' => User::query()->orderBy('name')->get(['id', 'name']),
            'channels' => Channel::options(),
            'types' => CustomerType::options(),
            'statuses' => VisitStatus::options(),
        ]);
    }

    public function show(Visit $visit)
    {
        $visit->load('customer', 'ticket', 'bank', 'agent', 'clientType', 'employee', 'creator', 'desk', 'statusLogs.user', 'whatsappLogs', 'sessions.employee', 'deal.visits.customer', 'deal.visits.ticket', 'deal.visits.clientType');

        return view('admin.visits.show', compact('visit'));
    }
}
