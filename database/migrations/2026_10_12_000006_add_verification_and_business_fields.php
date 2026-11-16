<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('status');
            $table->string('verification_code', 10)->nullable()->after('email_verified_at');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('is_approved');
            $table->string('verification_code', 10)->nullable()->after('email_verified_at');
            $table->string('tin_number', 64)->nullable()->after('phone');
            $table->string('registration_number', 64)->nullable()->after('tin_number');
            $table->text('business_documents')->nullable()->after('registration_number');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'verification_code']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'verification_code', 'tin_number', 'registration_number', 'business_documents']);
        });
    }
};
