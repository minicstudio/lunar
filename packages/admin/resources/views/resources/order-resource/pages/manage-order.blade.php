<x-filament-panels::page>
    <style>
        .split-order-lines .fi-fo-repeater-item {
            background-color: transparent !important;
            box-shadow: none !important;
            border-radius: 0;
        }

        .split-order-lines .fi-fo-repeater-item-content {
            padding: 0;
        }

        .split-order-lines > .fi-fo-repeater-items {
            gap: 0;
        }

        .split-order-lines > .fi-fo-repeater-items > .fi-fo-repeater-item + .fi-fo-repeater-item {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgb(3 7 18 / 0.1);
        }

        .dark .split-order-lines > .fi-fo-repeater-items > .fi-fo-repeater-item + .fi-fo-repeater-item {
            border-top-color: rgb(255 255 255 / 0.12);
        }

        .split-order-lines .split-price-toggle.fi-btn-group {
            display: inline-flex;
            width: max-content;
            max-width: 100%;
            align-items: center;
            gap: 0.125rem;
            padding: 0.125rem;
            border: 1px solid rgb(209 213 219);
            border-radius: 9999px;
            background: #fff;
            box-shadow: none !important;
        }

        .dark .split-order-lines .split-price-toggle.fi-btn-group {
            border-color: rgb(75 85 99);
            background: rgb(17 24 39);
        }

        .split-order-lines .split-price-toggle .fi-btn {
            flex: none !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0.2rem 0.7rem !important;
            border: 0 !important;
            border-radius: 9999px !important;
            background: transparent !important;
            box-shadow: none !important;
            color: rgb(17 24 39) !important;
            font-size: 0.75rem !important;
            font-weight: 600;
            line-height: 1rem !important;
        }

        .dark .split-order-lines .split-price-toggle .fi-btn {
            color: rgb(243 244 246) !important;
        }

        .split-order-lines .split-price-toggle .fi-btn[aria-pressed="true"] {
            background: #128a72 !important;
            color: #fff !important;
        }

        .split-order-lines .split-price-toggle .fi-btn:not([aria-pressed="true"]):hover {
            background: rgb(243 244 246) !important;
        }

        .dark .split-order-lines .split-price-toggle .fi-btn:not([aria-pressed="true"]):hover {
            background: rgb(255 255 255 / 0.06) !important;
        }

        .split-order-lines .fi-fo-field:has(.split-price-toggle) {
            gap: 0.375rem;
        }

        .split-order-lines .split-price-note {
            display: block;
            margin-bottom: 0.375rem;
        }
    </style>
    {{ $this->content }}
</x-filament-panels::page>