<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number')->unique();
            // Link to the central staff account. Nullable so employment records
            // survive without (or before) portal access; nullOnDelete preserves
            // the HR record if the login account is removed.
            $table->foreignId('user_id')->nullable()->unique()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()
                ->constrained('staff_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()
                ->constrained('positions')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->string('employment_type')->default('full_time');
            $table->date('hire_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active');
            $table->string('work_email')->nullable();
            $table->string('work_phone')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
