<?php

namespace App\Filament\Widgets;

use App\Enums\DesignTemplateVersionStatus;
use App\Enums\ProductStatus;
use App\Models\DesignTemplateVersion;
use App\Models\FulfilmentProductMapping;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CatalogueOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Published products', Product::where('status', ProductStatus::Published)->count()),
            Stat::make('Draft products', Product::where('status', ProductStatus::Draft)->count()),
            Stat::make('Provider sync failures', FulfilmentProductMapping::where('last_sync_status', 'failed')->count()),
            Stat::make('Draft templates', DesignTemplateVersion::where('status', DesignTemplateVersionStatus::Draft)->count()),
        ];
    }
}
