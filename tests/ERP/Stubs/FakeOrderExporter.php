<?php

namespace Lunar\Tests\ERP\Stubs;

use Lunar\ERP\Contracts\ErpDataExporterInterface;
use Lunar\Models\Order;
use RuntimeException;
use Saloon\Http\Response;

class FakeOrderExporter implements ErpDataExporterInterface
{
    public static bool $accepts = true;

    public static bool $throws = false;

    /**
     * @var array<string, mixed>|null
     */
    public static ?array $sentMeta = null;

    public function __construct(public FakeErpClient $client) {}

    public function sendOrder(Order $order): bool
    {
        self::$sentMeta = $order->meta instanceof \ArrayObject
            ? $order->meta->getArrayCopy()
            : (array) ($order->meta ?? []);

        if (self::$throws) {
            throw new RuntimeException('ERP rejected the connection');
        }

        return self::$accepts;
    }

    /**
     * @return array{series: string, number: string}
     */
    public function generateInvoice(Order $order): array
    {
        return [
            'series' => 'S',
            'number' => '1',
        ];
    }

    public function downloadInvoicePDF(Order $order): ?Response
    {
        return null;
    }
}
