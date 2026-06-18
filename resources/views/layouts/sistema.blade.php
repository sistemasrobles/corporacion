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

    {{-- Block UI global (procesando) --}}
    <style>
        .block-ui { position:fixed; inset:0; z-index:5000; display:flex; align-items:center; justify-content:center; background:rgba(15,23,42,.55); -webkit-backdrop-filter:blur(2px); backdrop-filter:blur(2px); }
        .block-ui[hidden] { display:none; }
        .block-ui-card { display:flex; flex-direction:column; align-items:center; gap:14px; background:#fff; padding:26px 40px; border-radius:14px; box-shadow:0 12px 44px rgba(0,0,0,.28); }
        .block-ui-spinner { width:38px; height:38px; border:4px solid #e5e7eb; border-top-color:var(--primary,#16a34a); border-radius:50%; animation:block-spin .8s linear infinite; }
        .block-ui-text { font-size:14px; font-weight:600; color:var(--text,#1e293b); }
        @keyframes block-spin { to { transform:rotate(360deg); } }
    </style>
    <div id="block-ui" class="block-ui" hidden>
        <div class="block-ui-card">
            <span class="block-ui-spinner"></span>
            <span class="block-ui-text" id="block-ui-text">Procesando…</span>
        </div>
    </div>
    <script>
        window.blockUI = (msg) => {
            const el = document.getElementById('block-ui');
            document.getElementById('block-ui-text').textContent = msg || 'Procesando…';
            el.hidden = false; document.body.style.overflow = 'hidden';
        };
        window.unblockUI = () => {
            document.getElementById('block-ui').hidden = true; document.body.style.overflow = '';
        };
    </script>

    @stack('scripts')
</body>
</html>