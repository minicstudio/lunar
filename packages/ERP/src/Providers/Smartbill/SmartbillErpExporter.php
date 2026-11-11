<?php

namespace Lunar\ERP\Providers\Smartbill;

use Lunar\DataTypes\Price;
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

        $isTaxIncluded = (bool) config('lunar.pricing.stored_inclusive_of_tax', false);

        $chargesVat = collect($order->tax_breakdown?->amounts)->contains(fn ($amount) => $amount->percentage > 0);

        $packMeasuringUnitName = (string) (config('lunar.erp.smartbill.pack_measuring_unit_name') ?: 'pachet');
        $invoicePacks = (bool) config('lunar.erp.smartbill.invoice_packs', true);

        $products = $order->productLines
            ->map(function ($productLine) use (
                $measuringUnitName,
                $packMeasuringUnitName,
                $invoicePacks,
                $taxNames,
                $saveToDb,
                $defaultLocale,
                $order,
                $isTaxIncluded,
                $chargesVat,
            ) {
                $variant = $productLine->purchasable;
                $productName = $variant->product->translateAttribute('name', $defaultLocale);
                $options = $variant->values->map(fn ($value) => $value->translate('name', $defaultLocale))->join(', ');
                $displayName = $options ? $productName.' - '.$options : $productName;
                $inPacks = $invoicePacks && $productLine->isInvoicedInPacks();

                return new SmartbillProduct(
                    name: $displayName,
                    code: $productLine->identifier,
                    measuringUnitName: $inPacks ? $packMeasuringUnitName : $measuringUnitName,
                    currency: $order->currency_code,
                    quantity: $inPacks ? $productLine->invoicePackQuantity() : $productLine->quantity,
                    price: $inPacks ? $productLine->invoicePackPrice()->decimal : $productLine->invoiceUnitPrice()->decimal,
                    isTaxIncluded: $isTaxIncluded,
                    taxName: $chargesVat ? $taxNames[(string) ($productLine->taxRate * 100)] : null,
                    taxPercentage: $chargesVat ? $productLine->taxRate * 100 : null,
                    saveToDb: $saveToDb,
                    isService: false,
                    productDescription: $this->packDescription($productLine->packSize(), $packMeasuringUnitName, $measuringUnitName),
                );
            })
            ->values()
            ->all();

        // Apply the default Tax Class rate to shipping when the Default Tax Zone defines a Tax Rate for it.
        // Example: If the Default Tax Class is "Default" and the Default Tax Zone is "Romania" with a 21% tax rate,
        // and the shipping cost is 15 RON with LUNAR_STORE_INCLUSIVE_OF_TAX = false, then the final shipping amount becomes 18.15 RON.
        $defaultTaxPercentage = $chargesVat ? (float) $this->getDefaultTaxRateAmount()?->percentage : 0.0;
        $shippingTaxPercentage = (float) $order->shippingLines?->first()?->tax_breakdown?->amounts?->first()?->percentage;
        $resolvedShippingTaxPercentage = $shippingTaxPercentage ?: $defaultTaxPercentage;

        if ($order->shipping_sub_total->value > 0) {
            $products[] = new SmartbillProduct(
                name: 'Cost de livrare',
                code: 'SHIPPING',
                measuringUnitName: $measuringUnitName,
                currency: $order->currency_code,
                quantity: 1,
                price: $order->shipping_sub_total->decimal,
                isTaxIncluded: $isTaxIncluded,
                taxName: $chargesVat ? $taxNames[(string) $resolvedShippingTaxPercentage] : null,
                taxPercentage: $chargesVat ? $resolvedShippingTaxPercentage : null,
                saveToDb: $saveToDb,
                isService: true,
            );
        }

        $couponTaxPercentage = $this->resolveCouponTaxPercentage($order, $defaultTaxPercentage);

        $appliedCoupon = $order->appliedCoupon;
        if ($appliedCoupon) {
            $products[] = new SmartbillProduct(
                name: 'Cupon de reducere'.($appliedCoupon?->coupon ? ' - '.$appliedCoupon?->coupon : ''),
                code: 'CUPON',
                measuringUnitName: $measuringUnitName,
                currency: $order->currency_code,
                quantity: 1,
                price: -abs($order->coupon_total_without_tax->decimal),
                isTaxIncluded: $isTaxIncluded,
                taxName: $chargesVat ? $taxNames[(string) $couponTaxPercentage] : null,
                taxPercentage: $chargesVat ? $couponTaxPercentage : null,
                saveToDb: $saveToDb,
                isService: true,
            );
        }

        $loyaltyDiscount = (int) ($order->meta['loyalty_discount_minor'] ?? 0);
        if ($loyaltyDiscount > 0) {
            $products[] = new SmartbillProduct(
                name: 'Reducere puncte de fidelitate',
                code: 'LOIALITATE',
                measuringUnitName: $measuringUnitName,
                currency: $order->currency_code,
                quantity: 1,
                price: -(new Price($loyaltyDiscount, $order->currency, 1))->decimal,
                isTaxIncluded: $isTaxIncluded,
                taxName: $chargesVat ? $taxNames[(string) $couponTaxPercentage] : null,
                taxPercentage: $chargesVat ? $couponTaxPercentage : null,
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
            products: $products,
            observations: $this->buildInvoiceObservations($order),
        );
    }

    /**
     * Describe a pack's contents for the invoice line, e.g. "1 pachet = 4 buc".
     *
     * Returns null for lines sold per piece.
     */
    protected function packDescription(int $packSize, string $packMeasuringUnitName, string $measuringUnitName): ?string
    {
        if ($packSize <= 1) {
            return null;
        }

        return "1 {$packMeasuringUnitName} = {$packSize} {$measuringUnitName}";
    }

    /**
     * Resolve the tax percentage for the coupon and loyalty lines from product lines when available.
     */
    protected function resolveCouponTaxPercentage(Order $order, float $defaultTaxPercentage): float
    {
        $productTaxPercentages = $order->productLines
            ->reject(fn ($line) => $line->isComplimentary())
            ->map(fn ($line) => (float) ($line->taxRate * 100))
            ->filter(fn (float $percentage) => $percentage > 0)
            ->unique()
            ->values();

        if ($productTaxPercentages->count() === 1) {
            return (float) $productTaxPercentages->first();
        }

        return $defaultTaxPercentage;
    }

    /**
     * Build the SmartBill invoice observations string: #reference_paymentSlug_shippingSegment.
     */
    protected function buildInvoiceObservations(Order $order): string
    {
        $reference = trim((string) ($order->reference ?? ''));
        $paymentSlug = $this->observationsPaymentSlug($order);
        $shippingSegment = $this->observationsShippingSegment($order);

        return '#'.$reference.'_'.$paymentSlug.'_'.$shippingSegment;
    }

    private function observationsPaymentSlug(Order $order): string
    {
        $paymentOption = $this->observationsMetaString($order, 'payment_option');

        return (new PaymentSlugMapper)($paymentOption);
    }

    /**
     * Shipping segment from the order shipping_breakdown (first item identifier).
     */
    private function observationsShippingSegment(Order $order): string
    {
        $firstItem = $order->shipping_breakdown?->items?->first() ?? null;

        if (! $firstItem) {
            return '';
        }

        return trim((string) $firstItem->identifier ?? '');
    }

    private function observationsMetaString(Order $order, string $key): string
    {
        $meta = $order->meta;

        if ($meta === null) {
            return '';
        }

        if (! isset($meta[$key])) {
            return '';
        }

        $value = $meta[$key];

        return is_string($value) ? trim($value) : '';
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
