<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>User Wallet - Merchant Portal</title>
    
    <!-- App CSS -->
    @vite(['resources/sass/app.scss'])
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div id="merchant-app"></div>
    
    <!-- React App -->
    @vite(['resources/js/merchant/App.jsx'])
</body>
</html>