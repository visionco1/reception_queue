#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────
# تحديث النظام على الاستضافة بعد رفع تعديلات جديدة إلى GitHub:
#   cd ~/domains/your-domain.com/public_html && bash deploy.sh
# لا يلمس ملف .env ولا مجلد storage (البيانات والنسخ الاحتياطية).
# ─────────────────────────────────────────────────────────────
set -euo pipefail
cd "$(dirname "$0")"
PHP="${PHP:-php}"

if [ ! -f .env ]; then
    echo "ملف .env غير موجود. انسخ .env.hostinger.example باسم .env وعدّله أولًا." >&2
    exit 1
fi

git pull --ff-only

# --no-scripts لأن Hostinger تعطّل proc_open فيفشل composer في تشغيل artisan بنفسه
composer install --no-dev --optimize-autoloader --no-scripts --no-interaction
"$PHP" artisan package:discover --ansi

"$PHP" artisan migrate --force
"$PHP" artisan optimize

echo "تم التحديث بنجاح."
