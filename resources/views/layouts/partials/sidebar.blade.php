{{-- Sidebar del sistema (estilo Gentelella) --}}
@php
    $user = auth()->user();
    $rol  = $user->user_type ?? '';
    // Helper: devuelve la URL de una ruta si existe, si no '#'
    $href = fn ($name) => \Illuminate\Support\Facades\Route::has($name) ? route($name) : '#';

    // ¿Algún hijo del árbol está activo? (para abrirlo y marcarlo)
    $ordenesActivo  = request()->routeIs('orders.view') || request()->routeIs('orders.history');
    $cuentasActivo  = request()->routeIs('orders.payable') || request()->routeIs('orders.payments');
    $provActivo     = request()->routeIs('suppliers.*');
    $reqActivo      = request()->routeIs('requirements.*');
@endphp

<aside class="sidebar">

    <div class="sidebar-brand">
        <div class="brand-icon">{{ strtoupper(substr(config('app.name'), 0, 1)) }}</div>
        <div class="brand-name">{{ config('app.name') }}</div>
    </div>

    <nav class="sidebar-nav">

        <div class="nav-group">
            <div class="nav-label">General</div>
            <a href="{{ $href('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span class="nav-text">Dashboard</span>
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-label">Gestión</div>

            {{-- Árbol: Órdenes --}}
            <div class="nav-tree {{ $ordenesActivo ? 'open has-active' : '' }}">
                <button type="button" class="nav-link nav-toggle">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4m-21 2v12a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2 2z"/></svg>
                    <span class="nav-text">Órdenes</span>
                    <svg class="nav-chev" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 4l4 4-4 4"/></svg>
                </button>
                <div class="nav-sub">
                    <div class="nav-sub-inner">
                        <a href="{{ $href('orders.view') }}" class="nav-sublink {{ request()->routeIs('orders.view') ? 'active' : '' }}">
                            <span class="nav-text">Mis Órdenes</span>
                        </a>
                        <a href="{{ $href('orders.history') }}" class="nav-sublink {{ request()->routeIs('orders.history') ? 'active' : '' }}">
                            <span class="nav-text">Órdenes Históricas</span>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Árbol: Cuentas por Pagar (roles del ciclo de pago) --}}
            @if (in_array($rol, ['AA', 'UC2', 'GF', 'AF'], true))
            <div class="nav-tree {{ $cuentasActivo ? 'open has-active' : '' }}">
                <button type="button" class="nav-link nav-toggle">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                    <span class="nav-text">Cuentas por Pagar</span>
                    <svg class="nav-chev" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 4l4 4-4 4"/></svg>
                </button>
                <div class="nav-sub">
                    <div class="nav-sub-inner">
                        <a href="{{ $href('orders.payable') }}" class="nav-sublink {{ request()->routeIs('orders.payable') ? 'active' : '' }}">
                            <span class="nav-text">Cuentas por Pagar</span>
                        </a>
                        @if (in_array($rol, ['GF', 'AF'], true))
                            <a href="{{ $href('orders.payments') }}" class="nav-sublink {{ request()->routeIs('orders.payments') ? 'active' : '' }}">
                                <span class="nav-text">Histórico de Pagos</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- Árbol: Requerimientos (roles del flujo) --}}
            @if (in_array($rol, ['AA', 'GA', 'GF', 'AF', 'UC1'], true))
            <div class="nav-tree {{ $reqActivo ? 'open has-active' : '' }}">
                <button type="button" class="nav-link nav-toggle">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                    <span class="nav-text">Requerimientos</span>
                    <svg class="nav-chev" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 4l4 4-4 4"/></svg>
                </button>
                <div class="nav-sub">
                    <div class="nav-sub-inner">
                        <a href="{{ $href('requirements.index') }}" class="nav-sublink {{ request()->routeIs('requirements.index') ? 'active' : '' }}">
                            <span class="nav-text">Órdenes de Requerimientos</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif

            {{-- Árbol: Proveedores --}}
            <div class="nav-tree {{ $provActivo ? 'open has-active' : '' }}">
                <button type="button" class="nav-link nav-toggle">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/></svg>
                    <span class="nav-text">Proveedores</span>
                    <svg class="nav-chev" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 4l4 4-4 4"/></svg>
                </button>
                <div class="nav-sub">
                    <div class="nav-sub-inner">
                        <a href="{{ $href('suppliers.index') }}" class="nav-sublink {{ request()->routeIs('suppliers.index') ? 'active' : '' }}">
                            <span class="nav-text">Proveedores</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>

    </nav>

    {{-- Usuario + logout --}}
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar">
                {{ strtoupper(substr($user->name, 0, 2)) }}
                <span class="online"></span>
            </div>
            <div class="sidebar-user-info">
                <div class="name">{{ $user->name }}</div>
                <div class="role">{{ $user->userType->description ?? $rol }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="more-btn" title="Cerrar sesión" style="opacity:1">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </button>
            </form>
        </div>
    </div>

</aside>

<script>
    {{-- Expandir/colapsar árboles del sidebar --}}
    (function () {
        document.querySelectorAll('.nav-tree > .nav-toggle').forEach((toggle) => {
            toggle.addEventListener('click', () => {
                toggle.closest('.nav-tree').classList.toggle('open');
            });
        });
    })();
</script>