<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // المعلمون/الموظفون مقدمو الطلبات (مختلفون عن مستخدمي النظام)
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations');   // المدرسة
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('employee_code', 30)->unique();
            $t->string('full_name');
            $t->string('job_title')->nullable();      // الوظيفة
            $t->string('grade')->nullable();          // الدرجة
            $t->date('birth_date')->nullable();
            $t->date('hire_date')->nullable();        // تاريخ التعيين
            $t->date('work_start_date')->nullable();  // تاريخ استلام العمل
            $t->string('phone', 20)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['organization_id', 'full_name']);
        });
    }
    public function down(): void { Schema::dropIfExists('employees'); }
};
