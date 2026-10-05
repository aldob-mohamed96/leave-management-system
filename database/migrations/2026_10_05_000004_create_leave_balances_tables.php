<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // رصيد الموظف لكل سنة ولكل نوع إجازة
        Schema::create('leave_balances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('leave_type_id')->constrained();
            $t->unsignedSmallInteger('year');
            $t->decimal('entitled', 5, 1)->default(0);     // المستحق للسنة الحالية
            $t->decimal('carried_over', 5, 1)->default(0); // رصيد السنوات السابقة
            $t->decimal('used', 5, 1)->default(0);         // المنصرف
            $t->timestamps();
            $t->unique(['employee_id', 'leave_type_id', 'year']);
        });

        // دفتر الحركات: المرجع الحقيقي لأي تغيير في الرصيد
        Schema::create('leave_balance_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('leave_balance_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('leave_request_id')->nullable()->index();
            $t->string('type', 20);                 // accrual | deduction | refund | carryover | adjustment
            $t->decimal('days', 5, 1);              // موجب أو سالب
            $t->string('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('leave_balance_transactions');
        Schema::dropIfExists('leave_balances');
    }
};
