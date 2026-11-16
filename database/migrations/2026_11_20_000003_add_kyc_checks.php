<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            if (! Schema::hasColumn('riders', 'kyc_checks')) {
                $table->json('kyc_checks')->nullable()->after('kyc_documents');
            }
            if (! Schema::hasColumn('riders', 'kyc_score')) {
                $table->unsignedTinyInteger('kyc_score')->nullable()->after('kyc_checks');
            }
        });

        Schema::table('vendor_verification_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_verification_documents', 'checks')) {
                $table->json('checks')->nullable()->after('status');
            }
            if (! Schema::hasColumn('vendor_verification_documents', 'score')) {
                $table->unsignedTinyInteger('score')->nullable()->after('checks');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_verification_documents', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_verification_documents', 'score')) {
                $table->dropColumn('score');
            }
            if (Schema::hasColumn('vendor_verification_documents', 'checks')) {
                $table->dropColumn('checks');
            }
        });

        Schema::table('riders', function (Blueprint $table) {
            if (Schema::hasColumn('riders', 'kyc_score')) {
                $table->dropColumn('kyc_score');
            }
            if (Schema::hasColumn('riders', 'kyc_checks')) {
                $table->dropColumn('kyc_checks');
            }
        });
    }
};
