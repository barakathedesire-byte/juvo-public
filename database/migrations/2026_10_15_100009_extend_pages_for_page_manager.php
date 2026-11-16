<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            if (! Schema::hasColumn('pages', 'page_type')) {
                $table->string('page_type')->default('custom')->after('slug');
            }
            if (! Schema::hasColumn('pages', 'template')) {
                $table->string('template')->default('default')->after('page_type');
            }
            if (! Schema::hasColumn('pages', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('template');
            }
            if (! Schema::hasColumn('pages', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('parent_id');
            }
            if (! Schema::hasColumn('pages', 'system_key')) {
                $table->string('system_key')->nullable()->unique()->after('is_system');
            }
            if (! Schema::hasColumn('pages', 'seo_title')) {
                $table->string('seo_title')->nullable()->after('system_key');
            }
            if (! Schema::hasColumn('pages', 'seo_description')) {
                $table->text('seo_description')->nullable()->after('seo_title');
            }
            if (! Schema::hasColumn('pages', 'seo_image')) {
                $table->string('seo_image')->nullable()->after('seo_description');
            }
            if (! Schema::hasColumn('pages', 'blocks')) {
                $table->json('blocks')->nullable()->after('seo_image');
            }
            if (! Schema::hasColumn('pages', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('blocks');
            }
            if (! Schema::hasColumn('pages', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            foreach (['page_type', 'template', 'parent_id', 'is_system', 'system_key', 'seo_title', 'seo_description', 'seo_image', 'blocks', 'sort_order', 'published_at'] as $column) {
                if (Schema::hasColumn('pages', $column)) {
                    if ($column === 'system_key') {
                        $table->dropUnique(['system_key']);
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};
