<!DOCTYPE html>
<html lang="pt">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <!-- Google tag (gtag.js) -->
    @production
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag() {
        dataLayer.push(arguments);
      }
      function enableZentrumAnalytics() {
        if (document.getElementById('zentrum-google-tag')) return;
        const tag = document.createElement('script');
        tag.id = 'zentrum-google-tag';
        tag.async = true;
        tag.src = 'https://www.googletagmanager.com/gtag/js?id=AW-17802026959';
        document.head.appendChild(tag);
        gtag('js', new Date());
        gtag('config', 'AW-17802026959');
      }
      if (document.cookie.split(';').some(c => c.trim() === 'zentrum-cookie-consent=accepted')) enableZentrumAnalytics();
      window.addEventListener('zentrum:consent', enableZentrumAnalytics, {once:true});
      document.addEventListener('click', event => {
        if (!document.getElementById('zentrum-google-tag')) return;
        const link = event.target.closest('a');
        if (link?.hostname === 'wa.me') gtag('event','whatsapp_click');
        if (link?.pathname === '/publicidade' && link.hash === '#proposta' && !link.dataset.format) gtag('event','ads_proposal_click');
      });
    </script>
    @endproduction
    <link rel="icon" href="{{ asset('website/assets/favicon.ico') }}" type="image/x-icon" />
    <title>@yield('title', 'Plataforma TVDE')</title>
    <meta property="og:type" content="website" />
    <meta property="og:title" content="@yield('title', 'Plataforma TVDE')" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:image" content="{{ request()->routeIs('ads.*') ? asset('website/assets/ads-tesla.webp') : 'https://zentrum-tvde.com/storage/fleets/01KC4P8BKJX9YAWBRPJ3BBG4AN.png' }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:image" content="{{ request()->routeIs('ads.*') ? asset('website/assets/ads-tesla.webp') : 'https://zentrum-tvde.com/storage/fleets/01KC4P8BKJX9YAWBRPJ3BBG4AN.png' }}" />
    @stack('head')
    <!-- Bootstrap CSS -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    />
    <!-- FontAwesome for icons -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />
    <!-- Custom styles -->
    <link rel="stylesheet" href="{{ asset('website/assets/style.css') }}?v={{ hash_file('sha256', public_path('website/assets/style.css')) }}" />
    @stack('styles')
  </head>
  <body>
    <x-navigation />
    @yield('content')
    <x-footer />
    <x-cookie-banner />

    <!-- Bootstrap JS (optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/website/assets/scripts.js"></script>
    @stack('scripts')
  </body>
</html>
