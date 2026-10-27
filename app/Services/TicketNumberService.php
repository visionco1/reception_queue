<?php

namespace App\Services;

use App\Enums\CustomerType;
use App\Models\QueueTicket;
use App\Models\TicketCounter;
use App\Models\Visit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * إصدار أرقام الانتظار: رمز نوع العميل + رقم تسلسلي يومي (M023، B014).
 * الترقيم مستقل لكل رمز ويبدأ من جديد كل يوم، وترتيب الدور يبقى بحسب وقت التسجيل.
 * يجب استدعاؤه داخل Transaction.
 */
class TicketNumberService
{
    public function prefixFor(CustomerType $type): string
    {
        $prefix = $type === CustomerType::Buyer
            ? setting('ticket_prefix_buyer', 'M')
            : setting('ticket_prefix_seller', 'B');

        return mb_strtoupper(trim((string) $prefix)) ?: ($type === CustomerType::Buyer ? 'M' : 'B');
    }

    public function format(string $prefix, int $number): string
    {
        $digits = max(1, min(6, (int) setting('ticket_digits', 3)));

        return $prefix.str_pad((string) $number, $digits, '0', STR_PAD_LEFT);
    }

    public function issue(Visit $visit): QueueTicket
    {
        $prefix = $this->prefixFor($visit->customer_type);
        $date = today()->toDateString();
        $number = $this->next($date, $prefix);

        return $visit->ticket()->create([
            'ticket_date' => $date,
            'prefix' => $prefix,
            'number' => $number,
            'ticket_no' => $this->format($prefix, $number),
            'issued_at' => now(),
        ]);
    }

    private function next(string $date, string $prefix): int
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $counter = TicketCounter::query()
                ->where('counter_date', $date)
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                try {
                    // نقطة حفظ داخلية حتى لا يُفسد تعارضُ الإنشاء المعاملةَ الأم
                    $counter = DB::transaction(fn () => TicketCounter::create(['counter_date' => $date, 'prefix' => $prefix, 'last_number' => 0]));
                } catch (UniqueConstraintViolationException) {
                    continue; // أنشأه طلب متزامن، نعيد المحاولة مع القفل
                }
            }

            $counter->increment('last_number');

            return (int) $counter->last_number;
        }

        throw new \RuntimeException('Could not allocate a ticket number.');
    }
}
