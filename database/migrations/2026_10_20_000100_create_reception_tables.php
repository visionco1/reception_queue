<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // قائمة البنوك (قابلة للإدارة)
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // بيانات العميل الأساسية — رقم الجوال هو المفتاح الفريد
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mobile', 20)->unique();
            $table->text('notes')->nullable();
            $table->unsignedInteger('visits_count')->default(0);
            $table->timestamp('last_visit_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('name');
        });

        // كل زيارة أو تفاعل (حضوري أو عن بعد)
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20)->index();          // in_person | remote
            $table->string('customer_type', 20)->index();    // buyer | seller
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20)->nullable();        // للعميل عن بعد
            $table->string('status', 20)->index();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('desk_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_returning')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedInteger('wait_seconds')->nullable();
            $table->unsignedInteger('service_seconds')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('service_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
            $table->index(['status', 'channel']);
        });

        // تذاكر الطابور — للحضوري فقط
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('ticket_date');
            $table->string('prefix', 5);
            $table->unsignedInteger('number');
            $table->string('ticket_no', 12);
            $table->timestamp('issued_at');
            $table->timestamp('called_at')->nullable();
            $table->string('desk_label', 20)->nullable();
            $table->unsignedSmallInteger('recall_count')->default(0);
            $table->timestamp('near_notified_at')->nullable();
            $table->timestamps();
            $table->unique(['ticket_date', 'prefix', 'number']);
            $table->index('ticket_date');
        });

        // عدّاد أرقام الانتظار اليومي لكل رمز
        Schema::create('ticket_counters', function (Blueprint $table) {
            $table->id();
            $table->date('counter_date');
            $table->string('prefix', 5);
            $table->unsignedInteger('last_number')->default(0);
            $table->unique(['counter_date', 'prefix']);
        });

        // جلسات الخدمة: من خدم العميل ومدة الخدمة
        Schema::create('service_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('desk_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_status', 20)->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'started_at']);
        });

        // نداءات شاشة الانتظار
        Schema::create('display_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_no', 12);
            $table->string('desk_label', 20);
            $table->string('employee_name')->nullable();
            $table->string('kind', 10)->default('call'); // call | recall
            $table->timestamp('created_at')->nullable();
            $table->index('created_at');
        });

        // سجل حالات الزيارة (من غيّر الحالة ومتى)
        Schema::create('visit_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // تتبع رسائل واتساب
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message_key', 30);
            $table->string('to_phone', 20);
            $table->text('body');
            $table->string('provider', 20);
            $table->string('template_name')->nullable();
            $table->json('template_params')->nullable();
            $table->string('status', 20)->index(); // queued | sent | delivered | read | failed | skipped
            $table->string('provider_message_id')->nullable()->index();
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });

        // سجل التدقيق للأحداث المهمة
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['subject_type', 'subject_id']);
        });

        // الإعدادات القابلة للتعديل من لوحة الإدارة
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'audit_logs', 'whatsapp_logs', 'visit_status_logs', 'display_calls',
            'service_sessions', 'ticket_counters', 'queue_tickets', 'visits', 'customers', 'banks'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
