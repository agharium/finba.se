@php
    $completedOnboarding = (bool) auth()->user()?->hasCompletedOnboarding();
    $isDashboard = request()->routeIs('filament.admin.pages.dashboard')
        || request()->is('/');
@endphp

<div
    id="finba-pwa-shell"
    class="finba-pwa-shell"
    data-onboarding-completed="{{ $completedOnboarding ? '1' : '0' }}"
    data-is-dashboard="{{ $isDashboard ? '1' : '0' }}"
    x-data="finbaPwaShell()"
    x-cloak
>
    <template x-teleport="body">
        <dialog
            id="finba-pwa-install-modal"
            class="finba-pwa-modal"
            x-ref="installModal"
            @cancel.prevent="closeInstallModal(modalSource === 'proactive')"
        >
            <div class="finba-pwa-modal__panel" role="document">
                <h2 class="finba-pwa-modal__title">Instalar o Finba.se</h2>

                <p class="finba-pwa-modal__description">
                    Adicione o Finba.se à tela inicial para acessar o aplicativo com mais rapidez e usá-lo em uma experiência semelhante a um app instalado.
                </p>

                <ul class="finba-pwa-modal__list">
                    <li>• Acesso rápido pela tela inicial</li>
                    <li>• Interface em modo aplicativo</li>
                    <li>• Atualizações automáticas</li>
                    <li>• Seus dados continuam protegidos na sua conta</li>
                </ul>

                <p class="finba-pwa-modal__note">
                    O Finba.se continua precisando de internet para consultar e salvar seus dados financeiros.
                </p>

                <div class="finba-pwa-modal__actions">
                    <button
                        type="button"
                        class="finba-pwa-modal__button finba-pwa-modal__button--secondary"
                        @click="closeInstallModal(true)"
                    >
                        Agora não
                    </button>

                    <button
                        type="button"
                        class="finba-pwa-modal__button finba-pwa-modal__button--primary"
                        @click="continueInstallation()"
                    >
                        Continuar instalação
                    </button>
                </div>
            </div>
        </dialog>

        <dialog
            id="finba-pwa-ios-modal"
            class="finba-pwa-modal"
            x-ref="iosModal"
            @cancel.prevent="closeIosModal()"
        >
            <div class="finba-pwa-modal__panel" role="document">
                <h2 class="finba-pwa-modal__title">Instalar o Finba.se</h2>

                <ol class="finba-pwa-modal__list">
                    <li>Toque no botão Compartilhar do Safari.</li>
                    <li>Escolha “Adicionar à Tela de Início”.</li>
                    <li>Confirme tocando em “Adicionar”.</li>
                </ol>

                <p class="finba-pwa-modal__note">
                    Depois disso, o Finba.se poderá ser aberto diretamente pela tela inicial.
                </p>

                <div class="finba-pwa-modal__actions">
                    <button
                        type="button"
                        class="finba-pwa-modal__button finba-pwa-modal__button--primary"
                        @click="closeIosModal()"
                    >
                        Entendi
                    </button>
                </div>
            </div>
        </dialog>

        <div
            class="finba-pwa-update"
            x-show="showUpdateBanner"
            x-transition
            role="status"
            aria-live="polite"
        >
            <p class="finba-pwa-update__text">
                Uma nova versão do Finba.se está disponível.
            </p>

            <div class="finba-pwa-update__actions">
                <button
                    type="button"
                    class="finba-pwa-update__button finba-pwa-update__button--secondary"
                    @click="dismissUpdate()"
                >
                    Depois
                </button>

                <button
                    type="button"
                    class="finba-pwa-update__button finba-pwa-update__button--primary"
                    @click="applyUpdate()"
                >
                    Atualizar agora
                </button>
            </div>
        </div>
    </template>
</div>
