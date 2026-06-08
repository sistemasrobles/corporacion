{{-- Topbar del sistema (estilo Gentelella) --}}
@php
    $hrefProfile = \Illuminate\Support\Facades\Route::has('profile') ? route('profile') : '#';
@endphp

<header class="topbar">

    <div class="topbar-left">
        <button class="sidebar-toggle" aria-label="Alternar menú" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>

        <div class="breadcrumb">
            <span>Inicio</span>
            @hasSection('breadcrumb')
                <span class="sep">/</span>
                <span class="current">@yield('breadcrumb')</span>
            @endif
        </div>
    </div>

    <div class="topbar-right">

        {{-- Menú de usuario --}}
        <div class="tb-user" style="position:relative">
            <button class="tb-avatar" type="button" title="{{ auth()->user()->name }}" data-user-toggle>
                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
            </button>

            <div class="menu-popover" data-user-menu
                 style="position:absolute;top:calc(100% + 8px);right:0;min-width:200px;display:none">

                {{-- Encabezado del usuario --}}
                <div style="padding:8px 10px;border-bottom:1px solid var(--border-color-light);margin-bottom:4px">
                    <div class="cell-strong" style="font-size:13px">{{ auth()->user()->name }}</div>
                    <div style="font-size:11.5px;color:var(--text-muted)">{{ auth()->user()->email }}</div>
                </div>

                <a href="{{ $hrefProfile }}" class="menu-item" style="text-decoration:none;display:flex;align-items:center;gap:8px">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Mi perfil
                </a>

                <div class="menu-separator"></div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="menu-item" style="width:100%;display:flex;align-items:center;gap:8px;color:var(--red)">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>

    </div>

</header>

<script>
    (function () {
        const toggle = document.querySelector('[data-user-toggle]');
        const menu   = document.querySelector('[data-user-menu]');
        if (!toggle || !menu) return;

        const isOpen = () => menu.style.display !== 'none';
        const open   = () => { menu.style.display = 'flex'; };
        const close  = () => { menu.style.display = 'none'; };

        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            isOpen() ? close() : open();
        });

        // Cerrar al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (isOpen() && !menu.contains(e.target) && e.target !== toggle) {
                close();
            }
        });

        // Cerrar con Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') close();
        });
    })();
</script>