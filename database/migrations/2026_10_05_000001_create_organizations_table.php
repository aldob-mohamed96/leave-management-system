<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // مديرية > إدارة > مدرسة (شجرة بـ parent_id)
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $t->string('type', 20);            // directorate | administration | school
            $t->string('name');
            $t->string('code', 30)->nullable()->unique();
            $t->string('path')->index();       // مثال: /1/5/23/  لسهولة جلب كل الفروع
            $t->unsignedTinyInteger('depth')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['type', 'parent_id']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            $t->boolean('is_active')->default(true);
        });
    }
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('organization_id'));
        Schema::dropIfExists('organizations');
    }
};
