<?php

namespace Lunar\Loyalty\Filament\Resources;

use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Loyalty\Filament\Resources\CustomerResource\RelationManagers\LoyaltyAccountRelationManager;
use Lunar\Models\Customer;

class CustomerResource extends ResourceExtension
{
    /**
     * Get the relation managers for the customer resource.
     *
     * @param  array<int, class-string>  $managers
     * @return array<int, class-string>
     */
    public function getRelations(array $managers): array
    {
        $managers[] = LoyaltyAccountRelationManager::class;

        return $managers;
    }

    /**
     * Show total loyalty points below Customer Groups on the customer form sidebar.
     */
    public function extendForm(Schema $schema): Schema
    {
        if (! config('lunar.loyalty.enabled', true)) {
            return $schema;
        }

        $components = [];

        foreach ($schema->getComponents(true) as $component) {
            if ($this->isCustomerGroupsSideSection($component)) {
                $components[] = Group::make([
                    Section::make()
                        ->schema($component->getDefaultChildComponents()),
                    Section::make(__('lunarpanel.loyalty::plugin.customer.loyalty_title'))
                        ->schema([
                            Placeholder::make('loyalty_display_balance')
                                ->label(__('lunarpanel.loyalty::plugin.fields.total_points'))
                                ->content(fn (?Model $record): string => (string) (
                                    $record instanceof Customer
                                        ? ($record->loyaltyAccount?->display_balance ?? 0)
                                        : 0
                                )),
                        ]),
                ])->columnSpan($component->getColumnSpan());

                continue;
            }

            $components[] = $component;
        }

        return $schema->components($components);
    }

    /**
     * Whether the component is the customer form sidebar section that holds Customer Groups.
     */
    protected function isCustomerGroupsSideSection(Component $component): bool
    {
        if (! $component instanceof Section) {
            return false;
        }

        foreach ($component->getDefaultChildComponents() as $child) {
            if (method_exists($child, 'getName') && $child->getName() === 'customerGroups') {
                return true;
            }
        }

        return false;
    }
}
