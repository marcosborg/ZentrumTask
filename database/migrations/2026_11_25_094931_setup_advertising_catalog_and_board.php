<?php

use App\Models\BlogPost;
use App\Models\Board;
use Database\Seeders\AdvertisingFormatSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_formats', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('description');
            $table->string('image')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        (new AdvertisingFormatSeeder)->run();
        $board = Board::create(['slug' => 'zentrum-ads', 'name' => 'Zentrum Ads', 'position' => 100]);
        foreach (['Novo', 'Contactado', 'Em qualificação', 'Proposta em preparação', 'Proposta enviada', 'Em negociação', 'Aceite', 'Recusado', 'Arquivado'] as $position => $name) {
            $board->stages()->create(['slug' => Str::slug($name), 'name' => $name, 'position' => $position, 'is_initial' => $position === 0, 'is_final' => $position >= 6]);
        }
        BlogPost::firstOrCreate(['slug' => 'publicidade-em-viaturas-tvde-em-portugal'], [
            'title' => 'Publicidade em viaturas TVDE em Portugal: onde pode aparecer a sua marca?',
            'excerpt' => 'A alteração legislativa de 2026 abriu novas possibilidades para anunciar nas cidades. Conheça as zonas da viatura e os passos para preparar uma campanha.',
            'body' => '<p>A Lei n.º 59/2026 alterou a Lei n.º 45/2018. O artigo 12.º, n.º 11 permite publicidade no interior e no exterior das viaturas TVDE nas condições previstas para os táxis.</p><h2>Onde aplicar o vinil?</h2><p>Os formatos exteriores da Zentrum concentram-se nas portas laterais, excluindo os vidros, e nos guarda-lamas traseiros. O desenho final deve respeitar o artigo 5.º da Portaria n.º 451/2023, os dísticos, a iluminação e a visibilidade do condutor.</p><h2>Como preparar a campanha</h2><p>Indique a região, o período e o formato pretendidos. A seleção depende da disponibilidade das viaturas, da análise do conteúdo à luz do Código da Publicidade e das regras municipais aplicáveis. Uma proposta comercial não substitui uma licença municipal.</p><p><a href="https://diariodarepublica.pt/dr/detalhe/lei/59-2026-1161801432">Consultar a lei no Diário da República</a> · <a href="/publicidade">Conhecer os formatos Zentrum Ads</a></p>',
            'is_published' => true,
        ]);
    }

    public function down(): void
    {
        throw new RuntimeException('Esta migração contém conteúdo editorial e oportunidades comerciais. Rever os dados antes de reverter.');
    }
};
