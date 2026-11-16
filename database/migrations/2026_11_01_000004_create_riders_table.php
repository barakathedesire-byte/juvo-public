<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->string('national_id')->unique();
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('address');
            $table->string('city');
            $table->string('state');
            $table->string('postal_code')->nullable();

            // Vehicle details
            $table->enum('vehicle_type', ['motorcycle', 'bicycle', 'van', 'truck'])->default('motorcycle');
            $table->string('vehicle_make')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->integer('vehicle_year')->nullable();
            $table->string('vehicle_color')->nullable();
            $table->string('license_plate')->unique()->nullable();

            // License
            $table->string('license_number')->unique();
            $table->date('license_expiry');

            // KYC
            $table->enum('kyc_status', ['pending', 'under_review', 'approved', 'rejected'])->default('pending');
            $table->json('kyc_documents')->nullable();
            $table->timestamp('kyc_submitted_at')->nullable();
            $table->timestamp('kyc_reviewed_at')->nullable();
            $table->foreignId('kyc_reviewed_by')->nullable()->constrained('logistics_admins');
            $table->text('kyc_rejection_reason')->nullable();

            // Banking
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_branch')->nullable();

            // Emergency contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();

            // Performance
            $table->decimal('rating', 3, 2)->default(5.00);
            $table->integer('total_deliveries')->default(0);

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_online_at')->nullable();
            $table->decimal('current_lat', 10, 8)->nullable();
            $table->decimal('current_lng', 10, 8)->nullable();
            $table->string('profile_image')->nullable();

            // Background check
            $table->enum('background_check_status', ['pending', 'clear', 'flagged'])->default('pending');
            $table->timestamp('background_check_completed_at')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index(['kyc_status', 'is_active']);
            $table->index(['is_online', 'is_active']);
            $table->index(['city', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riders');
    }
};
