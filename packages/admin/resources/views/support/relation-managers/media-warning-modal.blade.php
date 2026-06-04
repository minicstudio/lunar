<x-filament::modal
    id="media-non-webp-warning"
    alignment="center"
    icon="heroicon-o-exclamation-triangle"
    icon-color="warning"
    width="md"
    :heading="__('lunarpanel::relationmanagers.medias.actions.confirm_non_webp.heading')"
    :description="__('lunarpanel::relationmanagers.medias.actions.confirm_non_webp.description')"
>
    <x-slot name="footerActions">
        <x-filament::button
            color="warning"
            wire:click="confirmNonWebpUpload"
            wire:loading.attr="disabled"
            wire:target="confirmNonWebpUpload"
        >
            {{ __('lunarpanel::relationmanagers.medias.actions.confirm_non_webp.confirm') }}
        </x-filament::button>

        <x-filament::button
            color="gray"
            wire:click="cancelNonWebpUpload"
        >
            {{ __('lunarpanel::relationmanagers.medias.actions.confirm_non_webp.cancel') }}
        </x-filament::button>
    </x-slot>
</x-filament::modal>
