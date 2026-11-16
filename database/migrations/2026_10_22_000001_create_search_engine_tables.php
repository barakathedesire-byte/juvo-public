<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------------------------------------------------------------
         | Query indexes for common storefront/search access patterns.
         | Guarded so the migration is idempotent on an already-indexed DB.
         * ------------------------------------------------------------- */
        $this->index('products', ['category_id'], 'products_category_id_index');
        $this->index('products', ['brand_id'], 'products_brand_id_index');
        $this->index('products', ['status'], 'products_status_index');
        $this->index('product_variants', ['product_id', 'price'], 'product_variants_product_price_index');
        $this->index('product_variants', ['product_id', 'stock'], 'product_variants_product_stock_index');

        /* ---------------------------------------------------------------
         | Derived search index. One row per product+locale. Kept fully
         | rebuildable from authoritative catalog tables — never a source
         | of truth for eligibility or pricing.
         * ------------------------------------------------------------- */
        Schema::create('product_search_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('locale', 5)->default('en');
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->unsignedBigInteger('brand_id')->nullable()->index();
            $table->string('title');
            $table->text('keywords')->nullable();
            $table->decimal('price_min', 12, 2)->nullable();
            $table->decimal('discount_price_min', 12, 2)->nullable();
            $table->boolean('in_stock')->default(false)->index();
            $table->boolean('is_promoted')->default(false);
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('popularity')->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'locale']);
        });

        // InnoDB FULLTEXT index gives the candidate/relevance signal without
        // loading the catalog into memory.
        DB::statement('ALTER TABLE product_search_documents ADD FULLTEXT product_search_fulltext (title, keywords)');

        /* ---------------------------------------------------------------
         | Managed synonym dictionary. Permissioned, validated, auditable;
         | never edited implicitly by ranking code.
         * ------------------------------------------------------------- */
        Schema::create('search_synonyms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 120)->unique();
            $table->string('locale', 5)->default('en')->index();
            $table->json('synonyms');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        /* ---------------------------------------------------------------
         | Aggregate query log. No PII beyond a salted IP hash. Powers the
         | query-quality, zero-result and latency KPIs.
         * ------------------------------------------------------------- */
        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query', 255)->nullable();
            $table->string('normalized', 255)->index();
            $table->string('locale', 5)->default('en')->index();
            $table->string('surface', 32)->default('results')->index();
            $table->unsignedInteger('result_count')->default(0);
            $table->boolean('is_zero_result')->default(false)->index();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('algorithm_key', 64)->nullable();
            $table->unsignedInteger('algorithm_version')->nullable();
            $table->json('filters')->nullable();
            $table->string('sort', 32)->nullable();
            $table->string('session_id', 64)->nullable()->index();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['occurred_at', 'is_zero_result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
        Schema::dropIfExists('search_synonyms');
        Schema::dropIfExists('product_search_documents');

        foreach ([
            ['products', 'products_status_index'],
            ['products', 'products_brand_id_index'],
            ['products', 'products_category_id_index'],
            ['product_variants', 'product_variants_product_stock_index'],
            ['product_variants', 'product_variants_product_price_index'],
        ] as [$table, $index]) {
            if ($this->hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $b) => $b->dropIndex($index));
            }
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    protected function index(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table) || $this->hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $b) => $b->index($columns, $name));
    }

    protected function hasIndex(string $table, string $name): bool
    {
        foreach (DB::select("SHOW INDEX FROM {$table}") as $row) {
            if (($row->Key_name ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
