<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AB-ERP — Manufaktur Pipa</title>
    {{-- Installable on the shop-floor tablets; see resources/js/pwa.js --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#1e40af">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/main.jsx'])
</head>
<body>
    <div id="root"></div>
</body>
</html>
