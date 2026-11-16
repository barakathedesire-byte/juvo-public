<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Governance metadata for configurable rules. Every rule now carries a
     * stable owner, a validated schema, a safe default, a failure behaviour,
     * an effective window and a version, with an append-only revision log.
     */
    public function up(): void
    {
        Schema::table('business_rules', function (Blueprint $table) {
            $table->string('category')->nullable()->after('key');
            $table->string('owner')->nullable()->after('category');
            $table->string('validation_rules')->nullable()->after('value_type');
            $table->text('default_value')->nullable()->after('value');
            $table->enum('on_invalid', ['default', 'block'])->default('default')->after('default_value');
            $table->boolean('is_managed')->default(false)->after('is_active');
            $table->boolean('is_editable')->default(true)->after('is_managed');
            $table->unsignedInteger('version')->default(1)->after('is_editable');
            $table->timestamp('effective_from')->nullable()->after('version');
            $table->timestamp('effective_to')->nullable()->after('effective_from');
            $table->unsignedBigInteger('updated_by')->nullable()->after('effective_to');

            $table->index(['category', 'is_active']);
        });

        Schema::create('business_rule_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_rule_id')->constrained('business_rules')->cascadeOnDelete();
            $table->string('key');
            $table->unsignedInteger('version');
            $table->text('value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('reason')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['key', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_rule_revisions');

        Schema::table('business_rules', function (Blueprint $table) {
            $table->dropIndex(['category', 'is_active']);
            $table->dropColumn([
                'category', 'owner', 'validation_rules', 'default_value', 'on_invalid',
                'is_managed', 'is_editable', 'version', 'effective_from', 'effective_to', 'updated_by',
            ]);
        });
    }
};
