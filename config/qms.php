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

    // حساب المدير الأول — يُنشأ مرة واحدة عند db:seed
    'admin' => [
        'username' => env('ADMIN_USERNAME', 'admin'),
        'password' => env('ADMIN_PASSWORD'),
    ],

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
        'company_name' => 'شركة سلطان الهيضل',
        'welcome_message' => 'سجّل بياناتك وخذ رقمك، وتابع دورك على الشاشة.',

        'country_code' => '966',

        // M-014 للمشتري، S-014 للبائع، ورمز للأنواع الأخرى (عملاء عن بعد)
        'ticket_prefix_buyer' => 'M',
        'ticket_prefix_seller' => 'S',
        'ticket_prefix_other' => 'G',
        'ticket_digits' => 3,

        'kiosk_reset_seconds' => 10,
        'kiosk_token' => null,
        'display_token' => null,

        // current = الرقم الحالي بخط كبير | offices = لوحة المكاتب
        'display_mode' => 'current',
        // مدة تنبيه النداء الجديد (ثوانٍ)، ثم تعرض الشاشة الرقم مع جدول النداءات
        'display_alert_seconds' => 10,
        // تعود الشاشة للخمول بعد هذه الدقائق بدون نداء إذا لم يكن أحد في الانتظار
        'display_idle_minutes' => 10,
        'display_recent_count' => 5,
        'display_sound' => true,
        // مستوى صوت التنبيه من 0 إلى 100
        'display_volume' => 80,
        'display_voice' => true,
        // رسائل الشريط المتحرك، كل سطر رسالة
        'ticker_messages' => "أهلًا بكم في شركة سلطان الهيضل\nيرجى إحضار الهوية الوطنية وصك العقار\nتابع رقمك على الشاشة أو على واتساب",

        // ساعات العمل (فارغة = بدون قيود). خارجها يعرض الآيباد رسالة ولا يسجل
        'work_start' => '',
        'work_end' => '',

        // متوسط الخدمة المفترض حتى تتوفر بيانات اليوم (للوقت التقريبي)
        'default_service_minutes' => 15,

        // all = الموظف يبحث في جميع العملاء | own = في العملاء الذين خدمهم فقط
        'employee_search_scope' => 'all',

        'whatsapp_enabled' => true,
        'whatsapp_template_lang' => 'ar',

        'wa_registered_enabled' => true,
        'wa_registered_text' => 'أهلًا {first_name}، رقم انتظارك {ticket} في {company}. أمامك {ahead}. تابع دورك: {link}',
        'wa_registered_template' => '',
        'wa_registered_params' => 'first_name,ticket,company,ahead,link',

        'wa_near_turn_enabled' => false,
        'wa_near_turn_threshold' => 1,
        'wa_near_turn_text' => 'اقترب دورك. رقمك {ticket}، نرجو البقاء قريبًا من صالة الانتظار.',
        'wa_near_turn_template' => '',
        'wa_near_turn_params' => 'ticket',

        'wa_called_enabled' => true,
        'wa_called_text' => 'حان دورك، تفضّل إلى مكتب {desk}.',
        'wa_called_template' => '',
        'wa_called_params' => 'desk',

        'wa_thanks_enabled' => true,
        'wa_thanks_text' => 'شكرًا لزيارتكم {company}. سعدنا بخدمتكم.',
        'wa_thanks_template' => '',
        'wa_thanks_params' => 'company',

        'wa_services_line_enabled' => false,
        'wa_services_line_text' => 'نقدم خدمات المحاماة والاستشارات، ويسعدنا تواصلكم في أي وقت.',
    ],
];
