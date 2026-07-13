<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Memory Vault' }}</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    @yield('content')
    @vite(['resources/js/app.js'])
    @stack('scripts')
</body>
</html>
