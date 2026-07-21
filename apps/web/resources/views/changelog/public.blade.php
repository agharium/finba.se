<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#16a34a">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/css/filament/changelog.css'])
    <script>
        (() => {
            const root = document.documentElement;
            const sync = () => {
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    root.classList.add('dark');
                } else {
                    root.classList.remove('dark');
                }
            };
            sync();
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', sync);
        })();
    </script>
</head>
<body class="finba-public-changelog-page min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <header class="finba-public-changelog-header border-b border-zinc-200/80 bg-white/90 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/90">
        <div class="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ url('/') }}" class="finba-public-changelog-brand inline-flex items-center gap-3 no-underline">
                <img
                    src="/images/logo/light.png"
                    alt="Finba.se"
                    class="h-8 w-auto dark:hidden"
                    width="120"
                    height="32"
                >
                <img
                    src="/images/logo/dark.png"
                    alt="Finba.se"
                    class="hidden h-8 w-auto dark:block"
                    width="120"
                    height="32"
                >
                <span class="sr-only">Finba.se</span>
            </a>

            <nav class="flex items-center gap-3 text-sm font-semibold">
                <a href="{{ url('/') }}" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white">
                    Aplicativo
                </a>
            </nav>
        </div>
    </header>

    <main class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 sm:py-10">
        <header class="finba-public-changelog-intro mb-8">
            <p class="mb-2 text-sm font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">
                Finba.se · {{ $displayVersion }} · {{ $stage }}
            </p>
            <h1 class="mb-3 text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-zinc-50">
                Changelog
            </h1>
            <p class="max-w-2xl text-base leading-relaxed text-zinc-600 dark:text-zinc-300">
                Registro público da evolução do produto e da plataforma — do aplicativo Laravel à arquitetura multi-serviço —
                em ordem cronológica (mais recente primeiro).
            </p>
        </header>

        <div class="finba-changelog">
            @include('changelog.partials.entries', ['entries' => $entries])
        </div>
    </main>

    <footer class="border-t border-zinc-200/80 py-6 text-center text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
        <p>
            <a href="{{ url('/') }}" class="font-semibold text-emerald-700 hover:underline dark:text-emerald-400">Voltar ao Finba.se</a>
            ·
            <a href="{{ route('changelog') }}" class="hover:underline">Changelog</a>
        </p>
    </footer>
</body>
</html>
