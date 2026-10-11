<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sorting Center Delivery Monitoring - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/js/logistics.js'])
</head>
<body class="font-sans antialiased bg-gray-50">
    <div id="app">
        <delivery-monitoring></delivery-monitoring>
    </div>
</body>
</html>
