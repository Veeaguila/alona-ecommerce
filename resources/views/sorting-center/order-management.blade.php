<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Management - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/js/logistics.js'])
    @include('sorting-center.session')
</head>
<body class="font-sans antialiased bg-gray-50">
    <div id="app">
        <order-management></order-management>
    </div>
</body>
</html>
