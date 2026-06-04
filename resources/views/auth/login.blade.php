@extends('layouts.auth')

@section('title', 'Iniciar sesión')

@section('content')
<div class="min-h-screen flex">

    {{-- Panel izquierdo --}}
    <div class="hidden lg:flex lg:w-1/2 bg-indigo-600 flex-col justify-between p-12">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <span class="text-white font-semibold text-lg">{{ config('app.name') }}</span>
        </div>

        <div>
            <h2 class="text-4xl font-bold text-white leading-tight">
                Gestiona tus órdenes<br>de forma inteligente.
            </h2>
            <p class="mt-4 text-indigo-200 text-lg leading-relaxed">
                Plataforma centralizada para el control de compras, servicios y flujo de aprobaciones empresariales.
            </p>

            <div class="mt-10 grid grid-cols-3 gap-6">
                <div class="bg-white/10 rounded-xl p-4">
                    <p class="text-2xl font-bold text-white">OC</p>
                    <p class="text-indigo-200 text-sm mt-1">Órdenes de Compra</p>
                </div>
                <div class="bg-white/10 rounded-xl p-4">
                    <p class="text-2xl font-bold text-white">OS</p>
                    <p class="text-indigo-200 text-sm mt-1">Órdenes de Servicio</p>
                </div>
                <div class="bg-white/10 rounded-xl p-4">
                    <p class="text-2xl font-bold text-white">CC</p>
                    <p class="text-indigo-200 text-sm mt-1">Centros de Costo</p>
                </div>
            </div>
        </div>

        <p class="text-indigo-300 text-sm">© {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</p>
    </div>

    {{-- Panel derecho --}}
    <div class="flex-1 flex items-center justify-center px-6 py-12 bg-gray-50">
        <div class="w-full max-w-sm">

            {{-- Logo mobile --}}
            <div class="flex items-center gap-3 mb-8 lg:hidden">
                <div class="w-9 h-9 bg-indigo-600 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                    </svg>
                </div>
                <span class="font-semibold text-gray-900 text-lg">{{ config('app.name') }}</span>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Bienvenido</h1>
                    <p class="text-sm text-gray-500 mt-1">Ingresa tus credenciales para continuar</p>
                </div>

                {{-- Errors --}}
                @if ($errors->any())
                    <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-100">
                        <p class="text-sm text-rose-600">{{ $errors->first() }}</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Correo electrónico
                        </label>
                        <input type="email"
                               id="email"
                               name="email"
                               value="{{ old('email') }}"
                               autocomplete="email"
                               required
                               class="block w-full rounded-lg border-gray-300 shadow-sm text-sm
                                      focus:ring-indigo-500 focus:border-indigo-500
                                      placeholder-gray-400"
                               placeholder="tu@empresa.com">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Contraseña
                        </label>
                        <div class="relative" x-data="{ show: false }">
                            <input :type="show ? 'text' : 'password'"
                                   id="password"
                                   name="password"
                                   autocomplete="current-password"
                                   required
                                   class="block w-full rounded-lg border-gray-300 shadow-sm text-sm pr-10
                                          focus:ring-indigo-500 focus:border-indigo-500"
                                   placeholder="••••••••">
                            <button type="button"
                                    @click="show = !show"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="remember" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                            <span class="text-sm text-gray-600">Recordarme</span>
                        </label>
                    </div>

                    <button type="submit"
                            class="w-full flex justify-center py-2.5 px-4 rounded-lg text-sm font-semibold
                                   bg-indigo-600 text-white hover:bg-indigo-700 focus:outline-none
                                   focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500
                                   transition-colors duration-150">
                        Ingresar al sistema
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
