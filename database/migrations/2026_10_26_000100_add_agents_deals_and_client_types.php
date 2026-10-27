<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // وكلاء البنوك: مندوب البنك الذي يتابع صفقات عدة بائعين ومشترين
        Schema::create('bank_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('mobile', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['bank_id', 'is_active']);
        });

        // أنواع العملاء الأخرى (غير البائع والمشتري) للعملاء عن بعد — تديرها الإدارة
        Schema::create('client_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // الصفقة: تربط زيارة البائع بزيارة المشتري (وتقبل أكثر من طرف)
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('bank_agents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('bank_id')->constrained('bank_agents')->nullOnDelete();
            $table->foreignId('client_type_id')->nullable()->after('customer_type')->constrained('client_types')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->after('agent_id')->constrained('deals')->nullOnDelete();
        });

        // النداءات التي تظهر معًا على الشاشة (بائع ومشتري لنفس المكتب)
        Schema::table('display_calls', function (Blueprint $table) {
            $table->string('batch', 36)->nullable()->after('kind')->index();
        });
    }

    public function down(): void
    {
        Schema::table('display_calls', function (Blueprint $table) {
            $table->dropIndex(['batch']);
            $table->dropColumn('batch');
        });
        Schema::table('visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deal_id');
            $table->dropConstrainedForeignId('client_type_id');
            $table->dropConstrainedForeignId('agent_id');
        });
        Schema::dropIfExists('deals');
        Schema::dropIfExists('client_types');
        Schema::dropIfExists('bank_agents');
    }
};
