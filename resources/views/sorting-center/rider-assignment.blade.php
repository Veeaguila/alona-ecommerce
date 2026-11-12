<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rider Assignment - {{ config('app.name', 'Laravel') }}</title>
    <!-- Fonts matching Admin -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/js/logistics.js'])
    @include('sorting-center.session')
</head>
<body class="font-sans antialiased bg-[#F8FAF9] text-[#1F2937]">
    <div id="app">
        <rider-assignment></rider-assignment>
    </div>
</body>
</html>
