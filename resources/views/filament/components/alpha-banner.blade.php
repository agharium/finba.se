<div
    class="finba-alpha-banner"
    x-data="{ visible: true }"
    x-init="visible = sessionStorage.getItem('finba_alpha_banner_dismissed') !== '1'"
    x-show="visible"
    x-cloak
    role="status"
    aria-live="polite"
>
    <div class="finba-alpha-banner__inner">
        <div class="finba-alpha-banner__content">
            <span class="finba-alpha-banner__badge">Alfa</span>

            <p class="finba-alpha-banner__text">
                Finba.se está em fase alfa. Alguns recursos ainda estão sendo preparados para a primeira versão beta.
            </p>
        </div>

        <div class="finba-alpha-banner__actions">
            <a href="{{ $changelogUrl }}" class="finba-alpha-banner__link">
                Ver changelog
            </a>

            <button
                type="button"
                class="finba-alpha-banner__dismiss"
                x-on:click="sessionStorage.setItem('finba_alpha_banner_dismissed', '1'); visible = false"
                aria-label="Fechar aviso alfa"
            >
                <x-filament::icon icon="heroicon-m-x-mark" class="finba-alpha-banner__dismiss-icon" />
            </button>
        </div>
    </div>
</div>
