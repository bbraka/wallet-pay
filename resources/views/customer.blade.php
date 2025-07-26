<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - Customer</title>
    @vite(['resources/sass/app.scss', 'resources/js/customer/App.jsx'])
</head>
<body>
    <div id="customer-app"></div>
</body>
</html>