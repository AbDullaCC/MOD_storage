@props(['name'])
@php
    $paths = [
        'box' => 'm12 3 9 5-9 5-9-5 9-5Zm-9 5v9l9 5 9-5V8M12 13v9M7.5 5.5l9 5',
        'chart' => 'M4 3v17h17M8 15v-4m5 4V7m5 8v-5',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
        'key' => 'm15 7 2 2m-7.5 4.5L3 20v2h3l1-2h2l2-2v-2l1.5-1.5M21 7a5 5 0 1 1-10 0 5 5 0 0 1 10 0Z',
        'logout' => 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5M21 12H9',
        'history' => 'M3 11a9 9 0 1 1 2.5 7M3 4v7h7m2-4v5l3 2',
        'edit' => 'm16 3 5 5M4 16 16 4a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z',
        'cancel' => 'M9 9l6 6m0-6-6 6m12-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'close' => 'm6 6 12 12M6 18 18 6',
        'archive' => 'M3 3h18v5H3V3Zm2 5v13h14V8m-10 4h6',
        'arrow' => 'M5 12h14m-6-6 6 6-6 6',
        'chevron' => 'm6 9 6 6 6-6',
        'clock' => 'M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'shield' => 'm12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Zm-4 9 3 3 5-5',
        'plus' => 'M12 5v14M5 12h14',
        'download' => 'M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5',
        'search' => 'm21 21-5-5m2-6a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z',
        'check' => 'm5 12 4 4L19 6',
    ];
@endphp
<svg {{ $attributes->class(['ui-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $paths[$name] ?? $paths['box'] }}"/></svg>
