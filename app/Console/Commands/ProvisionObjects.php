<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\SystemObjectProvisioner;
use Illuminate\Console\Command;

class ProvisionObjects extends Command
{
    protected $signature = 'spidernet:provision-objects {tenant? : Tenant id (defaults to all tenants)}';

    protected $description = 'Provision the built-in system objects (People, Companies, Deals) for a tenant';

    public function handle(SystemObjectProvisioner $provisioner): int
    {
        $tenants = $this->argument('tenant')
            ? Tenant::whereKey($this->argument('tenant'))->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error('No matching tenants found.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $provisioner->provision($tenant->id);
            $this->info("Provisioned system objects for tenant #{$tenant->id} ({$tenant->name}).");
        }

        return self::SUCCESS;
    }
}
