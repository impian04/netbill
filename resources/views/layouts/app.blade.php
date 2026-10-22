<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'NetBill') — NetBill Admin</title>
    <meta name="description" content="NetBill — Sistem manajemen tagihan pelanggan internet.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

    {{-- Sidebar --}}
    @include('layouts.partials.sidebar')

    {{-- Topbar --}}
    @include('layouts.partials.topbar')

    {{-- Main content --}}
    <main id="main-content">
        <div class="flash-area mb-3">
            @include('layouts.partials.flash')
        </div>

        @yield('content')
    </main>

    {{-- Sidebar overlay (mobile) --}}
    <div id="sidebar-overlay" class="d-lg-none" style="display:none!important;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1039;" onclick="closeSidebar()"></div>

    <script>
        function openSidebar()  { document.getElementById('sidebar').classList.add('show'); document.getElementById('sidebar-overlay').style.removeProperty('display'); }
        function closeSidebar() { document.getElementById('sidebar').classList.remove('show'); document.getElementById('sidebar-overlay').style.setProperty('display','none','important'); }
    </script>

    @stack('scripts')
</body>
</html>
