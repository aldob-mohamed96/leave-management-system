<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlement_grades', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('category', 30); // teacher|guidance|admin|special
            $table->string('name');
            $table->unsignedSmallInteger('yearly_days');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlement_grades');
    }
};
