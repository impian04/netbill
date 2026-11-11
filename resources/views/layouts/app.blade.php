<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — NetBill Admin</title>
    <meta name="description" content="NetBill — Sistem manajemen tagihan pelanggan internet.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

    {{-- Flash data container untuk otomatis ditangkap oleh SweetAlert2 Toast --}}
    @php
        $flashType = session('success') ? 'success' : (session('error') ? 'error' : (session('warning') ? 'warning' : ''));
        $flashMsg = session('success') ?? session('error') ?? session('warning') ?? '';
        $activationCode = session('activation_code_display') ?? '';
        $customerName = session('activation_customer_name') ?? '';
    @endphp
    <div id="flash-data" class="d-none"
         data-type="{{ $flashType }}"
         data-message="{{ $flashMsg }}"
         data-activation-code="{{ $activationCode }}"
         data-customer-name="{{ $customerName }}"></div>

    {{-- Sidebar --}}
    @include('layouts.partials.sidebar')

    {{-- Topbar --}}
    @include('layouts.partials.topbar')

    {{-- Main content --}}
    <main id="main-content">
        <div class="container-fluid p-0">
            @yield('content')
        </div>
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
