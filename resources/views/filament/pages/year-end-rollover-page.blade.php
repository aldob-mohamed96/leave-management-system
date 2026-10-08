<x-filament-panels::page>
    <form wire:submit.prevent>
        {{$this->form}}
    </form>

    <div class="flex gap-3 mt-4">
        <x-filament::button wire:click="dryRun" color="gray" icon="heroicon-o-eye">
            معاينة (بدون حفظ)
        </x-filament::button>
        <x-filament::button wire:click="run" color="warning" icon="heroicon-o-arrow-path"
            wire:confirm="هل أنت متأكد من تجديد الأرصدة؟ لا يمكن التراجع عن هذه العملية.">
            تشغيل التجديد
        </x-filament::button>
    </div>

    @if($this->output)
        <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border">
            <h3 class="font-bold mb-2 text-sm text-gray-600 dark:text-gray-400">نتيجة التنفيذ:</h3>
            <pre class="text-xs whitespace-pre-wrap font-mono">{{$this->output}}</pre>
        </div>
    @endif
</x-filament-panels::page>
