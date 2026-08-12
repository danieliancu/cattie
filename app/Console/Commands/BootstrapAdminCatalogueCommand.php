<?php

namespace App\Console\Commands;

use App\Domain\Catalogue\Actions\BootstrapAdminCatalogue;
use Illuminate\Console\Command;

class BootstrapAdminCatalogueCommand extends Command
{
    protected $signature = 'cattie:admin-bootstrap';

    protected $description = 'Import existing catalogue templates into Cattie Admin';

    public function handle(BootstrapAdminCatalogue $bootstrap): int
    {
        $bootstrap->handle();
        $this->info('Admin catalogue bootstrap complete.');

        return self::SUCCESS;
    }
}
