const INSTALL_SUGGESTION_KEY = 'finba_pwa_install_suggestion_dismissed';
const UPDATE_DISMISS_KEY = 'finba_pwa_update_prompt_dismissed';

const state = {
    deferredPrompt: null,
    installAvailable: false,
    iosGuideAvailable: false,
    installed: false,
    updateWaiting: false,
    registration: null,
};

function dispatch(name, detail = {}) {
    window.dispatchEvent(new CustomEvent(name, { detail: { ...getPublicState(), ...detail } }));
}

function getPublicState() {
    return {
        canInstall: canInstall(),
        isInstalled: isInstalled(),
        installAvailable: state.installAvailable,
        iosGuideAvailable: state.iosGuideAvailable,
        updateWaiting: state.updateWaiting,
    };
}

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function isIosDevice() {
    const ua = window.navigator.userAgent || '';

    return /iPhone|iPad|iPod/i.test(ua)
        || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
}

function isLikelyIosSafari() {
    if (! isIosDevice() || isStandalone()) {
        return false;
    }

    const ua = window.navigator.userAgent || '';

    if (/CriOS|FxiOS|EdgiOS|OPiOS|OPT\/|Instagram|FBAN|FBAV|Line\//i.test(ua)) {
        return false;
    }

    return /Safari/i.test(ua);
}

function isInstalled() {
    return state.installed || isStandalone();
}

function canInstall() {
    if (isInstalled()) {
        return false;
    }

    return state.installAvailable || state.iosGuideAvailable;
}

function syncInstalledState() {
    const wasInstalled = state.installed;
    const installed = isStandalone();

    state.installed = installed;

    if (installed) {
        state.deferredPrompt = null;
        state.installAvailable = false;
        state.iosGuideAvailable = false;

        if (! wasInstalled) {
            dispatch('finba:pwa-installed');
        }
    } else if (isLikelyIosSafari()) {
        state.iosGuideAvailable = true;
    }

    dispatch('finba:pwa-state-changed');
}

async function registerServiceWorker() {
    if (! ('serviceWorker' in navigator)) {
        return null;
    }

    try {
        const registration = await navigator.serviceWorker.register('/service-worker.js', {
            scope: '/',
        });

        state.registration = registration;
        bindUpdateDetection(registration);

        return registration;
    } catch (error) {
        console.warn('[FinbaPwa] Service worker registration failed.', error);

        return null;
    }
}

function bindUpdateDetection(registration) {
    if (registration.waiting) {
        markUpdateWaiting(true);
    }

    registration.addEventListener('updatefound', () => {
        const worker = registration.installing;

        if (! worker) {
            return;
        }

        worker.addEventListener('statechange', () => {
            if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                markUpdateWaiting(true);
            }
        });
    });
}

function markUpdateWaiting(waiting) {
    state.updateWaiting = waiting;

    if (waiting && sessionStorage.getItem(UPDATE_DISMISS_KEY) !== '1') {
        dispatch('finba:pwa-update-available');
    }

    dispatch('finba:pwa-state-changed');
}

async function requestInstallation() {
    if (state.iosGuideAvailable || ! state.deferredPrompt) {
        return { outcome: 'unavailable' };
    }

    const promptEvent = state.deferredPrompt;

    state.deferredPrompt = null;
    state.installAvailable = false;
    dispatch('finba:pwa-state-changed');

    promptEvent.prompt();

    const choice = await promptEvent.userChoice;

    if (choice.outcome === 'accepted') {
        state.installed = true;
        dispatch('finba:pwa-installed');
    }

    dispatch('finba:pwa-state-changed');

    return choice;
}

function activateUpdate() {
    const waiting = state.registration?.waiting;

    if (! waiting) {
        return false;
    }

    waiting.postMessage({ type: 'SKIP_WAITING' });

    return true;
}

function dismissInstallSuggestion() {
    sessionStorage.setItem(INSTALL_SUGGESTION_KEY, '1');
}

function isInstallSuggestionDismissed() {
    return sessionStorage.getItem(INSTALL_SUGGESTION_KEY) === '1';
}

function dismissUpdatePrompt() {
    sessionStorage.setItem(UPDATE_DISMISS_KEY, '1');
    dispatch('finba:pwa-state-changed');
}

function bindBeforeInstallPrompt() {
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();

        if (isInstalled()) {
            return;
        }

        state.deferredPrompt = event;
        state.installAvailable = true;
        state.iosGuideAvailable = false;

        dispatch('finba:pwa-install-available');
        dispatch('finba:pwa-state-changed');
    });
}

function bindAppInstalled() {
    window.addEventListener('appinstalled', () => {
        state.deferredPrompt = null;
        state.installAvailable = false;
        state.iosGuideAvailable = false;
        state.installed = true;

        dispatch('finba:pwa-installed');
        dispatch('finba:pwa-state-changed');
    });
}

function bindDisplayModeChanges() {
    const media = window.matchMedia('(display-mode: standalone)');
    const handler = () => syncInstalledState();

    if (typeof media.addEventListener === 'function') {
        media.addEventListener('change', handler);
    } else if (typeof media.addListener === 'function') {
        media.addListener(handler);
    }
}

function bindControllerChangeReload() {
    let refreshing = false;

    navigator.serviceWorker?.addEventListener('controllerchange', () => {
        if (refreshing) {
            return;
        }

        refreshing = true;
        window.location.reload();
    });
}

function registerAlpineComponents() {
    if (! window.Alpine || window.__finbaPwaAlpineRegistered) {
        return;
    }

    window.__finbaPwaAlpineRegistered = true;

    window.Alpine.data('finbaPwaInstallButton', () => ({
        visible: false,

        init() {
            const sync = () => {
                const pwa = window.FinbaPwa;
                this.visible = Boolean(pwa?.canInstall?.() && ! pwa?.isInstalled?.());
            };

            window.addEventListener('finba:pwa-state-changed', sync);
            window.addEventListener('finba:pwa-install-available', sync);
            window.addEventListener('finba:pwa-installed', sync);
            sync();
        },

        open() {
            const shell = document.querySelector('#finba-pwa-shell');
            const data = shell && window.Alpine ? window.Alpine.$data(shell) : null;

            if (data?.openInstallFlow) {
                data.openInstallFlow('manual');
            }
        },
    }));

    window.Alpine.data('finbaPwaShell', () => ({
        showUpdateBanner: false,
        proactiveTimer: null,
        modalSource: 'manual',
        lastTrigger: null,

        init() {
            const root = this.$el;

            window.addEventListener('finba:pwa-update-available', () => {
                if (sessionStorage.getItem(UPDATE_DISMISS_KEY) === '1') {
                    return;
                }

                this.showUpdateBanner = true;
            });

            window.addEventListener('finba:pwa-installed', () => {
                this.closeInstallModal(false);
                this.closeIosModal();
            });

            window.addEventListener('finba:pwa-install-available', () => {
                this.maybeSuggestInstallation(root);
            });

            window.addEventListener('finba:pwa-state-changed', () => {
                this.maybeSuggestInstallation(root);
            });

            this.maybeSuggestInstallation(root);
        },

        maybeSuggestInstallation(root) {
            clearTimeout(this.proactiveTimer);

            const pwa = window.FinbaPwa;

            if (! pwa || ! pwa.canInstall() || pwa.isInstalled()) {
                return;
            }

            if (root.dataset.onboardingCompleted !== '1' || root.dataset.isDashboard !== '1') {
                return;
            }

            if (isInstallSuggestionDismissed()) {
                return;
            }

            this.proactiveTimer = setTimeout(() => {
                if (this.hasBlockingModal()) {
                    return;
                }

                if (! window.FinbaPwa?.canInstall() || window.FinbaPwa?.isInstalled()) {
                    return;
                }

                if (isInstallSuggestionDismissed()) {
                    return;
                }

                this.openInstallFlow('proactive');
            }, 4000);
        },

        hasBlockingModal() {
            return Boolean(
                document.querySelector('.fi-modal-open')
                || document.querySelector('.fi-modal.fi-open')
                || document.querySelector('dialog[open].finba-pwa-modal')
                || document.querySelector('[aria-modal="true"]:not(.finba-pwa-modal)'),
            );
        },

        openInstallFlow(source = 'manual') {
            this.modalSource = source;
            this.lastTrigger = document.activeElement instanceof HTMLElement
                ? document.activeElement
                : document.querySelector('.finba-pwa-install');

            if (window.FinbaPwa?.isIosGuide?.()) {
                this.$refs.iosModal?.showModal();
                return;
            }

            this.$refs.installModal?.showModal();
        },

        restoreFocus() {
            queueMicrotask(() => {
                const target = this.lastTrigger instanceof HTMLElement
                    ? this.lastTrigger
                    : document.querySelector('.finba-pwa-install');

                target?.focus?.();
            });
        },

        closeInstallModal(recordDismissal) {
            this.$refs.installModal?.close();

            if (recordDismissal || this.modalSource === 'proactive') {
                dismissInstallSuggestion();
            }

            this.restoreFocus();
        },

        closeIosModal() {
            this.$refs.iosModal?.close();
            dismissInstallSuggestion();
            this.restoreFocus();
        },

        async continueInstallation() {
            this.$refs.installModal?.close();

            if (this.modalSource === 'proactive') {
                dismissInstallSuggestion();
            }

            await window.FinbaPwa?.requestInstallation?.();
            this.restoreFocus();
        },

        dismissUpdate() {
            this.showUpdateBanner = false;
            dismissUpdatePrompt();
        },

        applyUpdate() {
            window.FinbaPwa?.activateUpdate?.();
        },
    }));
}

export function bootPwa() {
    if (window.__finbaPwaBooted) {
        return window.FinbaPwa;
    }

    window.__finbaPwaBooted = true;

    window.FinbaPwa = {
        canInstall,
        isInstalled,
        isIosGuide: () => state.iosGuideAvailable && ! isInstalled(),
        requestInstallation,
        activateUpdate,
        dismissInstallSuggestion,
        isInstallSuggestionDismissed,
        dismissUpdatePrompt,
        getState: getPublicState,
    };

    document.addEventListener('alpine:init', registerAlpineComponents);

    if (window.Alpine) {
        registerAlpineComponents();
    }

    syncInstalledState();
    bindBeforeInstallPrompt();
    bindAppInstalled();
    bindDisplayModeChanges();
    bindControllerChangeReload();
    registerServiceWorker();

    dispatch('finba:pwa-state-changed');

    return window.FinbaPwa;
}

bootPwa();
