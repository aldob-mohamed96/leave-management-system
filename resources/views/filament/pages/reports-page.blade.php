<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        {{-- Filter Form --}}
        <x-filament::section heading="فلترة التقارير">
            <form wire:submit.prevent>
                {{ $this->form }}
            </form>
        </x-filament::section>

        {{-- Export Actions --}}
        <div class="flex gap-3 justify-end">
            <x-filament::button
                wire:click="exportExcel"
                color="success"
                icon="heroicon-o-table-cells"
            >
                تصدير Excel
            </x-filament::button>

            <x-filament::button
                wire:click="exportPdf"
                color="danger"
                icon="heroicon-o-document-arrow-down"
            >
                تصدير PDF
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
