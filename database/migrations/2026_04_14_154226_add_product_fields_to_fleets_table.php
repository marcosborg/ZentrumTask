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
        Schema::table('fleets', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->string('brand')->nullable()->after('slug');
            $table->string('model')->nullable()->after('brand');
            $table->decimal('rental_price', 10, 2)->nullable()->after('model');
            $table->string('price_suffix')->default('/semana')->after('rental_price');
            $table->string('excerpt')->nullable()->after('price_suffix');
            $table->text('description')->nullable()->after('excerpt');
            $table->json('gallery_paths')->nullable()->after('photo_path');
            $table->boolean('is_published')->default(true)->after('gallery_paths');
        });

        DB::table('fleets')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function (object $fleet): void {
                $baseSlug = Str::slug((string) $fleet->name) ?: 'viatura';
                $slug = $baseSlug;
                $suffix = 1;

                while (DB::table('fleets')
                    ->where('slug', $slug)
                    ->where('id', '!=', $fleet->id)
                    ->exists()) {
                    $slug = $baseSlug.'-'.$suffix;
                    $suffix++;
                }

                DB::table('fleets')
                    ->where('id', $fleet->id)
                    ->update(['slug' => $slug]);
            });
    }

    public function down(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'brand',
                'model',
                'rental_price',
                'price_suffix',
                'excerpt',
                'description',
                'gallery_paths',
                'is_published',
            ]);
        });
    }
};
