<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_paid')->default(false);
            $table->unsignedInteger('max_days')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->timestamps();
        });

        Schema::create('hr_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('hr_leave_types')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('days')->default(0);
            $table->string('status')->default('pending'); // pending|approved|rejected|cancelled
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'status', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leaves');
        Schema::dropIfExists('hr_leave_types');
    }
};
