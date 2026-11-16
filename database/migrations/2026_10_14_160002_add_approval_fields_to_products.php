<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'approval_status')) {
                $table->enum('approval_status', ['draft', 'pending', 'approved', 'rejected'])
                    ->default('approved')->after('status');
            }
            if (! Schema::hasColumn('products', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approval_status');
            }
            if (! Schema::hasColumn('products', 'visibility')) {
                $table->enum('visibility', ['public', 'hidden'])->default('public')->after('rejection_reason');
            }
            if (! Schema::hasColumn('products', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('visibility');
            }
            if (! Schema::hasColumn('products', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('products', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('products', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('published_at');
            }
            if (! Schema::hasColumn('products', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('archived_at');
            }
        });

        // Existing catalogue rows are treated as approved + published.
        \Illuminate\Support\Facades\DB::table('products')->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'published_at' => now(),
        ]);

        Schema::table('products', function (Blueprint $table) {
            $table->index('approval_status');
            $table->index('visibility');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'approval_status', 'rejection_reason', 'visibility', 'submitted_at',
                'approved_at', 'published_at', 'archived_at', 'approved_by',
            ]);
        });
    }
};
