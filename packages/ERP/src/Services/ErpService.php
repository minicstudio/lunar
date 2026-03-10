<?php

namespace Lunar\ERP\Services;

use Lunar\ERP\Contracts\ErpDataExporterInterface;
use Lunar\ERP\Contracts\ErpDataImporterInterface;
use Lunar\ERP\Contracts\SupportsLocalities;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Exceptions\ErpInitializationException;
use Lunar\Models\Order;
use Saloon\Http\Response;

class ErpService
{
    /**
     * Sync products from the specified ERP provider.
     *
     * @throws ErpInitializationException
     */
    public function syncProducts(ErpProviderEnum $provider, ?callable $progressCallback = null): array
    {
        if (! $this->isAllowed('sync', 'products', $provider)) {
            return [
                'success' => false,
                'message' => "Product sync is not enabled for provider [{$provider->value}].",
            ];
        }

        $importer = $this->getImporter($provider);

        return $importer->syncProducts($progressCallback);
    }

    /**
     * Sync orders to the specified ERP provider.
     *
     * @throws ErpInitializationException
     */
    public function syncOrderStatuses(ErpProviderEnum $provider): array
    {
        if (! $this->isAllowed('sync', 'orders', $provider)) {
            return [
                'success' => false,
                'message' => "Order sync is not enabled for provider [{$provider->value}].",
            ];
        }

        $importer = $this->getImporter($provider);

        return $importer->syncOrderStatuses();
    }

    /**
     * Sync stock from the specified ERP provider.
     *
     * @throws ErpInitializationException
     */
    public function syncStock(ErpProviderEnum $provider, ?callable $progressCallback = null): array
    {
        if (! $this->isAllowed('sync', 'stock', $provider)) {
            return [
                'success' => false,
                'message' => "Stock sync is not enabled for provider [{$provider->value}].",
            ];
        }

        $importer = $this->getImporter($provider);

        return $importer->syncStock($progressCallback);
    }

    /**
     * Get enabled ERP providers
     */
    public function getEnabledProviders(): array
    {
        if (! config('lunar.erp.enabled')) {
            return [];
        }

        $providers = config('lunar.erp.providers', []);
        $enabled = [];

        foreach ($providers as $provider) {
            if (config("lunar.erp.{$provider}.enabled", false)) {
                $enabled[] = ErpProviderEnum::from($provider);
            }
        }

        return $enabled;
    }

    /**
     * Send an order to the specified ERP provider.
     */
    public function sendOrder(ErpProviderEnum $provider, Order $order): bool
    {
        if (! $this->isAllowed('actions', 'send_order', $provider)) {
            return false;
        }

        $exporter = $this->getExporter($provider);

        return $exporter->sendOrder($order);
    }

    /**
     * Get localities from the specified ERP provider.
     *
     * @param  ErpProviderEnum|null  $provider
     *
     * @throws ErpInitializationException
     */
    public function getLocalities(ErpProviderEnum $provider): array
    {
        if (! $this->isAllowed('sync', 'localities', $provider)) {
            return [];
        }

        // For now, keep the old way since this is a special provider-specific method
        $erpManager = new ErpManager([$provider->value]);
        $erpProvider = $erpManager->getProvider($provider);

        if (! $erpProvider instanceof SupportsLocalities) {
            return [];
        }

        return $erpProvider->getLocalities();
    }

    /**
     * Get attributes from the specified ERP provider.
     *
     * @param  ErpProviderEnum|null  $provider
     *
     * @throws ErpInitializationException
     */
    public function getAttributes(ErpProviderEnum $provider): array
    {
        if (! $this->isAllowed('sync', 'attributes', $provider)) {
            return [];
        }

        // For now, keep the old way since this is a special provider-specific method
        $erpManager = new ErpManager([$provider->value]);
        $erpProvider = $erpManager->getProvider($provider);

        return $erpProvider->getAttributes();
    }

    /**
     * Get the ERP data importer for the specified provider.
     *
     * @throws ErpInitializationException
     */
    protected function getImporter(ErpProviderEnum $provider): ErpDataImporterInterface
    {
        if (! config('lunar.erp.enabled')) {
            throw new ErpInitializationException('ERP is globally disabled.');
        }

        $key = $provider->value;

        if (! config("lunar.erp.{$key}.enabled", false)) {
            throw new ErpInitializationException("ERP provider [{$key}] is not enabled.");
        }

        $importerClass = config("lunar.erp.{$key}.importer_class");

        if (! $importerClass) {
            throw new ErpInitializationException("ERP provider [{$key}] does not have an importer class configured.");
        }

        return app($importerClass, [
            'client' => app(config("lunar.erp.{$key}.client_class")),
        ]);
    }

    /**
     * Get the ERP data exporter for the specified provider.
     *
     * @throws ErpInitializationException
     */
    protected function getExporter(ErpProviderEnum $provider): ErpDataExporterInterface
    {
        if (! config('lunar.erp.enabled')) {
            throw new ErpInitializationException('ERP is globally disabled.');
        }

        $key = $provider->value;

        if (! config("lunar.erp.{$key}.enabled", false)) {
            throw new ErpInitializationException("ERP provider [{$key}] is not enabled.");
        }

        $exporterClass = config("lunar.erp.{$key}.exporter_class");

        if (! $exporterClass) {
            throw new ErpInitializationException("ERP provider [{$key}] does not have an exporter class configured.");
        }

        return app($exporterClass, [
            'client' => app(config("lunar.erp.{$key}.client_class")),
        ]);
    }

    /**
     * Generate an invoice for the given order.
     */
    public function generateInvoice(ErpProviderEnum $provider, Order $order): void
    {
        if (! $this->isAllowed('actions', 'billing', $provider)) {
            return;
        }

        $exporter = $this->getExporter($provider);

        $response = $exporter->generateInvoice($order);

        // Save series and number to order without triggering events again
        Order::withoutEvents(function () use ($order, $response) {
            $order->meta['billing_series'] = $response['series'];
            $order->meta['billing_number'] = $response['number'];

            $order->save();
        });
    }

    /**
     * Download the invoice PDF for the given order.
     */
    public function downloadInvoicePDF(ErpProviderEnum $provider, Order $order): ?Response
    {
        if (! $this->isAllowed('actions', 'billing', $provider)) {
            return null;
        }

        $exporter = $this->getExporter($provider);

        return $exporter->downloadInvoicePDF($order);
    }

    /**
     * Get the providers for a specific feature.
     */
    protected function providersFor(string $group, string $feature): array
    {
        return (array) config("lunar.erp.{$group}.{$feature}", []);
    }

    /**
     * Check if a provider is allowed for a specific feature.
     */
    protected function isAllowed(string $group, string $feature, ErpProviderEnum $provider): bool
    {
        return in_array($provider->value, $this->providersFor($group, $feature), true);
    }

    /**
     * Return providers that are both enabled AND allowed for the given group/feature.
     *
     * @param  string  $group  e.g. 'sync' or 'actions'
     * @param  string  $feature  e.g. 'products', 'stock', 'billing'
     * @return ErpProviderEnum[]
     */
    public function getAllowedProviders(string $group, string $feature): array
    {
        if (! config('lunar.erp.enabled')) {
            return [];
        }

        $allowed = [];
        foreach ($this->getEnabledProviders() as $provider) {
            if ($this->isAllowed($group, $feature, $provider)) {
                $allowed[] = $provider;
            }
        }

        return $allowed;
    }
}
