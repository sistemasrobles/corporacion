@extends('layouts.sistema')

@section('title', 'Dashboard')
@section('page', 'dashboard')

@section('page-pretitle', 'Resumen')
@section('page-title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')

    <div style="margin-bottom:20px">
        <h2 style="font-size:18px;font-weight:600;color:var(--text)">
            Hola, {{ explode(' ', auth()->user()->name)[0] }} 👋
        </h2>
        <p style="font-size:13px;color:var(--text-muted);margin-top:2px">
            Aquí tienes el resumen del sistema.
        </p>
    </div>

    {{-- Stat cards --}}
    <div class="row col-3">
        <div class="card">
            <div class="stat">
                <div class="stat-icon teal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Órdenes totales</div>
                    <div class="stat-value-row"><span class="stat-value">{{ $stats['orders'] ?? 0 }}</span></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="stat">
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Proveedores</div>
                    <div class="stat-value-row"><span class="stat-value">{{ $stats['suppliers'] ?? 0 }}</span></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="stat">
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Usuarios</div>
                    <div class="stat-value-row"><span class="stat-value">{{ $stats['users'] ?? 0 }}</span></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Flujo de aprobación --}}
    <div class="row col-1" style="margin-top:16px">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Flujo de aprobación</div>
            </div>
            <div class="card-body">
                <div style="display:flex;flex-wrap:wrap;gap:12px">
                    @foreach([
                        ['JA', 'Jefe de Área'],
                        ['AA', 'Auxiliar Administrativo'],
                        ['GA', 'Gerente Administrativo'],
                        ['GF', 'Gerente de Finanzas'],
                        ['AF', 'Auxiliar de Finanzas'],
                        ['UC', 'Usuario Contable'],
                    ] as $i => [$code, $name])
                        <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--bg-surface-secondary);border-radius:var(--radius)">
                            <span class="cell-avatar" style="background:var(--primary)">{{ $i + 1 }}</span>
                            <div>
                                <div class="cell-strong" style="font-size:12px">{{ $code }}</div>
                                <div style="font-size:11px;color:var(--text-muted)">{{ $name }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

@endsection