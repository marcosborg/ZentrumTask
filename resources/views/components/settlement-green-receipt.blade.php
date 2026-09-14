@php
    $settlement = $getRecord();
    $hasReceipt = filled($settlement->green_receipt_path);
@endphp

<div class="flex flex-col items-start gap-2 px-3 py-2 whitespace-nowrap">
    @if ($hasReceipt)
        <x-filament::link
            :href="route('driver-settlements.green-receipt.download', ['driverSettlement' => $settlement, 'preview' => 1])"
            target="_blank"
            rel="noopener noreferrer"
            icon="heroicon-o-document-text"
            color="success"
        >
            Abrir recibo
        </x-filament::link>
    @endif

    <x-filament::link
        tag="button"
        type="button"
        wire:click="mountTableAction('manageGreenReceipt', '{{ $settlement->getKey() }}')"
        wire:loading.attr="disabled"
        icon="heroicon-o-arrow-up-tray"
        :color="$hasReceipt ? 'gray' : 'primary'"
    >
        {{ $hasReceipt ? 'Substituir PDF' : 'Carregar PDF' }}
    </x-filament::link>
</div>
