<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();                         // رقم الطلب
            $t->foreignId('employee_id')->constrained();
            $t->foreignId('organization_id')->constrained('organizations'); // المدرسة وقت التقديم
            $t->foreignId('leave_type_id')->constrained();
            $t->foreignId('substitute_employee_id')->nullable()->constrained('employees')->nullOnDelete(); // القائم بالعمل
            $t->date('start_date');
            $t->date('end_date');
            $t->decimal('days', 5, 1);
            $t->date('written_at')->nullable();                         // تحريرا في
            $t->text('reason')->nullable();

            // snapshot الرصيد وقت التقديم (لا يتغير لاحقاً)
            $t->decimal('balance_entitled', 5, 1)->nullable();
            $t->decimal('balance_used', 5, 1)->nullable();
            $t->decimal('balance_remaining', 5, 1)->nullable();

            // draft | submitted | in_review | returned | approved | rejected | cancelled
            $t->string('status', 20)->default('draft')->index();
            $t->string('current_stage', 30)->nullable();                // direct_manager | leaves_officer | admin_manager
            $t->text('rejection_reason')->nullable();

            $t->foreignId('created_by')->constrained('users');          // موظف المدرسة
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['organization_id', 'status']);
            $t->index(['employee_id', 'start_date']);
            $t->index(['start_date', 'end_date']);
        });

        // مراحل الاعتماد (رأي كل جهة)
        Schema::create('leave_request_steps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('step_order');
            $t->string('stage', 30);                       // direct_manager | leaves_officer | admin_manager
            $t->string('status', 20)->default('pending');  // pending | approved | rejected | returned | skipped
            $t->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('acted_at')->nullable();
            $t->text('note')->nullable();                  // الرأي / سبب الرفض
            $t->timestamps();
            $t->unique(['leave_request_id', 'step_order']);
        });

        Schema::create('leave_request_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('leave_request_attachments');
        Schema::dropIfExists('leave_request_steps');
        Schema::dropIfExists('leave_requests');
    }
};
