<?php

namespace Lunar\ERP\Providers\Magister;

class OrderStatusMapper
{
    public function __invoke(int $externalStatus, int $externalSubStatus): string
    {
        // Magister status mapping
        // Our statuses: created, canceled, confirmed, payment-received, payment-offline, prepare-shipment, dispatched, returned, rejected, completed
        switch ($externalStatus) {
            case 1: // Deschis
                return 'created';
            case 2: // In Asteptare
                if ($externalSubStatus === 21) {
                    return 'awaiting-payment'; // In asteptarea platii
                } elseif ($externalSubStatus === 22) {
                    return 'payment-received'; // Procesabil
                }

                return 'created';
            case 3: // In Lucru
                // SEND EMAIL
                return 'confirmed';
            case 4: // Facturat
                switch ($externalSubStatus) {
                    case 41: // Neambalat
                    case 42: // Ambalat
                        return 'prepare-shipment';
                    case 43: // Expediat
                        return 'dispatched';
                    case 44: // Retur Postal
                        return 'returned';
                    case 45: // Confirmat Primirea
                        return 'completed';
                    default:
                        return 'completed';
                }
            case 5: // Returnat
                return 'returned';
            case 6: // Anulat
                return 'canceled';
            default:
                return 'created';
        }
    }
}
