<?php

/*
|--------------------------------------------------------------------------
| إعدادات نظام الاستقبال وإدارة الطابور
|--------------------------------------------------------------------------
|
| القيم هنا هي القيم الافتراضية. معظمها قابل للتعديل من لوحة الإدارة
| (تُحفظ في جدول settings وتتقدم على القيم الافتراضية). بيانات الدخول
| لمزود واتساب تبقى في ملف .env فقط ولا تُحفظ في قاعدة البيانات.
|
*/

return [

    // سياسة الطابور
    'queue' => [
        // إغلاق تذاكر الأيام السابقة التي بقيت في الانتظار (يعمل يوميًا عبر المجدول)
        'auto_close_stale' => true,
        // مدة الاستعلام الدوري لشاشات الموظفين والعرض (بالمللي ثانية)
        'poll_ms' => (int) env('QMS_POLL_MS', 2500),
    ],

    // واتساب: المزود وبيانات الربط
    'whatsapp' => [
        // log = تسجيل الرسائل فقط دون إرسال (للتجربة) | meta = WhatsApp Business Platform (Cloud API)
        'driver' => env('WHATSAPP_DRIVER', 'log'),

        'meta' => [
            'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            // يستخدم للتحقق من توقيع Webhook حالات التسليم
            'app_secret' => env('WHATSAPP_APP_SECRET'),
            'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
            'timeout' => 15,
        ],

        // عدد محاولات إعادة الإرسال عند الفشل
        'tries' => 3,
    ],

    // تشغيل عامل الطابور من المجدول (مناسب للاستضافة المشتركة بدون Supervisor)
    'queue_via_scheduler' => (bool) env('QMS_QUEUE_VIA_SCHEDULER', false),

    // النسخ الاحتياطي
    'backup' => [
        'keep' => (int) env('QMS_BACKUP_KEEP', 14),
        'path' => storage_path('app/backups'),
    ],

    // القيم الافتراضية للإعدادات القابلة للتعديل من لوحة الإدارة
    'defaults' => [
        'company_name' => 'شركة المحاماة والتوثيق',
        'welcome_message' => 'أهلًا بكم، يسعدنا خدمتكم. سجّل بياناتك وتابع رقمك على الشاشة.',
        'logo_path' => null,

        'country_code' => '966',

        'ticket_prefix_buyer' => 'M',
        'ticket_prefix_seller' => 'B',
        'ticket_digits' => 3,

        'kiosk_reset_seconds' => 15,
        'kiosk_token' => null,
        'display_token' => null,

        'display_bg' => '#0E3B33',
        'display_primary' => '#F2EFE6',
        'display_accent' => '#E3B04B',
        'display_show_employee' => false,
        'display_recent_count' => 5,
        'display_sound' => true,
        'display_voice' => false,

        // all = الموظف يبحث في جميع العملاء | own = في العملاء الذين خدمهم فقط
        'employee_search_scope' => 'all',

        'whatsapp_enabled' => true,
        'whatsapp_template_lang' => 'ar',

        'wa_registered_enabled' => true,
        'wa_registered_text' => 'مرحبًا {name}، تم تسجيلك بنجاح. رقم انتظارك: {ticket}.',
        'wa_registered_template' => '',
        'wa_registered_params' => 'name,ticket',

        'wa_near_turn_enabled' => false,
        'wa_near_turn_threshold' => 1,
        'wa_near_turn_text' => 'متبقي قبلك عميل واحد. رقمك {ticket}، نرجو البقاء قريبًا من صالة الانتظار.',
        'wa_near_turn_template' => '',
        'wa_near_turn_params' => 'ticket',

        'wa_called_enabled' => true,
        'wa_called_text' => 'حان دورك الآن. رقمك {ticket} - يرجى التوجه إلى مكتب {desk}.',
        'wa_called_template' => '',
        'wa_called_params' => 'ticket,desk',

        'wa_thanks_enabled' => true,
        'wa_thanks_text' => 'شكرًا لزيارتكم. سعدنا بخدمتكم ونسعد دائمًا بتعاملكم معنا.',
        'wa_thanks_template' => '',
        'wa_thanks_params' => '',

        'wa_services_line_enabled' => false,
        'wa_services_line_text' => 'نقدم خدمات المحاماة والتوثيق العقاري، ويسعدنا تواصلكم في أي وقت.',
    ],
];
