<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Allow longer highlight text by changing column to TEXT.
        if (Schema::hasTable('cms_pages') && $this->isMySql()) {
            DB::statement('ALTER TABLE cms_pages MODIFY highlight TEXT');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to VARCHAR(255) if needed.
        if (Schema::hasTable('cms_pages') && $this->isMySql()) {
            DB::statement('ALTER TABLE cms_pages MODIFY highlight VARCHAR(255)');
        }
    }

    private function isMySql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
