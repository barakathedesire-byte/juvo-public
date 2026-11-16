<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('product_reviews', 'vendor_reply')) {
                $table->text('vendor_reply')->nullable()->after('review');
            }
            if (! Schema::hasColumn('product_reviews', 'vendor_replied_at')) {
                $table->timestamp('vendor_replied_at')->nullable()->after('vendor_reply');
            }
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('customer_id')->constrained('products')->nullOnDelete();
            }
            if (! Schema::hasColumn('support_tickets', 'last_replied_by')) {
                $table->string('last_replied_by')->nullable()->after('last_reply_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            foreach (['vendor_reply', 'vendor_replied_at'] as $column) {
                if (Schema::hasColumn('product_reviews', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('support_tickets', 'product_id')) {
                $table->dropConstrainedForeignId('product_id');
            }
            if (Schema::hasColumn('support_tickets', 'last_replied_by')) {
                $table->dropColumn('last_replied_by');
            }
        });
    }
};
