<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_leads', 'region_id')) {
                $table->foreignId('region_id')->nullable()->after('phone')->constrained('regions')->nullOnDelete();
            }
            if (! Schema::hasColumn('sales_leads', 'location_id')) {
                $table->foreignId('location_id')->nullable()->after('region_id')->constrained('locations')->nullOnDelete();
            }
            if (! Schema::hasColumn('sales_leads', 'source')) {
                $table->string('source', 60)->nullable()->after('email');
            }
            if (! Schema::hasColumn('sales_leads', 'interest')) {
                $table->string('interest')->nullable()->after('source');
            }
            if (! Schema::hasColumn('sales_leads', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('interest')->constrained('categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('sales_leads', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('category_id')->constrained('products')->nullOnDelete();
            }
            if (! Schema::hasColumn('sales_leads', 'needs')) {
                $table->text('needs')->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('sales_leads', 'budget')) {
                $table->decimal('budget', 12, 2)->nullable()->after('needs');
            }
            if (! Schema::hasColumn('sales_leads', 'timeline')) {
                $table->string('timeline', 60)->nullable()->after('budget');
            }
            if (! Schema::hasColumn('sales_leads', 'lost_reason')) {
                $table->string('lost_reason', 60)->nullable()->after('status');
            }
            if (! Schema::hasColumn('sales_leads', 'last_contacted_at')) {
                $table->timestamp('last_contacted_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('sales_leads', 'next_followup_at')) {
                $table->timestamp('next_followup_at')->nullable()->after('last_contacted_at');
            }
            if (! Schema::hasColumn('sales_leads', 'converted_customer_id')) {
                $table->foreignId('converted_customer_id')->nullable()->after('converted_order_id')->constrained('customers')->nullOnDelete();
            }
            if (! Schema::hasColumn('sales_leads', 'converted_at')) {
                $table->timestamp('converted_at')->nullable()->after('converted_customer_id');
            }
            if (! Schema::hasColumn('sales_leads', 'referral_customer_id')) {
                $table->foreignId('referral_customer_id')->nullable()->after('converted_at')->constrained('customers')->nullOnDelete();
            }
            if (! Schema::hasColumn('sales_leads', 'referral_sales_rep_id')) {
                $table->foreignId('referral_sales_rep_id')->nullable()->after('referral_customer_id')->constrained('sales_reps')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            // Keep rollback simple.
        });
    }
};
