<nav class="navbar navbar-expand-lg py-3 navbar-website bg-white" aria-label="Main navigation">
  <div class="container">
    @php
        $adminPanel = filament()->getPanel('admin');
        $loginUrl = $adminPanel?->getLoginUrl() ?? url('/admin/login');
        $dashboardUrl = $adminPanel?->getUrl() ?? url('/admin');
        $logoutUrl = $adminPanel?->getLogoutUrl() ?? url('/admin/logout');
        $menuItems = \Illuminate\Support\Facades\Schema::hasTable('website_menu_items')
            ? \App\Models\WebsiteMenuItem::query()
                ->with(['children'])
                ->whereNull('parent_id')
                ->orderBy('position')
                ->get(['id', 'label', 'url', 'position'])
            : collect();
        $blogUrl = route('blog.index');
        if ($menuItems->isNotEmpty() && ! $menuItems->contains(fn ($item) => $item->url === $blogUrl)) {
            $menuItems->push((object) [
                'id' => 'blog',
                'label' => 'Noticias',
                'url' => $blogUrl,
                'children' => collect(),
            ]);
        }
        $linkColor = '#000000';
        $hoverColor = '#2A66B5';
    @endphp
    <a class="navbar-brand d-flex align-items-center gap-2" href="{{ url('/') }}">
      <img src="/website/assets/logo.svg" alt="Zentrum TVDE" class="logo" />
    </a>
    <button
      class="navbar-toggler border-0 shadow-none"
      type="button"
      data-bs-toggle="collapse"
      data-bs-target="#navbarNav"
      aria-controls="navbarNav"
      aria-expanded="false"
      aria-label="Abrir menu"
    >
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav align-items-lg-center gap-lg-3">
        <li class="nav-item"><a class="nav-link nav-link-custom" href="{{ route('vehicle.index') }}">Aluguer</a></li>
        <li class="nav-item"><a class="nav-link nav-link-custom" href="{{ route('slot.show') }}">SLOT</a></li>
        <li class="nav-item"><a class="nav-link nav-link-custom" href="{{ route('van-rentals.index') }}">Carrinhas</a></li>
        <li class="nav-item dropdown">
          <button class="nav-link dropdown-toggle nav-link-custom" id="navbarMoreDropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">Mais</button>
          <ul class="dropdown-menu dropdown-menu-end website-more-menu" aria-labelledby="navbarMoreDropdown">
            @forelse ($menuItems as $item)
              @continue($item->children->isEmpty() && (in_array(rtrim(parse_url($item->url ?? '', PHP_URL_PATH) ?? '', '/'), ['/frota', '/slot', '/aluguer-carrinhas'], true) || parse_url($item->url ?? '', PHP_URL_FRAGMENT) === 'aluguer'))
              @if ($item->children->isNotEmpty())
                <li><h6 class="dropdown-header">{{ $item->label }}</h6></li>
                @foreach ($item->children as $child)
                  <li><a class="dropdown-item nav-link-custom" href="{{ $child->url }}">{{ $child->label }}</a></li>
                @endforeach
              @else
                <li><a class="dropdown-item nav-link-custom" href="{{ $item->url ?? '#' }}">{{ $item->label }}</a></li>
              @endif
            @empty
              <li><a class="dropdown-item nav-link-custom" href="{{ url('/') }}">Início</a></li>
              <li><a class="dropdown-item nav-link-custom" href="{{ $blogUrl }}">Notícias</a></li>
              <li><a class="dropdown-item nav-link-custom" href="{{ url('/').'#contactos' }}">Contactos</a></li>
            @endforelse
          </ul>
        </li>
        @guest
        <li class="nav-item">
          <a class="nav-link nav-link-custom d-flex align-items-center gap-2" href="{{ $loginUrl }}">
            <i class="fa-solid fa-lock"></i>
            <span class="visually-hidden">Login</span>
          </a>
        </li>
      @endguest
        @auth
          <li class="nav-item dropdown">
            <a
              class="nav-link dropdown-toggle nav-link-custom d-flex align-items-center gap-2"
              href="#"
              id="navbarAuthDropdown"
              role="button"
              data-bs-toggle="dropdown"
              aria-expanded="false"
            >
              <i class="fa-solid fa-user-shield"></i>
              <span>Conta</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarAuthDropdown">
              <li>
                <a class="dropdown-item nav-link-custom d-flex align-items-center gap-2" href="{{ $dashboardUrl }}">
                  <i class="fa-solid fa-gauge-high"></i>
                  <span>Dashboard</span>
                </a>
              </li>
              <li>
                <form method="POST" action="{{ $logoutUrl }}">
                  @csrf
                  <button type="submit" class="dropdown-item nav-link-custom d-flex align-items-center gap-2">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>Sair</span>
                  </button>
                </form>
              </li>
            </ul>
          </li>
        @endauth
      </ul>
    </div>
  </div>
</nav>

<style>
  .nav-link-custom {
    color: {{ $linkColor }} !important;
  }

  .nav-link-custom:hover,
  .dropdown-item.nav-link-custom:hover {
    color: {{ $hoverColor }} !important;
  }
</style>
