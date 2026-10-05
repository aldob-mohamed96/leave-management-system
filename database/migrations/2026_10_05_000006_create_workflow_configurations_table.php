<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /*
         * كل صف = مرحلة واحدة في مسار الاعتماد لمؤسسة معينة.
         * مثال: مدرسة ABC → مرحلة 1 (مدير مباشر / any) → مرحلة 2 (مسؤول إجازات / all) → مرحلة 3 (مدير إدارة / any)
         */
        Schema::create('workflow_configurations', function (Blueprint $t) {
            $t->id();

            $t->foreignId('organization_id')
              ->constrained('organizations')
              ->cascadeOnDelete();

            // اسم المرحلة — حر النص عشان يسمح بإضافة مراحل مستقبلية
            $t->string('stage_name', 50);            // direct_manager | leaves_officer | admin_manager | ...

            // ترتيب المرحلة داخل المسار (1, 2, 3 …)
            $t->unsignedTinyInteger('step_order');

            // قاعدة الاعتماد المتوازي: all | any | majority
            $t->string('approval_rule', 20)->default('any');

            // الدور المطلوب لاعتماد هذه المرحلة (nullable = أي دور عنده صلاحية approve_leave_request)
            $t->string('required_role', 100)->nullable();

            // عنوان عربي للمرحلة يظهر في الواجهة
            $t->string('label')->nullable();          // مثال: "رأي المدير المباشر"

            // هل هذه المرحلة مفعّلة؟ (يمكن تعطيل مرحلة بدون حذفها)
            $t->boolean('is_active')->default(true);

            $t->timestamps();

            // لا يمكن أن يكون لنفس المؤسسة مرحلتان بنفس الترتيب
            $t->unique(['organization_id', 'step_order']);

            $t->index('organization_id');
            $t->index('stage_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_configurations');
    }
};
