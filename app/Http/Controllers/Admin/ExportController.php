<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Visit;
use App\Support\Phone;
use App\Support\VisitFilters;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/** تصدير العملاء والزيارات إلى Excel أو CSV (يدعم العربية في Excel) */
class ExportController extends Controller
{
    public function visits(Request $request)
    {
        $filters = VisitFilters::values($request);
        $format = $this->format($request);
        $headers = [
            __('رقم الزيارة'), __('التاريخ'), __('وقت التسجيل'), __('العميل'), __('الجوال'), __('نوع التسجيل'),
            __('النوع'), __('البنك'), __('وكيل البنك'), __('مرتبط بـ'), __('مصدر العميل'), __('رقم الانتظار'), __('الحالة'), __('الموظف'), __('المكتب'),
            __('وقت الاستدعاء'), __('وقت إنهاء الخدمة'), __('مدة الانتظار (دقيقة)'), __('مدة الخدمة (دقيقة)'),
            __('عميل متكرر'), __('ملاحظات'),
        ];

        AuditLog::record('export.visits', null, __('تصدير الزيارات'), array_filter($filters) + ['format' => $format]);

        return response()->streamDownload(function () use ($filters, $format, $headers) {
            $writer = $this->open($format, $headers);
            VisitFilters::query($filters)->reorder('visits.id')->chunk(500, function ($visits) use ($writer) {
                foreach ($visits as $v) {
                    /** @var Visit $v */
                    $writer->addRow(Row::fromValues([
                        $v->id,
                        $v->created_at->format('Y-m-d'),
                        $v->created_at->format('H:i'),
                        $v->customer?->name,
                        Phone::format($v->customer?->mobile),
                        $v->channel->label(),
                        $v->typeLabel(),
                        $v->bank?->name ?? '',
                        $v->agent?->name ?? '',
                        $v->deal ? $v->deal->visits->reject(fn ($o) => $o->id === $v->id)
                            ->map(fn ($o) => trim(($o->ticket?->ticket_no ?? '').' '.$o->customer->name).' ('.$o->typeLabel().')')
                            ->implode('، ') : '',
                        $v->source?->label() ?? '',
                        $v->ticket?->ticket_no ?? '',
                        $v->status->label(),
                        $v->employee?->name ?? '',
                        $v->desk?->number ?? '',
                        $v->called_at?->format('H:i') ?? '',
                        $v->completed_at?->format('H:i') ?? '',
                        $v->wait_seconds !== null ? round($v->wait_seconds / 60, 1) : '',
                        $v->service_seconds !== null ? round($v->service_seconds / 60, 1) : '',
                        $v->is_returning ? __('نعم') : __('لا'),
                        (string) $v->notes,
                    ]));
                }
            });
            $writer->close();
        }, $this->filename('visits', $format), $this->contentType($format));
    }

    public function customers(Request $request)
    {
        $format = $this->format($request);
        $headers = [__('رقم العميل'), __('الاسم'), __('الجوال'), __('عدد الزيارات'), __('آخر زيارة'), __('تاريخ التسجيل'), __('ملاحظات')];

        AuditLog::record('export.customers', null, __('تصدير العملاء'), ['format' => $format]);

        return response()->streamDownload(function () use ($format, $headers) {
            $writer = $this->open($format, $headers);
            Customer::query()->orderBy('id')->chunk(500, function ($customers) use ($writer) {
                foreach ($customers as $c) {
                    $writer->addRow(Row::fromValues([
                        $c->id,
                        $c->name,
                        Phone::format($c->mobile),
                        $c->visits_count,
                        $c->last_visit_at?->format('Y-m-d H:i') ?? '',
                        $c->created_at->format('Y-m-d H:i'),
                        (string) $c->notes,
                    ]));
                }
            });
            $writer->close();
        }, $this->filename('customers', $format), $this->contentType($format));
    }

    private function format(Request $request): string
    {
        return $request->query('format') === 'csv' ? 'csv' : 'xlsx';
    }

    private function open(string $format, array $headers): WriterInterface
    {
        if ($format === 'csv') {
            $writer = new CsvWriter; // يضيف BOM تلقائيًا حتى يقرأ Excel العربية بشكل صحيح
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($headers));

            return $writer;
        }

        $writer = new XlsxWriter;
        $writer->openToFile('php://output');
        $sheet = $writer->getCurrentSheet();
        $sheet->setSheetView((new SheetView)->setRightToLeft(true)->setFreezeRow(2));
        $sheet->setColumnWidthForRange(16, 1, count($headers));
        $writer->addRow(Row::fromValues($headers, (new Style)->setFontBold()->setBackgroundColor('E2EFEB')));

        return $writer;
    }

    private function filename(string $what, string $format): string
    {
        return $what.'-'.now()->format('Y-m-d-His').'.'.$format;
    }

    private function contentType(string $format): array
    {
        return ['Content-Type' => $format === 'csv'
            ? 'text/csv; charset=UTF-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    }
}
