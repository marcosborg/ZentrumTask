<section class="container pb-5">
  <div class="row g-4">
    @php
        $fleetItems = collect($fleets ?? []);
        if ($fleetItems->isEmpty()) {
            $fleetItems = \App\Models\Fleet::query()->latest('id')->get();
        }
    @endphp

    <!-- Fleet full width -->
    <div class="col-12">
      <h3 class="mb-3">A nossa frota</h3>
      <!-- fleet-count: {{ $fleetItems->count() }} -->
      <div class="row g-3">
        @forelse($fleetItems as $fleet)
          <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <div class="fleet-card">
              <img
                src="{{ ($fleet->photo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($fleet->photo_path)) ? \Illuminate\Support\Facades\Storage::disk('public')->url($fleet->photo_path) : asset('website/assets/car_sedan.png') }}"
                alt="{{ $fleet->name }}"
              />
              <p>{{ $fleet->name }}</p>
            </div>
          </div>
        @empty
          <div class="col-12 text-light-subtle">Nenhum veículo na frota ainda.</div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="row g-4 mt-4">
    <!-- How it works -->
    <div class="col-lg-4">
      <h3 class="mb-3">Como funciona</h3>
      <div class="steps-list">
        <div class="step-item">
          <div class="step-number">1</div>
          <div class="step-text">Registe-se na plataforma</div>
        </div>
        <div class="step-item">
          <div class="step-number">2</div>
          <div class="step-text">Encontre a viatura ideal</div>
        </div>
        <div class="step-item">
          <div class="step-number">3</div>
          <div class="step-text">Comece a conduzir</div>
        </div>
      </div>
    </div>

    <!-- FAQs side-by-side with How it works -->
    <div class="col-lg-8">
      <h3 class="mb-3">Perguntas frequentes</h3>
      <div class="faq-item">
        <h6>Que documentos são necessários para me tornar motorista?</h6>
        <p>
          Vai precisar do documento de identificação, carta de condução e comprovativo de residência, entre outros.
        </p>
      </div>
      <div class="faq-item">
        <h6>Quais são os requisitos para alugar uma viatura?</h6>
        <p>
          Ter carta de condução válida e cumprir os critérios de idade mínima previstos pela plataforma.
        </p>
      </div>
      <div class="faq-item">
        <h6>Posso utilizar a minha própria viatura como motorista TVDE?</h6>
        <p>
          Sim, desde que a viatura cumpra os requisitos legais e seja registada na plataforma.
        </p>
      </div>
      <div class="faq-item">
        <h6>Qual é o processo para comprar uma viatura?</h6>
        <p>
          Contacte-nos para obter informações sobre a nossa oferta de veículos em venda e as condições de aquisição.
        </p>
      </div>
    </div>
  </div>
</section>
