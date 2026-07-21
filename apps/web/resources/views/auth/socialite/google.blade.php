<x-filament::button
    :href="route('socialite.redirect', 'google')"
    tag="a"
    color="gray"
    :spa-mode="false"
    wire:navigate.ignore
    style="margin-top: -0.5rem"
>
    <svg
        width="25"
        height="20"
        viewBox="0 0 533.5 544.3"
        xmlns="http://www.w3.org/2000/svg"
        class="mr-2"
        aria-hidden="true"
    >
        <path fill="#4285f4" d="M533.5 278.4c0-18.5-1.5-37.1-4.7-55.3H272.1v104.8h147c-6.1 33.8-25.7 63.7-54.4 82.7v68h87.9c51.5-47.4 80.9-117.4 80.9-200.2z"/>
        <path fill="#34a853" d="M272.1 544.3c73.7 0 135.8-24.2 181.1-65.7l-87.9-68c-24.4 16.6-55.9 26.2-93.2 26.2-71.6 0-132.3-48.3-154-113.2H27.4v70.9c46.1 91.7 140 149.8 244.7 149.8z"/>
        <path fill="#fbbc04" d="M118.1 323.6c-11.5-33.8-11.5-70.4 0-104.2v-70.9H27.4c-38.5 76.7-38.5 167.7 0 244.4l90.7-69.3z"/>
        <path fill="#ea4335" d="M272.1 107.7c38.9-.6 76.5 14 104.9 40.6l78.1-78.1C405.6 23.8 339.9-1.7 272.1 0 167.4 0 73.5 58.1 27.4 149.8l90.7 70.9c21.7-64.9 82.4-113 154-113z"/>
    </svg>

    Entrar com Google
</x-filament::button>
