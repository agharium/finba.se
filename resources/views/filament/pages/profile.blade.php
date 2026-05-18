<x-filament-panels::page>
    <div class="mx-auto w-full max-w-3xl">
        <x-filament::section>
            <x-slot name="heading">
                Informações do perfil
            </x-slot>

            <x-slot name="description">
                Atualize seus dados básicos e preferências do aplicativo.
            </x-slot>

            <form wire:submit="save" class="space-y-6">
                {{ $this->form }}

                <div class="flex justify-end">
                    <x-filament::button type="submit">
                        Salvar alterações
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </div>
</x-filament-panels::page>