<x-filament-panels::page>
    <div class="min-h-[70vh]" wire:ignore>
        <x-session-replay::player :session="$this->getRecord()" />
    </div>
</x-filament-panels::page>
