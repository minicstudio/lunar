<?php

namespace Lunar\ERP\Providers\Magister;

use Lunar\ERP\Contracts\DtoInterface;
use Lunar\ERP\Contracts\ErpApiClientInterface;
use Lunar\ERP\Exceptions\InvalidErpResponseException;
use Lunar\ERP\Exceptions\SilentException;
use Lunar\ERP\Providers\Magister\Requests\ConfirmReceivingDataRequest;
use Lunar\ERP\Providers\Magister\Requests\GetArticleStockByShop;
use Lunar\ERP\Providers\Magister\Requests\GetAttributesRequest;
use Lunar\ERP\Providers\Magister\Requests\GetLocalitiesRequest;
use Lunar\ERP\Providers\Magister\Requests\GetModifiedDeliveryOrderRequest;
use Lunar\ERP\Providers\Magister\Requests\GetModifiedStockByShopRequest;
use Lunar\ERP\Providers\Magister\Requests\GetNextModifiedArticlesRequest;
use Lunar\ERP\Providers\Magister\Requests\SendOrderRequest;
use Lunar\Models\Order;
use Saloon\Http\Connector;
use Saloon\Http\Response;

class MagisterApiClient extends Connector implements ErpApiClientInterface
{
    /**
     * The Base URL of the API.
     */
    public function resolveBaseUrl(): string
    {
        return config('lunar.erp.magister.base_url');
    }

    /**
     * Default headers for every request.
     */
    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Get next modified articles from Magister ERP.
     */
    public function getProductList(): array
    {
        $request = new GetNextModifiedArticlesRequest;
        $response = $this->send($request);

        if (! $response->successful()) {
            throw new InvalidErpResponseException(
                'Failed to get modified articles: '.$response->body()
            );
        }

        $data = $response->json();

        // Return the full response including RECVERSION for confirmation
        return $data ?? [];
    }

    /**
     * Confirm receiving data by type.
     */
    public function confirmReceivingData(int $typeOf, int $recVersion): array
    {
        $request = new ConfirmReceivingDataRequest($typeOf, $recVersion);
        $response = $this->send($request);

        if (! $response->successful()) {
            throw new InvalidErpResponseException(
                'Failed to confirm receiving data: '.$response->body()
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Get stock information from Magister ERP.
     */
    public function getStock(array $productCodes = []): array
    {
        $request = new GetModifiedStockByShopRequest;
        $response = $this->send($request);

        if (! $response->successful()) {
            throw new InvalidErpResponseException(
                'Failed to get stock information: '.$response->body()
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Get stock information for a specific article from Magister ERP.
     */
    public function getArticleStockByShop(int $articleId): array
    {
        $request = new GetArticleStockByShop($articleId);
        $response = $this->send($request);

        if (! $response->successful()) {
            report(new SilentException('Failed to get stock information for article ID '.$articleId.'.'));
        }

        return $response->json() ?? [];
    }

    /**
     * Send order to Magister ERP.
     */
    public function sendOrder(Order $order): array
    {
        $requestData = $this->prepareOrderData($order);

        $request = new SendOrderRequest($requestData);
        $response = $this->send($request);

        if (! $response->successful()) {
            throw new InvalidErpResponseException(
                'Failed to send order: '.$response->body()
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Prepare order data for sending to Magister ERP.
     */
    private function prepareOrderData(Order $order): array
    {
        // Config values
        $shopId = config('lunar.erp.magister.shop_id');

        $shippingAddress = $order->addresses()->where('type', 'shipping')->first();
        $billingAddress = $order->addresses()->where('type', 'billing')->first();

        $data = [
            'NRSHOP' => $shopId,
            'ORDER_NUMBER' => $order->reference,
            'ORDER_DATE' => $order->created_at->format('Y.m.d'),
            'ORDER_OBS' => $shippingAddress->delivery_instructions ?? '',
            'STATUS' => 2, // WAITING
            'STATUS_SUBTYPE' => 22, // Processable
            'TYPEOF_PAYMENT' => $order->meta['payment_type'] === 'offline' ? 1 : 2, // 1 for cash, 2 for card
            'TYPEOF_DELIVERY' => 2, // courier
            // billing
            'INVOICE_FIRSTNAME' => $billingAddress->first_name,
            'INVOICE_LASTNAME' => $billingAddress->last_name,
            'INVOICE_PHONE' => $billingAddress->contact_phone,
            'INVOICE_EMAIL' => $billingAddress->contact_email,
            'INVOICE_COUNTRY_CODE' => 'RO',
            'INVOICE_COUNTY' => $billingAddress->state,
            'INVOICE_TOWN' => $billingAddress->city,
            'INVOICE_STREET_ADDRESS' => $billingAddress->line_one.' '.$billingAddress->line_two.' '.$billingAddress->line_three,
            'INVOICE_POSTALCODE' => $billingAddress->postcode,
            // shipping
            'USE_DELIVERY_ADRESS' => 1,
            'DELIVERY_FIRSTNAME' => $shippingAddress->first_name,
            'DELIVERY_LASTNAME' => $shippingAddress->last_name,
            'DELIVERY_PHONE' => $shippingAddress->contact_phone,
            'DELIVERY_EMAIL' => $shippingAddress->contact_email,
            'DELIVERY_COUNTRY_CODE' => 'RO',
            'DELIVERY_COUNTY_CODE' => $shippingAddress->state,
            'DELIVERY_TOWN' => $shippingAddress->city,
            'DELIVERY_STREET_ADDRESS' => $shippingAddress->line_one.' '.$shippingAddress->line_two.' '.$shippingAddress->line_three,
            'DELIVERY_POSTALCODE' => $shippingAddress->postcode,
        ];

        if ($order->user_id) {
            $data['IDEXTAPP_CUSTOMER'] = $order->user_id;
        }

        if ($billingAddress->company_name) {
            $data['INVOICE_COMPANY_NAME'] = $billingAddress->company_name;
            $data['INVOICE_VAT_NUMBER'] = $billingAddress->tax_identifier;
        }

        // Items
        $items = $order->productLines->map(function ($item) {
            return [
                'IDSMARTCASH_ARTICLE' => $item->purchasable->erp_id,
                'IDEXTAPP_ARTICLE' => $item->purchasable->sku,
                'NAME' => $item->purchasable->getDescription(),
                'QTY' => $item->quantity,
                'PRICE' => number_format($item->unit_price->value / 100, 2, '.', ''),
            ];
        })->toArray();

        $data['ITEMS'] = $items;

        return $data;
    }

    /**
     * Get modified orders to sync status
     */
    public function getModifiedOrders(): array
    {
        $request = new GetModifiedDeliveryOrderRequest;
        $response = $this->send($request);

        if (! $response->successful()) {
            throw new InvalidErpResponseException(
                'Failed to get modified orders: '.$response->body()
            );
        }

        $data = $response->json();

        // Return the full response including RECVERSION for confirmation
        return $data ?? [];
    }

    /**
     * Get localities from Magister ERP.
     */
    public function getLocalities(): array
    {
        $request = new GetLocalitiesRequest;
        $response = $this->send($request);

        if (! $response->successful()) {
            throw new InvalidErpResponseException(
                'Failed to get localities: '.$response->body()
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Get attributes from Magister ERP.
     */
    public function getAttributes(): array
    {
        $request = new GetAttributesRequest;
        $response = $this->send($request);

        if (! $response->successful()) {
            throw new InvalidErpResponseException(
                'Failed to get attributes: '.$response->body()
            );
        }

        return $response->json() ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function generateInvoice(DtoInterface $payload): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function downloadInvoicePDF(DtoInterface $payload): ?Response
    {
        return null;
    }
}
