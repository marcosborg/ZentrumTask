@extends('website.layout')
@section('title', $van->name.' — Aluguer de carrinhas | Zentrum')
@push('head')<meta name="description" content="{{ \Illuminate\Support\Str::limit($van->description, 155) }}">@endpush
@push('styles') @vite('resources/css/van-rentals.css') @endpush
@push('scripts') @vite('resources/js/van-rentals.js') @endpush
@section('content')
<main class="van-area" data-van-rental data-availability="{{ route('van-rentals.availability', $van) }}" data-quote="{{ route('van-rentals.quote', $van) }}" data-opens="{{ substr($van->opens_at, 0, 5) }}" data-closes="{{ substr($van->closes_at, 0, 5) }}" data-today="{{ now('Europe/Lisbon')->format('Y-m-d') }}">
    <section class="van-section"><div class="container">
        <a href="{{ route('van-rentals.index') }}" class="van-small">← Todas as carrinhas</a>
        <div class="vr:mt-5 vr:mb-8"><span class="van-kicker">Mercadorias & mudanças</span><h1 class="vr:mt-3">{{ $van->name }}</h1><div class="van-tags">@if($van->self_drive)<span class="van-tag">Sem motorista</span>@endif @if($van->with_driver)<span class="van-tag">Com motorista</span>@endif</div></div>
        @if(session('van_success'))
            <div class="van-notice vr:mb-6" role="status"><strong>Pedido recebido, sujeito a confirmação. A viatura ainda não está reservada.</strong><p class="vr:mb-0">Referência: {{ session('van_success') }}. A equipa irá contactá-lo através dos dados indicados.</p></div>
        @endif
        <div class="vr:grid vr:grid-cols-1 vr:lg:grid-cols-2 vr:gap-8 vr:items-start">
            <div>
                <img class="van-gallery-main" data-main-photo src="{{ $van->imageUrl() }}" alt="{{ $van->name }}">
                <div class="van-gallery-thumbs">@foreach($van->photos ?? [] as $photo)<button type="button" data-gallery-photo="{{ Storage::disk('rental_vans')->url($photo) }}" aria-label="Ver fotografia {{ $loop->iteration }}"><img src="{{ Storage::disk('rental_vans')->url($photo) }}" alt="" loading="lazy"></button>@endforeach</div>
                <h2 class="vr:mt-6">Espaço para levar mais</h2><p style="white-space:pre-line">{{ $van->description }}</p>
                <dl class="vr:grid vr:grid-cols-2 vr:gap-x-6">
                    @foreach(['Volume útil' => number_format($van->volume_m3, 1, ',', ' ').' m³', 'Carga máxima' => number_format($van->payload_kg, 0, ',', ' ').' kg', 'Compartimento (C × L × A)' => $van->cargo_dimensions, 'Lugares' => $van->seats, 'Combustível' => $van->fuel, 'Transmissão' => $van->transmission] as $label => $value)
                        <div class="van-spec"><dt>{{ $label }}</dt><dd>{{ $value ?: 'Sob consulta' }}</dd></div>
                    @endforeach
                </dl>
                <div class="van-tags vr:my-6">@foreach($van->equipment ?? [] as $equipment)<span class="van-tag">{{ $equipment }}</span>@endforeach</div>
                <h2 class="vr:mt-8">Condições claras, antes de partir.</h2>
                @foreach(['Levantamento e devolução' => $van->pickup_location, 'Quilometragem' => $van->mileage_terms, 'Combustível / carregamento' => $van->fuel_terms, 'Cancelamento' => $van->cancellation_terms, 'Condições de aluguer' => $van->rental_terms] as $label => $text)
                    <details class="van-spec"><summary>{{ $label }}</summary><p class="vr:mt-3" style="white-space:pre-line">{{ $text }}</p></details>
                @endforeach
                <div class="van-notice vr:mt-6">Com motorista, o preço inclui condução. Ajuda de carga, descarga e ajudantes são serviços adicionais, sujeitos a orçamento.</div>
            </div>
            <div class="van-panel">
                <span class="van-kicker">O seu próximo transporte</span><h2 class="vr:mt-3">Planeie o aluguer</h2>
                <p class="van-small">Valores finais com IVA incluído. @if($van->startingPricingUnit() === 'two_days')O pacote é faturado por cada período iniciado de 48 h.@else Mínimo {{ $van->minimum_hours }} h, faturado por hora iniciada.@endif Antecedência mínima: {{ $van->lead_hours }} h. Levantamento/devolução: {{ substr($van->opens_at, 0, 5) }}–{{ substr($van->closes_at, 0, 5) }}, hora local. Duração máxima: 30 dias.</p>
                <div class="vr:my-5">
                    <label for="van-month">Calendário de disponibilidade</label><input type="month" id="van-month" data-calendar-month value="{{ now('Europe/Lisbon')->format('Y-m') }}" class="vr:my-3">
                    <div class="van-calendar van-small vr:mb-2" aria-hidden="true">@foreach(['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'] as $day)<span>{{ $day }}</span>@endforeach</div>
                    <div class="van-calendar" data-calendar aria-label="Dias do mês"></div>
                    <p class="van-small vr:mt-3">Selecione primeiro o dia de levantamento e depois o dia de entrega. Dias a âmbar têm períodos ocupados, incluindo preparação.</p>
                    <p data-calendar-status role="status" class="van-small"></p><div data-day-details class="van-notice van-small" hidden></div>
                </div>
                <form method="post" action="{{ route('van-rentals.store', $van) }}" data-reservation-form class="vr:grid vr:gap-5">
                    @csrf
                    <input type="hidden" name="submission_key" value="{{ $submissionKey }}">
                    <div hidden><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                    @if($errors->any())<div class="van-error" role="alert">Verifique os campos assinalados.@error('submission_key')<p>{{ $message }}</p>@enderror</div>@endif
                    <div class="van-field"><label for="van-mode">Como quer alugar?</label><select name="mode" id="van-mode" required>
                        @if($van->self_drive)<option value="self_drive" @selected(old('mode') === 'self_drive')>Sem motorista — {{ number_format($van->self_drive_rate / 100, 2, ',', ' ') }} €{{ $van->self_drive_pricing_unit === 'two_days' ? ' / 2 dias' : '/h' }}</option>@endif
                        @if($van->with_driver)<option value="with_driver" @selected(old('mode') === 'with_driver')>Com motorista — {{ number_format($van->with_driver_rate / 100, 2, ',', ' ') }} €{{ $van->with_driver_pricing_unit === 'two_days' ? ' / 2 dias' : '/h' }}</option>@endif
                    </select><span class="van-error" data-error="mode">@error('mode'){{ $message }}@enderror</span></div>
                    <div class="vr:grid vr:md:grid-cols-2 vr:gap-4">
                        @foreach(['starts_at' => 'Início — hora local', 'ends_at' => 'Fim — hora local'] as $name => $label)<div class="van-field"><label for="van-{{ $name }}">{{ $label }}</label><input type="datetime-local" step="1800" name="{{ $name }}" id="van-{{ $name }}" value="{{ old($name) }}" required><span class="van-error" data-error="{{ $name }}">@error($name){{ $message }}@enderror</span></div>@endforeach
                    </div>
                    <div class="van-notice" data-estimate aria-live="polite">Escolha as datas para calcular a estimativa.</div>
                    <div class="vr:grid vr:md:grid-cols-2 vr:gap-4">
                        @foreach(['name' => ['Nome completo','text'], 'email' => ['Email','email'], 'phone' => ['Telefone','tel']] as $name => $field)<div class="van-field"><label for="van-{{ $name }}">{{ $field[0] }}</label><input name="{{ $name }}" type="{{ $field[1] }}" id="van-{{ $name }}" value="{{ old($name) }}" maxlength="{{ $name === 'phone' ? 40 : 150 }}" required autocomplete="{{ $name === 'phone' ? 'tel' : $name }}">@error($name)<span class="van-error">{{ $message }}</span>@enderror</div>@endforeach
                        <div class="van-field"><label for="van-purpose">Finalidade</label><select id="van-purpose" name="purpose" required><option value="goods" @selected(old('purpose') === 'goods')>Transporte de mercadorias</option><option value="moving" @selected(old('purpose') === 'moving')>Mudanças</option></select>@error('purpose')<span class="van-error">{{ $message }}</span>@enderror</div>
                    </div>
                    <div data-driver-fields class="vr:grid vr:gap-4">
                        @foreach(['origin'=>'Origem do transporte', 'destination'=>'Destino do transporte'] as $name=>$label)<div class="van-field"><label for="van-{{ $name }}">{{ $label }}</label><input name="{{ $name }}" id="van-{{ $name }}" value="{{ old($name) }}" maxlength="255">@error($name)<span class="van-error">{{ $message }}</span>@enderror</div>@endforeach
                        <label><input type="checkbox" name="loading_help" value="1" @checked(old('loading_help'))> Pretendo orçamento para ajuda de carga/descarga</label>
                    </div>
                    <div data-pickup class="van-small">Levantamento e devolução: {{ $van->pickup_location }}</div>
                    <div class="van-field"><label for="van-notes">O que vai transportar? (opcional)</label><textarea id="van-notes" name="notes" maxlength="3000" rows="3" placeholder="Volume aproximado, acessos, andares ou outras necessidades…">{{ old('notes') }}</textarea>@error('notes')<span class="van-error">{{ $message }}</span>@enderror</div>
                    <label class="van-small"><input type="checkbox" name="accept_terms" value="1" required @checked(old('accept_terms'))> Li as condições apresentadas e compreendo que o pedido depende de confirmação pela equipa. Os dados serão usados para gerir este pedido.</label>@error('accept_terms')<span class="van-error">{{ $message }}</span>@enderror
                    <button type="submit" class="van-button" data-submit>Enviar pedido de reserva ↗</button>
                    <p class="van-small vr:mb-0">Sem pagamento online. O envio não garante a reserva da carrinha ou do motorista.</p>
                </form>
            </div>
        </div>
    </div></section>
</main>
@endsection
