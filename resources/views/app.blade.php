<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'ProgressOS') }}</title>

        <meta name="csrf-token" content="{{ csrf_token() }}">

        @fonts

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    </head>
    <body class="bg-white dark:bg-[#0a0a0a] text-[#1b1b18]">
        <div id="app"></div>
    </body>
</html>
