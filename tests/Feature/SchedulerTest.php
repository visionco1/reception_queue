<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    /** الاستضافة المشتركة تعطّل proc_open، فيجب ألا تعتمد أي مهمة على تشغيل عملية منفصلة */
    public function test_scheduled_tasks_run_in_process(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertNotEmpty($events);
        $events->each(fn ($e) => $this->assertInstanceOf(CallbackEvent::class, $e, (string) $e->description));
        $this->assertEqualsCanonicalizing(
            ['qms:close-stale', 'qms:backup', 'qms:whatsapp-retry-stuck', 'queue:prune-failed'],
            $events->pluck('description')->all(),
        );
    }
}
