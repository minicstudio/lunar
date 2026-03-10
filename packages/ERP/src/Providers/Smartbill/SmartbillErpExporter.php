<?php

namespace Lunar\ERP\Providers\Smartbill;

use Lunar\ERP\Contracts\DtoInterface;
use Lunar\ERP\Contracts\ErpApiClientInterface;
use Lunar\ERP\Contracts\ErpDataExporterInterface;
use Lunar\ERP\Exceptions\FailedErpInvoiceGenerationException;
use Lunar\ERP\Providers\Smartbill\DTOs\SmartbillClient;
use Lunar\ERP\Providers\Smartbill\DTOs\SmartbillInvoiceRequestBody;
use Lunar\ERP\Providers\Smartbill\DTOs\SmartbillPrintRequestQuery;
use Lunar\ERP\Providers\Smartbill\DTOs\SmartbillProduct;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxZone;
use Saloon\Http\Response;

class SmartbillErpExporter implements ErpDataExporterInterface
{
    /**
     * The ERP API client instance.
     */
    protected ErpApiClientInterface $client;

    /**
     * Create a new Smartbill ERP exporter instance.
     */
    public function __construct(ErpApiClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * {@inheritDoc}
     */
    public function sendOrder(Order $order): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function generateInvoice(Order $order): array
    {
        $order->load([
            'billingAddress.country',
            'productLines.purchasable.values',
            'productLines.purchasable.product',
        ]);

        $requestBody = $this->buildInvoiceGenerationRequestBody($order);

        try {
            $response = $this->client->generateInvoice($requestBody);
        } catch (\Throwable $e) {
            throw new FailedErpInvoiceGenerationException('Invoice generation failed: '.$e->getMessage());
        }

        if (! isset($response['series']) || ! isset($response['number'])) {
            $message = $response['errorText'] ?? 'Unknown error';
            throw new FailedErpInvoiceGenerationException("Invoice generation failed: {$message}");
        }

        return $response;
    }

    /**
     * Build the request body for invoice generation.
     */
    protected function buildInvoiceGenerationRequestBody(Order $order): DtoInterface
    {
        $companyVatCode = config('lunar.erp.smartbill.company_vat_code');
        $seriesName = config('lunar.erp.smartbill.series_name');
        $measuringUnitName = config('lunar.erp.smartbill.measuring_unit_name');
        $saveToDb = config('lunar.erp.smartbill.save_to_db');
        $taxNames = config('lunar.erp.smartbill.tax_names');

        $billingAddress = $order->billingAddress;
        $fullName = $billingAddress->first_name.' '.$billingAddress->last_name;
        $name = $billingAddress->company_name ? $billingAddress->company_name : $fullName;
        $defaultLocale = Language::where('default', true)->first()?->code;

        $products = $order->productLines
            ->map(function ($productLine) use (
                $measuringUnitName,
                $taxNames,
                $saveToDb,
                $defaultLocale,
                $order,
            ) {
                $variant = $productLine->purchasable;
                $productName = $variant->product->translateAttribute('name', $defaultLocale);
                $options = $variant->values->map(fn ($value) => $value->translate('name', $defaultLocale))->join(', ');
                $displayName = $options ? $productName.' - '.$options : $productName;

                return new SmartbillProduct(
                    name: $displayName,
                    code: $productLine->identifier,
                    measuringUnitName: $measuringUnitName,
                    currency: $order->currency_code,
                    quantity: $productLine->quantity,
                    price: $productLine->unit_price_without_coupon->decimal,
                    isTaxIncluded: config('lunar.pricing.stored_inclusive_of_tax', false),
                    taxName: $taxNames[(string) ($productLine->taxRate * 100)],
                    taxPercentage: $productLine->taxRate * 100,
                    saveToDb: $saveToDb,
                    isService: false,
                );
            })
            ->values()
            ->all();

        // Apply the default Tax Class rate to shipping when the Default Tax Zone defines a Tax Rate for it.
        // Example: If the Default Tax Class is "Default" and the Default Tax Zone is "Romania" with a 21% tax rate,
        // and the shipping cost is 15 RON with LUNAR_STORE_INCLUSIVE_OF_TAX = false, then the final shipping amount becomes 18.15 RON.
        $defaultTaxPercentage = (float) $this->getDefaultTaxRateAmount()?->percentage;
        $taxPercentage = (float) $order->shippingLines?->first()?->tax_breakdown?->amounts?->first()?->percentage;

        if ($order->shipping_total && $order->shipping_total->decimal > 0) {
            $products[] = new SmartbillProduct(
                name: 'Cost de livrare',
                code: 'SHIPPING',
                measuringUnitName: 'buc',
                currency: $order->currency_code,
                quantity: 1,
                price: $order->shipping_total->decimal,
                isTaxIncluded: $taxPercentage ? true : false,
                taxName: $taxNames[(string) ($taxPercentage ?? $defaultTaxPercentage)],
                taxPercentage: $taxPercentage ?? $defaultTaxPercentage,
                saveToDb: $saveToDb,
                isService: true,
            );
        }

        $appliedCoupon = $order->appliedCoupon;
        if ($appliedCoupon) {
            $products[] = new SmartbillProduct(
                name: 'Cupon de reducere'.($appliedCoupon?->coupon ? ' - '.$appliedCoupon?->coupon : ''),
                code: 'CUPON',
                measuringUnitName: 'buc',
                currency: $order->currency_code,
                quantity: 1,
                price: -abs($order->coupon_total->decimal),
                isTaxIncluded: true,
                taxName: $taxNames[(string) $defaultTaxPercentage],
                taxPercentage: $defaultTaxPercentage,
                saveToDb: $saveToDb,
                isService: true,
            );
        }

        return new SmartbillInvoiceRequestBody(
            companyVatCode: $companyVatCode,
            seriesName: $seriesName,
            client: new SmartbillClient(
                name: $name,
                vatCode: isset($billingAddress->tax_identifier) && $billingAddress->tax_identifier !== '' ? $billingAddress->tax_identifier : '-',
                isTaxPayer: false,
                address: $billingAddress->line_one,
                city: $billingAddress->city,
                county: $billingAddress->state,
                country: $billingAddress->country->name,
                email: $billingAddress->contact_email,
                saveToDb: $saveToDb,
            ),
            products: $products
        );
    }

    /**
     * Get the default tax rate amount.
     */
    protected function getDefaultTaxRateAmount()
    {
        $taxClass = TaxClass::where('default', true)->first();
        $taxZone = TaxZone::where('default', true)->first();

        return $taxClass->taxRateAmounts()
            ->whereIn('tax_rate_id', $taxZone->taxRates->pluck('id'))
            ->with('taxRate')
            ->get()
            ->sortBy(fn ($item) => $item->taxRate->priority)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function downloadInvoicePDF(Order $order): ?Response
    {
        $requestQuery = $this->buildPrintRequestQuery($order);

        return $this->client->downloadInvoicePDF($requestQuery);
    }

    /**
     * Build the print request query.
     */
    protected function buildPrintRequestQuery(Order $order): DtoInterface
    {
        $companyVatCode = config('lunar.erp.smartbill.company_vat_code');

        return new SmartbillPrintRequestQuery(
            series: $order->meta['billing_series'],
            number: $order->meta['billing_number'],
            companyVatCode: $companyVatCode,
        );
    }
}
