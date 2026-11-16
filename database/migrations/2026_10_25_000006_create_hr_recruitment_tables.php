<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_job_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('department_id')->nullable()->constrained('staff_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('openings')->default(1);
            $table->date('closes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hr_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_post_id')->constrained('hr_job_posts')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('resume_path')->nullable();
            $table->string('status')->default('applied'); // applied|screened|interview|offered|hired|rejected|withdrawn
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['job_post_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_candidates');
        Schema::dropIfExists('hr_job_posts');
    }
};
