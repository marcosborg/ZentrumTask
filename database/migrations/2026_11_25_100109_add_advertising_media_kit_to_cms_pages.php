<?php

use App\Models\CmsPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->string('content_key')->nullable()->unique();
        });

        $page = new CmsPage([
            'title' => 'Zentrum Ads · Media kit',
            'highlight' => 'Publicidade em movimento para empresas e agências.',
            'body' => '<h2>Sobre a Zentrum</h2><p>A Zentrum TVDE trabalha com motoristas de aluguer e proprietários de viatura. A equipa comercial acompanha a seleção de viaturas e formatos para cada campanha.</p><h3>Disponibilidade e indicadores</h3><p>Regiões, capacidade e datas sob consulta. Indique onde e quando pretende comunicar para prepararmos uma proposta.</p>',
            'is_active' => true,
        ]);
        $page->content_key = 'advertising_media_kit';
        $page->save();
    }

    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->dropUnique(['content_key']);
            $table->dropColumn('content_key');
        });
    }
};
