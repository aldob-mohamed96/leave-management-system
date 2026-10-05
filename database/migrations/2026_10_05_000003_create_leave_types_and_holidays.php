<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();         // regular | casual | sick ...
            $t->string('name');
            $t->boolean('deducts_balance')->default(true);
            $t->decimal('yearly_entitlement', 5, 1)->default(0);
            $t->unsignedSmallInteger('max_days_per_request')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->date('date')->unique();
            $t->string('name');
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('leave_types');
    }
};
