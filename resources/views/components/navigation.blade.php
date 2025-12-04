<nav class="navbar navbar-expand-lg py-3 navbar-website" aria-label="Main navigation">
  <div class="container">
    @php
        $adminPanel = filament()->getPanel('admin');
        $loginUrl = $adminPanel?->getLoginUrl() ?? url('/admin/login');
        $dashboardUrl = $adminPanel?->getUrl() ?? url('/admin');
        $logoutUrl = $adminPanel?->getLogoutUrl() ?? url('/admin/logout');
        $menuItems = \App\Models\WebsiteMenuItem::query()
            ->with(['children'])
            ->whereNull('parent_id')
            ->orderBy('position')
            ->get(['id', 'label', 'url', 'position']);
    @endphp
    <a class="navbar-brand d-flex align-items-center gap-2" href="#home">
      <img src="/website/assets/logo.png" alt="Zentrum TVDE" class="logo" />
    </a>
    <button
      class="navbar-toggler border-0 shadow-none"
      type="button"
      data-bs-toggle="collapse"
      data-bs-target="#navbarNav"
      aria-controls="navbarNav"
      aria-expanded="false"
      aria-label="Toggle navigation"
    >
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav align-items-lg-center gap-lg-3">
        @forelse ($menuItems as $item)
          @if ($item->children->isNotEmpty())
            <li class="nav-item dropdown">
              <a
                class="nav-link dropdown-toggle"
                href="#"
                id="menuDropdown-{{ $item->id }}"
                role="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
              >
                {{ $item->label }}
              </a>
              <ul class="dropdown-menu" aria-labelledby="menuDropdown-{{ $item->id }}">
                @foreach ($item->children as $child)
                  <li>
                    <a class="dropdown-item" href="{{ $child->url }}">{{ $child->label }}</a>
                  </li>
                @endforeach
              </ul>
            </li>
          @else
            <li class="nav-item">
              <a class="nav-link" href="{{ $item->url ?? '#' }}">{{ $item->label }}</a>
            </li>
          @endif
        @empty
          <li class="nav-item">
            <a class="nav-link" href="#home">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#aluguer">Aluguer de viaturas</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#contactos">Contactos</a>
          </li>
        @endforelse
        @guest
        <li class="nav-item">
          <a class="nav-link d-flex align-items-center gap-2" href="{{ $loginUrl }}">
            <i class="fa-solid fa-lock"></i>
            <span class="visually-hidden">Login</span>
          </a>
        </li>
      @endguest
        @auth
          <li class="nav-item dropdown">
            <a
              class="nav-link dropdown-toggle d-flex align-items-center gap-2"
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
                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ $dashboardUrl }}">
                  <i class="fa-solid fa-gauge-high"></i>
                  <span>Dashboard</span>
                </a>
              </li>
              <li>
                <form method="POST" action="{{ $logoutUrl }}">
                  @csrf
                  <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
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
