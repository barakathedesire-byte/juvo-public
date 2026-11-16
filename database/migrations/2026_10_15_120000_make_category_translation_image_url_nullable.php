<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A category must be creatable without an image. The column was defined
     * NOT NULL, so every no-image save failed at the database level even
     * though the validation rules allowed it.
     */
    public function up(): void
    {
        $column = DB::selectOne(
            "SELECT IS_NULLABLE AS nullable FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'category_translations'
               AND COLUMN_NAME = 'image_url'"
        );

        if ($column && strtoupper($column->nullable) === 'NO') {
            DB::statement('ALTER TABLE category_translations MODIFY image_url VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        DB::statement("UPDATE category_translations SET image_url = '' WHERE image_url IS NULL");
        DB::statement('ALTER TABLE category_translations MODIFY image_url VARCHAR(255) NOT NULL');
    }
};
