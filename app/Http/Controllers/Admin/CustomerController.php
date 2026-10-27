<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Phone;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $repeat = $request->boolean('repeat');

        $customers = Customer::query()
            ->when($q !== '', function ($query) use ($q) {
                $digits = preg_replace('/\D+/', '', Phone::toAsciiDigits($q));
                $query->where(fn ($w) => strlen($digits) >= 4
                    ? $w->where('mobile', 'like', '%'.ltrim($digits, '0').'%')
                    : $w->where('name', 'like', "%{$q}%"));
            })
            ->when($repeat, fn ($query) => $query->where('visits_count', '>', 1))
            ->orderByDesc('last_visit_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.customers', [
            'customers' => $customers,
            'q' => $q,
            'repeat' => $repeat,
            'total' => Customer::count(),
        ]);
    }
}
