<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('page_revisions')) {
            Schema::create('page_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('page_id')->constrained()->onDelete('cascade');
                $table->string('title')->nullable();
                $table->json('blocks')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->string('seo_image')->nullable();
                $table->string('status', 20)->default('draft');
                $table->string('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['page_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_revisions');
    }
};
