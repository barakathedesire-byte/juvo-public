<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('vendors', 'banner')) {
                $table->string('banner')->nullable()->after('profile_image');
            }
            if (! Schema::hasColumn('vendors', 'opening_hours')) {
                $table->json('opening_hours')->nullable()->after('address');
            }
            if (! Schema::hasColumn('vendors', 'delivery_coverage')) {
                $table->json('delivery_coverage')->nullable()->after('opening_hours');
            }
            if (! Schema::hasColumn('vendors', 'return_policy')) {
                $table->text('return_policy')->nullable()->after('delivery_coverage');
            }
            if (! Schema::hasColumn('vendors', 'shipping_policy')) {
                $table->text('shipping_policy')->nullable()->after('return_policy');
            }
            if (! Schema::hasColumn('vendors', 'privacy_policy')) {
                $table->text('privacy_policy')->nullable()->after('shipping_policy');
            }
            if (! Schema::hasColumn('vendors', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('privacy_policy');
            }
            if (! Schema::hasColumn('vendors', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('vendors', 'verification_status')) {
                $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])
                    ->default('unverified')->after('longitude');
            }
            if (! Schema::hasColumn('vendors', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verification_status');
            }
            if (! Schema::hasColumn('vendors', 'verified_by')) {
                $table->unsignedBigInteger('verified_by')->nullable()->after('verified_at');
            }
            if (! Schema::hasColumn('vendors', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('verified_by');
            }
            if (! Schema::hasColumn('vendors', 'suspension_reason')) {
                $table->text('suspension_reason')->nullable()->after('suspended_at');
            }
        });

        Schema::create('vendor_verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('type');
            $table->string('title')->nullable();
            $table->string('file_path');
            $table->string('disk')->default('local');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_verification_documents');

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn([
                'banner', 'opening_hours', 'delivery_coverage', 'return_policy',
                'shipping_policy', 'privacy_policy', 'latitude', 'longitude',
                'verification_status', 'verified_at', 'verified_by',
                'suspended_at', 'suspension_reason',
            ]);
        });
    }
};
