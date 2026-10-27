<?php

namespace Database\Seeders;

use App\Enums\Channel;
use App\Enums\ClientSource;
use App\Enums\CustomerType;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Bank;
use App\Models\BankAgent;
use App\Models\ClientType;
use App\Models\Deal;
use App\Models\Customer;
use App\Models\Desk;
use App\Models\DisplayCall;
use App\Models\QueueTicket;
use App\Models\ServiceSession;
use App\Models\TicketCounter;
use App\Models\User;
use App\Models\Visit;
use App\Models\WhatsappLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * بيانات تجريبية للعرض والتدريب: موظفون، عملاء، وزيارات لآخر 30 يومًا
 * مع طابور حي لليوم. لا تُشغَّل على بيانات الإنتاج.
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    private array $first = ['محمد', 'عبدالله', 'فهد', 'سارة', 'نورة', 'خالد', 'ريم', 'سلطان', 'هيفاء', 'عبدالرحمن', 'لطيفة', 'تركي', 'منيرة', 'سعود', 'أمل', 'ماجد', 'الجوهرة', 'نايف', 'دانة', 'بندر'];

    private array $last = ['العتيبي', 'القحطاني', 'الشهري', 'الدوسري', 'الغامدي', 'الحربي', 'المطيري', 'الزهراني', 'السبيعي', 'الشمري', 'العنزي', 'المالكي'];

    private $agentsByBank;

    private array $clientTypes = [];

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        mt_srand(2026);

        $desks = Desk::query()->ordered()->get();
        $staff = collect([['سلمان الحربي', 'salman'], ['أريج القحطاني', 'areej'], ['فيصل الدوسري', 'faisal'], ['منى الشهري', 'mona']])
            ->map(fn ($u, $i) => User::firstOrCreate(['username' => $u[1]], [
                'name' => $u[0], 'password' => 'password', 'role' => Role::Employee,
                'desk_id' => $desks[$i % $desks->count()]->id, 'is_active' => true,
            ]));
        User::firstOrCreate(['username' => 'supervisor'], ['name' => 'هند المشرفة', 'password' => 'password', 'role' => Role::Supervisor, 'is_active' => true]);

        $banks = Bank::query()->orderBy('sort_order')->pluck('id')->all();
        $bankWeights = [30, 22, 12, 10, 6, 5, 5, 4, 3, 2, 1];

        // وكلاء تجريبيون لأكثر البنوك ورودًا
        $agentNames = [['خالد المندوب', 'عمر السبيعي', 'ياسر العنزي'], ['ناصر القحطاني', 'بدر الشمري'], ['وليد الحربي', 'سامي الزهراني'], ['فارس العتيبي'], ['حمد المطيري']];
        foreach ($agentNames as $i => $names) {
            foreach ($names as $j => $name) {
                BankAgent::firstOrCreate(['bank_id' => $banks[$i], 'name' => $name], ['sort_order' => $j + 1]);
            }
        }
        $this->agentsByBank = BankAgent::query()->get()->groupBy('bank_id');
        $this->clientTypes = ClientType::query()->pluck('id')->all();

        $customers = collect(range(1, 160))->map(fn ($i) => Customer::firstOrCreate(
            ['mobile' => '9665'.str_pad((string) (10000000 + $i * 7919 % 89999999), 8, '0', STR_PAD_LEFT)],
            ['name' => $this->first[array_rand($this->first)].' '.$this->last[array_rand($this->last)]]
        ));

        for ($d = 29; $d >= 1; $d--) {
            $day = today()->subDays($d);
            if ($day->isFriday()) {
                continue;
            }
            $count = $day->isSaturday() ? mt_rand(4, 9) : mt_rand(10, 22);
            $this->seedDay($day, $count, $customers, $staff, $desks, $banks, $bankWeights, true);
        }

        // اليوم: بعض العملاء خُدموا وبعضهم في الانتظار الآن
        $this->seedDay(today(), 9, $customers, $staff, $desks, $banks, $bankWeights, false);

        Customer::query()->each(fn (Customer $c) => $c->refreshVisitStats());
    }

    private function seedDay(Carbon $day, int $count, $customers, $staff, $desks, array $banks, array $weights, bool $closed): void
    {
        $counters = [];
        $times = collect(range(1, $count))->map(fn () => $this->peakTime($day))->sort()->values();
        $now = now();

        foreach ($times as $i => $at) {
            if (! $closed && $at->greaterThan($now->copy()->subMinutes(2))) {
                $at = $now->copy()->subMinutes(($count - $i) * 4 + 1);
            }
            $customer = $customers->random();
            $type = mt_rand(1, 100) <= 58 ? CustomerType::Buyer : CustomerType::Seller;
            $bankId = $this->weighted($banks, $weights);

            if (mt_rand(1, 100) <= 22) {
                $employee = $staff->random();
                $isOther = $this->clientTypes && mt_rand(1, 100) <= 25;
                $visit = Visit::create([
                    'customer_id' => $customer->id, 'channel' => Channel::Remote,
                    'customer_type' => $isOther ? CustomerType::Other : $type,
                    'client_type_id' => $isOther ? $this->clientTypes[array_rand($this->clientTypes)] : null,
                    'bank_id' => $isOther ? null : $bankId,
                    'agent_id' => $isOther ? null : $this->pickAgent($bankId),
                    'source' => collect(ClientSource::cases())->random(), 'status' => VisitStatus::Completed,
                    'employee_id' => $employee->id, 'created_by' => $employee->id, 'completed_at' => $at,
                    'is_returning' => $customer->visits()->exists(),
                ]);
                $visit->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

                continue;
            }

            $prefix = $type === CustomerType::Buyer ? 'M' : 'B';
            $counters[$prefix] = ($counters[$prefix] ?? 0) + 1;

            $isLive = ! $closed && $i >= $count - 4;
            $visit = Visit::create([
                'customer_id' => $customer->id, 'channel' => Channel::InPerson, 'customer_type' => $type, 'bank_id' => $bankId,
                'agent_id' => $this->pickAgent($bankId),
                'status' => VisitStatus::Waiting, 'is_returning' => $customer->visits()->exists(),
            ]);
            $visit->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
            $ticket = QueueTicket::create([
                'visit_id' => $visit->id, 'ticket_date' => $day->toDateString(), 'prefix' => $prefix,
                'number' => $counters[$prefix], 'ticket_no' => $prefix.str_pad((string) $counters[$prefix], 3, '0', STR_PAD_LEFT),
                'issued_at' => $at,
            ]);

            $this->log($visit, $customer, 'registered', "مرحبًا {$customer->name}، تم تسجيلك بنجاح. رقم انتظارك: {$ticket->ticket_no}.", $at);

            if ($isLive) {
                continue;
            }

            $employee = $staff->random();
            $desk = $desks->firstWhere('id', $employee->desk_id) ?? $desks->first();
            $calledAt = $at->copy()->addSeconds(mt_rand(120, 1500));
            $noShow = mt_rand(1, 100) <= 5;
            $endAt = $calledAt->copy()->addSeconds(mt_rand(300, 1800));

            $ticket->update(['called_at' => $calledAt, 'desk_label' => $desk->number]);
            $visit->forceFill([
                'status' => $noShow ? VisitStatus::NoShow : VisitStatus::Completed,
                'employee_id' => $employee->id, 'desk_id' => $desk->id, 'called_at' => $calledAt,
                'wait_seconds' => $at->diffInSeconds($calledAt), 'completed_at' => $endAt,
                'service_seconds' => $noShow ? null : $calledAt->diffInSeconds($endAt),
            ])->saveQuietly();
            ServiceSession::create([
                'visit_id' => $visit->id, 'employee_id' => $employee->id, 'desk_id' => $desk->id,
                'started_at' => $calledAt, 'ended_at' => $endAt, 'end_status' => $noShow ? 'no_show' : 'completed',
            ]);
            DisplayCall::create(['visit_id' => $visit->id, 'ticket_no' => $ticket->ticket_no, 'desk_label' => $desk->number,
                'employee_name' => $employee->name, 'kind' => 'call'])->forceFill(['created_at' => $calledAt])->saveQuietly();

            $this->log($visit, $customer, 'called', "حان دورك الآن. رقمك {$ticket->ticket_no} - يرجى التوجه إلى مكتب {$desk->number}.", $calledAt);
            if (! $noShow) {
                $this->log($visit, $customer, 'thanks', 'شكرًا لزيارتكم. سعدنا بخدمتكم ونسعد دائمًا بتعاملكم معنا.', $endAt);
            }
        }

        $this->pairDeals($day, $closed);

        foreach ($counters as $prefix => $n) {
            TicketCounter::updateOrCreate(['counter_date' => $day->toDateString(), 'prefix' => $prefix], ['last_number' => $n]);
        }
    }

    private function pickAgent(?int $bankId): ?int
    {
        $agents = $bankId ? $this->agentsByBank->get($bankId) : null;
        if (! $agents || mt_rand(1, 100) > 80) {
            return null;
        }

        return $agents->random()->id;
    }

    /** ربط بائعي ومشتري نفس الوكيل في نفس اليوم كصفقات (المنتظرون اليوم يبقون بلا ربط للتجربة) */
    private function pairDeals(Carbon $day, bool $closed): void
    {
        $visits = Visit::query()->whereDate('created_at', $day)->whereNotNull('agent_id')->whereNull('deal_id')
            ->when(! $closed, fn ($q) => $q->where('status', '!=', VisitStatus::Waiting->value))
            ->whereIn('customer_type', ['buyer', 'seller'])->get()->groupBy('agent_id');

        foreach ($visits as $agentId => $group) {
            $sellers = $group->where('customer_type', CustomerType::Seller)->values();
            $buyers = $group->where('customer_type', CustomerType::Buyer)->values();
            foreach ($sellers as $i => $seller) {
                $buyer = $buyers->get($i);
                if (! $buyer || mt_rand(1, 100) > 75) {
                    continue;
                }
                $deal = Deal::create(['bank_id' => $seller->bank_id, 'agent_id' => $agentId]);
                $deal->forceFill(['created_at' => $seller->created_at, 'updated_at' => $seller->created_at])->saveQuietly();
                Visit::query()->whereKey([$seller->id, $buyer->id])->update(['deal_id' => $deal->id]);
            }
        }

        if (! $closed) {
            // اليوم: بائع ومشتري ينتظران بنفس الوكيل دون ربط، لتجربة الربط والاستدعاء معًا
            $waiting = Visit::query()->whereDate('created_at', $day)->where('status', VisitStatus::Waiting->value)->get();
            $seller = $waiting->firstWhere('customer_type', CustomerType::Seller);
            $buyer = $waiting->firstWhere('customer_type', CustomerType::Buyer);
            $agent = $this->agentsByBank->first()?->first();
            if ($seller && $buyer && $agent) {
                Visit::query()->whereKey([$seller->id, $buyer->id])->update(['bank_id' => $agent->bank_id, 'agent_id' => $agent->id]);
            }
        }
    }

    private function log(Visit $visit, Customer $customer, string $key, string $body, Carbon $at): void
    {
        $failed = mt_rand(1, 100) <= 2;
        WhatsappLog::create([
            'visit_id' => $visit->id, 'customer_id' => $customer->id, 'message_key' => $key, 'to_phone' => $customer->mobile,
            'body' => $body, 'provider' => 'log', 'status' => $failed ? 'failed' : 'sent', 'attempts' => $failed ? 3 : 1,
            'sent_at' => $failed ? null : $at, 'failed_at' => $failed ? $at : null,
            'error' => $failed ? '[400] Recipient phone number not in allowed list' : null,
        ])->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
    }

    /** أوقات واقعية: ذروة قبل الظهر وبعد العصر */
    private function peakTime(Carbon $day): Carbon
    {
        $hour = $this->weighted([8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19], [3, 8, 14, 15, 10, 5, 4, 7, 10, 9, 6, 3]);

        return $day->copy()->setTime($hour, mt_rand(0, 59), mt_rand(0, 59));
    }

    private function weighted(array $items, array $weights)
    {
        $weights = array_slice($weights, 0, count($items));
        $r = mt_rand(1, array_sum($weights));
        foreach ($items as $i => $item) {
            $r -= $weights[$i] ?? 1;
            if ($r <= 0) {
                return $item;
            }
        }

        return end($items);
    }
}
