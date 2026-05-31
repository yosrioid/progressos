<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'ProgressOS') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    </head>
    <body class="bg-stone-50 text-zinc-950 antialiased dark:bg-zinc-950 dark:text-zinc-50">
        <div id="app"></div>
    </body>
</html>
