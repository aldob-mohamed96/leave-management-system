<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            // الدرجة الوظيفية لحساب استحقاق الإجازة الاعتيادية
            // nullable → يمكن تحديثها لاحقاً عند استيراد بيانات الموظفين
            $t->string('entitlement_grade', 30)
              ->nullable()
              ->after('grade')
              ->comment('teacher|teacher_first|teacher_first_a|teacher_expert|teacher_senior|admin_4|admin_3|admin_over50');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->dropColumn('entitlement_grade');
        });
    }
};
