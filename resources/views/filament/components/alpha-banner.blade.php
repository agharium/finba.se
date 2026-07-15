<div
    class="finba-alpha-banner"
    x-data="{ visible: true }"
    x-init="visible = sessionStorage.getItem('finba_release_banner_dismissed') !== '1'"
    x-show="visible"
    x-cloak
    role="status"
    aria-live="polite"
>
    <div class="finba-alpha-banner__inner">
        <div class="finba-alpha-banner__content">
            <span class="finba-alpha-banner__badge">Beta</span>

            <div class="finba-alpha-banner__copy">
                <p class="finba-alpha-banner__title">
                    Você está utilizando a versão beta do Finba.se.
                </p>

                <div class="finba-alpha-banner__text">
                    <p>Alguns recursos ainda podem evoluir antes da versão estável.</p>
                    <p>
                        Obrigado por participar desta fase do projeto.
                        Seu feedback ajuda diretamente na evolução do aplicativo.
                    </p>
                </div>
            </div>
        </div>

        <div class="finba-alpha-banner__actions">
            <a href="{{ $changelogUrl }}" class="finba-alpha-banner__link">
                Ver Changelog
            </a>

            <a href="{{ $feedbackUrl }}" class="finba-alpha-banner__link">
                Enviar Feedback
            </a>

            <button
                type="button"
                class="finba-alpha-banner__dismiss"
                x-on:click="sessionStorage.setItem('finba_release_banner_dismissed', '1'); visible = false"
                aria-label="Fechar aviso beta"
            >
                <x-filament::icon icon="heroicon-m-x-mark" class="finba-alpha-banner__dismiss-icon" />
            </button>
        </div>
    </div>
</div>
