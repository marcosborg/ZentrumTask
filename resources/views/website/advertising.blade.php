@extends('website.layout')
@section('title', $kit ? 'Media kit · Zentrum Ads' : 'Publicidade em viaturas TVDE para empresas | Zentrum Ads')
@push('head')
<meta name="description" content="Anuncie em carros TVDE com a Zentrum Ads. Escolha formatos exteriores, regiões e duração e peça uma proposta para a sua empresa.">
<meta property="og:description" content="Publicidade em movimento. Conheça os formatos e configure a sua campanha em viaturas TVDE.">
<link rel="canonical" href="{{ url()->current() }}">
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'Service','name'=>'Zentrum Ads','serviceType'=>'Publicidade em viaturas TVDE','url'=>route('ads.index'),'provider'=>['@type'=>'Organization','name'=>'Zentrum TVDE']], JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
@push('styles')
<link rel="stylesheet" href="{{ asset('website/assets/advertising.css') }}?v={{ hash_file('sha256', public_path('website/assets/advertising.css')) }}">
@endpush
@section('content')
<main class="ads">
<header class="ads-cover"><div class="container"><p class="ads-label">Zentrum Ads / {{ $kit ? 'Media kit' : 'Publicidade em movimento' }}</p>
<h1>{{ $kit ? 'Media kit Zentrum Ads.' : 'A sua marca não precisa de ficar parada.' }}</h1>
<p class="lead">Publicidade em viaturas TVDE que acompanha o movimento das cidades. Campanhas personalizadas para empresas, marcas e agências que procuram maior presença urbana.</p>
<a class="btn btn-light" href="{{ route('ads.index') }}#proposta">Pedir proposta</a> <a class="btn btn-outline-light" href="#formatos">Conhecer os formatos</a></div>
<img src="{{ asset('website/assets/ads-tesla.webp') }}" width="2000" height="698" style="object-position:75% center" alt="Tesla branco com publicidade Zentrum Ads nas portas laterais"></header>
<div class="container">
<section id="marcas"><p class="ads-label">Para marcas e agências</p><h2>Da rua do seu negócio às ruas da cidade.</h2>
<p>A Zentrum TVDE reúne a operação de frota e o acompanhamento dos motoristas. Com a Zentrum Ads, a sua empresa tem um interlocutor para selecionar viaturas, preparar o design e acompanhar a aplicação do vinil.</p>
<p>Da restauração, hotelaria e comércio local às imobiliárias, clínicas, universidades, centros comerciais, concessionários, empresas tecnológicas, eventos e marcas nacionais, cada campanha pode ajustar a duração e o número de viaturas ao seu objetivo.</p></section>
<section id="formatos"><p class="ads-label">Formatos publicitários</p><h2>Escolha o espaço da sua marca.</h2>
<div class="row g-3">@foreach ($formats as $format)<div class="col-md-6"><article class="ads-card h-100">
@if($format->image)<img loading="lazy" src="{{ asset('website/assets/ads-'.$format->image.'.webp') }}" alt="Simulação do formato {{ $format->name }}">@endif
<div><h3>{{ $format->name }}</h3><p>{{ $format->description }}</p><p>Preço sob proposta</p><a data-format="{{ $format->id }}" href="{{ route('ads.index', ['formato'=>$format->id]) }}#proposta">Pedir orçamento para {{ $format->name }} →</a></div></article></div>@endforeach</div></section>
<section id="como-funciona"><p class="ads-label">Como funciona</p><h2>Do primeiro contacto à circulação.</h2><ol class="ads-steps"><li>Conte-nos o que quer comunicar e a quem.</li><li>Receba uma proposta adaptada à região e ao período pretendidos.</li><li>Selecionamos as viaturas e aprovamos consigo o design.</li><li>Coordenamos a aplicação dos materiais.</li><li>A campanha entra em circulação e a Zentrum acompanha a execução.</li></ol>
<h3>Cobertura geográfica</h3><p>Indique as cidades ou regiões que pretende alcançar. A disponibilidade é confirmada sob consulta para cada período e formato.</p>
<p>Os formatos exteriores utilizam as portas laterais, excluindo os vidros, e os guarda-lamas traseiros. Cada instalação requer verificação das regras nacionais e municipais aplicáveis. <a href="https://diariodarepublica.pt/dr/detalhe/lei/59-2026-1161801432">Lei n.º 59/2026</a> · <a href="https://diariodarepublica.pt/dr/legislacao-consolidada/portaria/2023-870515838">Artigo 5.º da Portaria n.º 451/2023</a>.</p></section>
@if($kit)
@if($editorial)<section>{!! $editorial->body !!}</section>@endif
<section><h2>Contactos comerciais</h2><p><a href="mailto:info@zentrum-tvde.com">info@zentrum-tvde.com</a></p><button class="btn btn-primary" type="button" onclick="window.print()">Imprimir ou guardar em PDF</button></section>
@else
<section id="proposta"><p class="ads-label">Pedido de proposta</p><h2>Configure a sua campanha.</h2>
<p>Escolha o formato e a dimensão pretendida. A equipa comercial confirma a disponibilidade e prepara o orçamento.</p>
@if($errors->any())<div role="alert" class="alert alert-danger"><strong>Reveja os campos indicados.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form action="{{ route('ads.store') }}" method="post" enctype="multipart/form-data" id="ads-form">@csrf
<input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
<fieldset><legend>Configuração da campanha</legend><div class="row g-3">
@foreach(['fleet'=>['Número de viaturas',\App\Models\AdvertisingFormat::FLEET], 'format_id'=>['Formato publicitário',$formats->pluck('name','id')->all()], 'duration'=>['Duração',\App\Models\AdvertisingFormat::DURATIONS], 'design'=>['Precisa de design gráfico?',['Sim','Não','A definir']]] as $key=>[$label,$choices])
<label class="col-sm-6">{{ $label }}<select class="form-select" name="{{ $key }}" required><option value="">Selecione</option>
@foreach($choices as $id=>$choice) @php($value = $key === 'format_id' ? $id : $choice)
<option value="{{ $value }}" @selected((string)old($key,$key==='format_id'?request()->integer('formato'):'')===(string)$value)>{{ $choice }}</option>@endforeach</select></label>@endforeach
@foreach(['custom_duration'=>['Duração personalizada','text',false], 'regions'=>['Regiões ou cidades pretendidas','text',true], 'start'=>['Início previsto (opcional)','date',false], 'budget'=>['Orçamento indicativo em euros (opcional)','number',false]] as $key=>[$label,$type,$required])
<label class="col-sm-6">{{ $label }}<input class="form-control" name="{{ $key }}" type="{{ $type }}" value="{{ old($key) }}" @required($required) @if($type==='date') min="{{ now()->toDateString() }}" @elseif($type==='number') min="0" step="0.01" @else maxlength="200" @endif></label>@endforeach</div>
<button type="button" class="btn btn-outline-primary mt-3" id="ads-review" hidden>Ver resumo da campanha</button><p id="ads-summary" class="mt-3" aria-live="polite" tabindex="-1"></p><a id="ads-continue" href="#ads-contact" hidden>Continuar com esta configuração →</a></fieldset>
<fieldset id="ads-contact"><legend>Dados para a proposta</legend><div class="row g-3">
@foreach(['company'=>['Empresa','text',120], 'name'=>['Nome do contacto','text',100], 'email'=>['Email profissional','email',200], 'phone'=>['Telefone','tel',24], 'website'=>['Website (opcional)','url',255], 'sector'=>['Setor de atividade','text',100], 'campaign'=>['Tipo de campanha ou objetivo','text',160]] as $key=>[$label,$type,$limit])
<label class="col-sm-6">{{ $label }}<input class="form-control" name="{{ $key }}" type="{{ $type }}" maxlength="{{ $limit }}" value="{{ old($key) }}" @required($key!=='website')></label>@endforeach
<label class="col-12">Mensagem adicional (opcional)<textarea class="form-control" name="message" maxlength="3000">{{ old('message') }}</textarea></label>
<label class="col-12">Logótipo ou elementos gráficos (PDF, JPG ou PNG, até 5 MB)<input class="form-control" type="file" name="artwork" accept=".jpg,.jpeg,.png,.pdf"></label></div>
<label hidden>Fax<input name="fax" tabindex="-1" autocomplete="off"></label>
<p class="mt-3">A Zentrum utiliza os contactos e materiais enviados para analisar este pedido, preparar uma proposta e acompanhar a negociação. O acesso aos anexos é reservado à equipa que trata do pedido.</p>
<label><input name="privacy" value="1" type="checkbox" required @checked(old('privacy'))> Li a <a href="{{ route('ads.privacy') }}" target="_blank" rel="noopener">política de privacidade</a>.</label>
<div class="mt-3"><button class="btn btn-primary">Enviar pedido de proposta</button></div></fieldset></form></section>
<section><a href="{{ route('ads.kit') }}">Consultar o media kit</a> · <a href="{{ route('slot.show') }}#publicidade-slot">Rentabilizar a minha viatura</a></section>
@endif</div></main>
@endsection
@push('scripts')
<script src="{{ asset('website/assets/advertising.js') }}" defer></script>
@endpush
