<button
    type="button"
    class="finba-pwa-install"
    x-data="finbaPwaInstallButton()"
    x-show="visible"
    x-cloak
    x-on:click="open()"
    aria-label="Instalar aplicativo"
    title="Instalar aplicativo"
>
    <svg class="finba-pwa-install__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <path d="M10 3v9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
        <path d="M6.5 8.5L10 12l3.5-3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M4 15.5h12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
    </svg>

    <span class="finba-pwa-install__label">Instalar aplicativo</span>
</button>
