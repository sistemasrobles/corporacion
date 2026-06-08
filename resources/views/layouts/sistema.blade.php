<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistema') — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/src/css/gentelella.css">
    @stack('styles')
</head>
<body data-page="@yield('page', '')">

    @include('layouts.partials.topbar')
    @include('layouts.partials.sidebar')

    {{-- Backdrop para móvil --}}
    <div class="sidebar-backdrop" hidden></div>

    <main class="main">
        <div class="page-wrapper">

            {{-- Cabecera de página (opcional) --}}
            @hasSection('page-title')
                <div class="page-header">
                    <div class="page-header-row">
                        <div>
                            @hasSection('page-pretitle')
                                <div class="page-pretitle">@yield('page-pretitle')</div>
                            @endif
                            <h1 class="page-title">@yield('page-title')</h1>
                            @hasSection('page-subtitle')
                                <div class="page-subtitle" style="font-size:13px;color:var(--text-muted);margin-top:4px">@yield('page-subtitle')</div>
                            @endif
                        </div>
                        @hasSection('page-actions')
                            <div class="page-actions">@yield('page-actions')</div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- 👇 Sección dinámica: aquí entra el contenido de cada vista --}}
            @yield('content')

        </div>
    </main>

    {{-- Toggle del sidebar (rail en desktop, drawer en móvil) --}}
    <script>
        (function () {
            const sidebar  = document.querySelector('.sidebar');
            const backdrop = document.querySelector('.sidebar-backdrop');
            const toggle   = document.querySelector('.sidebar-toggle');
            if (!sidebar || !toggle) return;

            const isMobile = () => window.innerWidth < 769;

            const closeMobile = () => {
                sidebar.classList.remove('open');
                document.body.classList.remove('sidebar-open');
                if (backdrop) backdrop.hidden = true;
            };
            const openMobile = () => {
                sidebar.classList.add('open');
                document.body.classList.add('sidebar-open');
                if (backdrop) backdrop.hidden = false;
            };

            toggle.addEventListener('click', () => {
                if (isMobile()) {
                    sidebar.classList.contains('open') ? closeMobile() : openMobile();
                } else {
                    document.body.classList.toggle('sidebar-rail');
                }
            });

            if (backdrop) backdrop.addEventListener('click', closeMobile);
        })();
    </script>

    @stack('scripts')
</body>
</html>