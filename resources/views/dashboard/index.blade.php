@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

<div class="space-y-6">

    {{-- Header --}}
    <div>
        <h2 class="text-xl font-semibold text-gray-900">
            Hola, {{ explode(' ', auth()->user()->name)[0] }}
        </h2>
        <p class="text-sm text-gray-500 mt-0.5">Aquí tienes el resumen del sistema.</p>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <x-stat-card label="Órdenes totales" :value="$stats['orders']" icon="document" color="indigo"/>
        <x-stat-card label="Proveedores"      :value="$stats['suppliers']" icon="truck" color="emerald"/>
        <x-stat-card label="Usuarios"         :value="$stats['users']" icon="users" color="sky"/>
    </div>

    {{-- Info cards --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Flujo de aprobación</h3>
            <ol class="space-y-3">
                @foreach([
                    ['JA', 'Jefe de Área', 'Crea la solicitud'],
                    ['AA', 'Auxiliar Administrativo', 'Primera revisión'],
                    ['GA', 'Gerente Administrativo', 'Aprobación gerencial'],
                    ['GF', 'Gerente de Finanzas', 'Aprobación financiera'],
                    ['AF', 'Auxiliar de Finanzas', 'Procesamiento de pago'],
                    ['UC', 'Usuario Contable', 'Cierre contable'],
                ] as $i => [$code, $name, $action])
                <li class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center flex-shrink-0 mt-0.5">
                        {{ $i + 1 }}
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-gray-900">{{ $code }}</span>
                        <span class="text-xs text-gray-500"> — {{ $name }}</span>
                        <p class="text-xs text-gray-400">{{ $action }}</p>
                    </div>
                </li>
                @endforeach
            </ol>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Tipos de documento</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center">OC</span>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Orden de Compra</p>
                            <p class="text-xs text-gray-500">Bienes y materiales</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center">OS</span>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Orden de Servicio</p>
                            <p class="text-xs text-gray-500">Servicios y consultoría</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-500">Tu rol actual</p>
                <div class="mt-2 flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-sm font-semibold bg-indigo-600 text-white">
                        {{ auth()->user()->user_type }}
                    </span>
                    <span class="text-sm text-gray-700">
                        {{ auth()->user()->userType->description ?? '' }}
                    </span>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection
