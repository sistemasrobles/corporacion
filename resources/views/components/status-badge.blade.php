@props(['status'])

@php
    $map = [
        0  => ['label' => 'BORRADOR',              'class' => 'bg-gray-100 text-gray-600'],
        1  => ['label' => 'CREADO',                 'class' => 'bg-blue-100 text-blue-700'],
        2  => ['label' => 'POR REVISAR',            'class' => 'bg-yellow-100 text-yellow-700'],
        3  => ['label' => 'APROBADO',               'class' => 'bg-emerald-100 text-emerald-700'],
        4  => ['label' => 'RECHAZADO',              'class' => 'bg-red-100 text-red-700'],
        5  => ['label' => 'OBSERVADO',              'class' => 'bg-orange-100 text-orange-700'],
        6  => ['label' => 'DEPOSITADO',             'class' => 'bg-indigo-100 text-indigo-700'],
        7  => ['label' => 'CONSTANCIA ADJUNTADA',   'class' => 'bg-violet-100 text-violet-700'],
        8  => ['label' => 'SUSTENTADO',             'class' => 'bg-cyan-100 text-cyan-700'],
        9  => ['label' => 'CONFORME',               'class' => 'bg-teal-100 text-teal-700'],
        10 => ['label' => 'CERRADO',                'class' => 'bg-gray-200 text-gray-700'],
    ];
    $item = $map[$status] ?? ['label' => 'DESCONOCIDO', 'class' => 'bg-gray-100 text-gray-500'];
@endphp

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $item['class'] }}">
    {{ $item['label'] }}
</span>
