<footer class="footer-section mt-5 pt-5 pb-4">
  <div class="container">
    <div class="row g-4 align-items-start">
      <div class="col-md-6">
        <div class="d-flex align-items-center gap-2 mb-3">
          <img src="/website/assets/logo.png" alt="Zentrum TVDE" class="footer-logo" />
        </div>
        <p class="text-light-subtle mb-3">
          Ajudamos motoristas e empresas a crescerem no universo TVDE com frota, suporte e tecnologia.
        </p>
        <div class="footer-legal text-light-subtle">
          © 2025 Zentrum TVDE. Todos os direitos reservados.<br />
          Política de Privacidade · Termos e Condições
        </div>
      </div>

      <div class="col-md-6">
        @php
            $footerMenu = \App\Models\WebsiteMenuItem::query()
                ->with(['children'])
                ->whereNull('parent_id')
                ->orderBy('position')
                ->get(['id', 'label', 'url', 'position']);
        @endphp
        <h6 class="footer-title">Menu</h6>
        <ul class="list-unstyled footer-links">
          @forelse ($footerMenu as $item)
            <li class="mb-1">
              @if ($item->url)
                <a href="{{ $item->url }}">{{ $item->label }}</a>
              @else
                <span class="fw-semibold">{{ $item->label }}</span>
              @endif
              @if ($item->children->isNotEmpty())
                <ul class="list-unstyled ps-3 mt-1">
                  @foreach ($item->children as $child)
                    <li class="mb-1">
                      @if ($child->url)
                        <a href="{{ $child->url }}">{{ $child->label }}</a>
                      @else
                        <span class="fw-semibold">{{ $child->label }}</span>
                      @endif
                    </li>
                  @endforeach
                </ul>
              @endif
            </li>
          @empty
            <li><a href="#home">Home</a></li>
            <li><a href="#aluguer">Aluguer de viaturas</a></li>
            <li><a href="#contactos">Contactos</a></li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>
</footer>
