<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (! Schema::hasColumn('shops', 'banner')) {
                $table->string('banner')->nullable()->after('logo');
            }
            if (! Schema::hasColumn('shops', 'story')) {
                $table->text('story')->nullable()->after('description');
            }
            if (! Schema::hasColumn('shops', 'shipping_policy')) {
                $table->text('shipping_policy')->nullable();
            }
            if (! Schema::hasColumn('shops', 'return_policy')) {
                $table->text('return_policy')->nullable();
            }
            if (! Schema::hasColumn('shops', 'warranty_policy')) {
                $table->text('warranty_policy')->nullable();
            }
            if (! Schema::hasColumn('shops', 'vacation_mode')) {
                $table->boolean('vacation_mode')->default(false);
            }
            if (! Schema::hasColumn('shops', 'vacation_note')) {
                $table->string('vacation_note', 500)->nullable();
            }
            if (! Schema::hasColumn('shops', 'support_email')) {
                $table->string('support_email')->nullable();
            }
            if (! Schema::hasColumn('shops', 'support_phone')) {
                $table->string('support_phone')->nullable();
            }
        });

        Schema::table('collections', function (Blueprint $table) {
            if (! Schema::hasColumn('collections', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('id')
                    ->constrained('vendors')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            foreach (['banner', 'story', 'shipping_policy', 'return_policy', 'warranty_policy', 'vacation_mode', 'vacation_note', 'support_email', 'support_phone'] as $col) {
                if (Schema::hasColumn('shops', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('collections', function (Blueprint $table) {
            if (Schema::hasColumn('collections', 'vendor_id')) {
                $table->dropConstrainedForeignId('vendor_id');
            }
        });
    }
};
