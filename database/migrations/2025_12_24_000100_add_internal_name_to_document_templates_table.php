<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        if (! Schema::hasColumn('document_templates', 'internal_name')) {
            Schema::table('document_templates', function (Blueprint $table): void {
                $table->string('internal_name')
                    ->nullable()
                    ->unique()
                    ->after('id');
            });

            DB::table('document_templates')
                ->orderBy('id')
                ->get()
                ->each(function ($template): void {
                    $slug = Str::slug((string) ($template->name ?? 'template'));

                    DB::table('document_templates')
                        ->where('id', $template->id)
                        ->update([
                            'internal_name' => $slug === '' ? 'template-'.$template->id : $slug.'-'.$template->id,
                        ]);
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        if (Schema::hasColumn('document_templates', 'internal_name')) {
            Schema::table('document_templates', function (Blueprint $table): void {
                $table->dropUnique(['internal_name']);
                $table->dropColumn('internal_name');
            });
        }
    }
};
