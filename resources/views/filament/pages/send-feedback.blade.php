<x-filament-panels::page>
    <div
        class="finba-feedback mx-auto w-full max-w-2xl"
        x-data
        x-init="
            const context = {
                screen_width: window.screen?.width ?? null,
                screen_height: window.screen?.height ?? null,
                viewport_width: window.innerWidth ?? null,
                viewport_height: window.innerHeight ?? null,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone ?? null,
                platform: navigator.platform ?? null,
            };

            $wire.set('data.client_context', context);
        "
    >
        <section class="finba-feedback__intro">
            <p>
                Seu relato ajuda a evoluir o Finba. Problemas, ideias e observações são bem-vindos.
            </p>
        </section>

        <form wire:submit="submit" class="finba-feedback__form">
            {{ $this->form }}

            <div class="finba-feedback__actions">
                <x-filament::button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="submit,data.attachment"
                >
                    <span wire:loading.remove wire:target="submit">Enviar feedback</span>
                    <span wire:loading wire:target="submit">Enviando...</span>
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
