<?php

namespace Lunar\Loyalty\Console;

use Illuminate\Console\Command;
use Lunar\Loyalty\Services\LoyaltyEngine;
use Lunar\Models\Customer;

class AwardBirthdayPointsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'loyalty:award-birthday-points';

    /**
     * @var string
     */
    protected $description = 'Award birthday loyalty points to eligible customers';

    /**
     * Execute the console command.
     */
    public function handle(LoyaltyEngine $engine): int
    {
        $config = config('lunar.loyalty.scheduled_rewards.birthday', []);

        if (! ($config['enabled'] ?? false)) {
            $this->info('Birthday rewards are disabled.');

            return self::SUCCESS;
        }

        $attributeHandle = $config['attribute_handle'] ?? 'birthday';
        $year = (int) now()->format('Y');
        $today = now()->format('m-d');
        $awarded = 0;

        Customer::query()->chunk(100, function ($customers) use ($engine, $attributeHandle, $year, $today, &$awarded) {
            foreach ($customers as $customer) {
                $birthday = $this->resolveBirthday($customer, $attributeHandle);

                if (! $birthday || $birthday !== $today) {
                    continue;
                }

                $engine->earnFromBirthday($customer, $year);
                $awarded++;
            }
        });

        $this->info("Awarded birthday points to {$awarded} customer(s).");

        return self::SUCCESS;
    }

    /**
     * Resolve a customer's birthday as an m-d string.
     */
    protected function resolveBirthday(Customer $customer, string $attributeHandle): ?string
    {
        $attributeData = $customer->attribute_data;

        if (! $attributeData) {
            return null;
        }

        $birthday = $attributeData->get($attributeHandle)?->getValue();

        if (! $birthday) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($birthday)->format('m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
