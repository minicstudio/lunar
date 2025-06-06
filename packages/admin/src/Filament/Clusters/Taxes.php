<?php

namespace Lunar\Admin\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Support\Facades\FilamentIcon;

class Taxes extends Cluster
{
    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.settings');
    }

    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::tax');
    }

    public static function getNavigationLabel(): string
    {
        return __('lunarpanel::tax.plural_label');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('lunarpanel::tax.plural_label');
    }
}
