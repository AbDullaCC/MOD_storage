<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — نظام إدارة المخزون</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    @include('partials.app-styles')
</head>
<body class="app-shell">
    @auth
        @include('partials.account-nav')
    @endauth
    <main class="app-content account-content" data-page-content>
        @if(session('success'))
            <div role="status" class="bg-green-100 text-green-800 p-4 mb-5 rounded-lg">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" class="bg-red-100 text-red-800 p-4 mb-5 rounded-lg">
                <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
