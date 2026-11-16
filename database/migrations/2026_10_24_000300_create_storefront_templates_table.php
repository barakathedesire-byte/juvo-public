<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scope')->default('global');
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->json('blocks')->nullable();
            $table->boolean('status')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_templates');
    }
};
