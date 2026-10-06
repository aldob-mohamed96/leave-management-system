<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6 max-w-xl">
        {{ $this->form }}

        <x-filament::button type="submit">
            حفظ كلمة المرور الجديدة
        </x-filament::button>
    </form>
</x-filament-panels::page>
