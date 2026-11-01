<?php

use App\Support\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * تعديلات دليل التنفيذ v1.0:
 * - ترتيب الدور بعمود مستقل (لم يحضر يعيد العميل لآخر الدور مرة واحدة)
 * - نتيجة الخدمة/الصفقة: مكتملة، ناقصة أوراق، ملغاة
 * - «عن طريق بنك / أخرى» وتفصيل «أخرى» (شركة عقارية أو أفراد)
 * - رابط متابعة الدور للعميل، والتحويل لمكتب آخر
 * - البنوك الأربعة الظاهرة على الآيباد
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->timestamp('queued_at')->nullable()->after('issued_at');
            $table->unsignedTinyInteger('no_show_count')->default(0)->after('recall_count');
            $table->index(['ticket_date', 'queued_at']);
        });
        DB::table('queue_tickets')->update(['queued_at' => DB::raw('issued_at')]);

        Schema::table('visits', function (Blueprint $table) {
            $table->string('via_detail', 30)->nullable()->after('bank_id');
            $table->string('outcome', 20)->nullable()->after('status')->index();
            $table->string('track_token', 40)->nullable()->unique()->after('outcome');
            $table->foreignId('assigned_desk_id')->nullable()->after('desk_id')->constrained('desks')->nullOnDelete();
        });

        // الزيارات المكتملة سابقًا تُعد صفقات مكتملة
        DB::table('visits')->where('status', 'completed')->update(['outcome' => 'completed']);

        Schema::table('deals', function (Blueprint $table) {
            $table->string('outcome', 20)->nullable()->after('agent_id');
        });

        Schema::table('banks', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_active');
        });

        // أول 4 بنوك نشطة تظهر على الآيباد حتى يختار المدير غيرها
        $first = DB::table('banks')->where('is_active', true)->orderBy('sort_order')->orderBy('id')->limit(4)->pluck('id');
        DB::table('banks')->whereIn('id', $first)->update(['is_featured' => true]);

        // رمز البائع في الدليل S بدل B (إن لم يغيّره المدير لرمز آخر)
        DB::table('settings')->where('key', 'ticket_prefix_seller')->where('value', 'B')->update(['value' => 'S']);

        // القيم الافتراضية القديمة المحفوظة كما هي تُحذف لتظهر نصوص الدليل الجديدة
        // (ما عدّله المدير بنفسه يبقى كما هو)
        $oldDefaults = [
            'company_name' => 'شركة المحاماة والتوثيق',
            'welcome_message' => 'أهلًا بكم، يسعدنا خدمتكم. سجّل بياناتك وتابع رقمك على الشاشة.',
            'kiosk_reset_seconds' => '15',
            'wa_registered_text' => 'مرحبًا {name}، تم تسجيلك بنجاح. رقم انتظارك: {ticket}.',
            'wa_registered_params' => 'name,ticket',
            'wa_near_turn_text' => 'متبقي قبلك عميل واحد. رقمك {ticket}، نرجو البقاء قريبًا من صالة الانتظار.',
            'wa_called_text' => 'حان دورك الآن. رقمك {ticket} - يرجى التوجه إلى مكتب {desk}.',
            'wa_called_params' => 'ticket,desk',
            'wa_thanks_text' => 'شكرًا لزيارتكم. سعدنا بخدمتكم ونسعد دائمًا بتعاملكم معنا.',
            'wa_thanks_params' => '',
        ];
        foreach ($oldDefaults as $key => $value) {
            DB::table('settings')->where('key', $key)->where('value', $value)->delete();
        }

        DB::table('visits')->whereNull('track_token')->orderBy('id')->select('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('visits')->where('id', $row->id)->update(['track_token' => Str::random(24)]);
            }
        });

        app(Settings::class)->flush();
    }

    public function down(): void
    {
        Schema::table('banks', fn (Blueprint $t) => $t->dropColumn('is_featured'));
        Schema::table('deals', fn (Blueprint $t) => $t->dropColumn('outcome'));
        Schema::table('visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_desk_id');
            $table->dropUnique(['track_token']);
            $table->dropIndex(['outcome']);
            $table->dropColumn(['via_detail', 'outcome', 'track_token']);
        });
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropIndex(['ticket_date', 'queued_at']);
            $table->dropColumn(['queued_at', 'no_show_count']);
        });
    }
};
