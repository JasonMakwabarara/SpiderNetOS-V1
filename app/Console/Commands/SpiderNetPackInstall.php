<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Models\Tenant;
use App\Models\FeaturePack;
use App\Models\Agent;

class SpiderNetPackInstall extends Command
{
    protected $signature = 'spidernet:pack-install {packPath} {--tenant=}';
    protected $description = 'Install a feature pack for a tenant';

    public function handle()
    {
        $tenantId = $this->option('tenant');
        
        if (!$tenantId) {
            $tenant = Tenant::first();
            if (!$tenant) {
                $this->error('No tenant found. Please create a tenant first or use --tenant option.');
                return 1;
            }
            $tenantId = $tenant->id;
            $this->warn("Using default tenant ID: $tenantId");
        }
        
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant not found with ID: $tenantId");
            return 1;
        }
        
        $packPath = $this->argument('packPath');
        if (!File::exists($packPath)) {
            $this->error("Pack file not found: $packPath");
            return 1;
        }
        
        $manifest = json_decode(File::get($packPath), true);
        if (!$manifest) {
            $this->error('Invalid manifest JSON');
            return 1;
        }
        
        // Validate first
        $this->call('spidernet:pack-validate', ['path' => $packPath]);
        
        // Create feature pack record
        $featurePack = FeaturePack::create([
            'tenant_id' => $tenant->id,
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'publisher' => $manifest['publisher'],
            'signature' => $manifest['signature'],
            'manifest' => $manifest,
            'status' => 'installed',
            'installed_at' => now(),
        ]);
        
        $this->info("✅ Feature pack '{$featurePack->name}' installed");
        
        // Provision dynamic agents
        $dynamicAgents = $manifest['provides']['dynamic_agents'] ?? [];
        foreach ($dynamicAgents as $agentSpec) {
            $agent = Agent::updateOrCreate(
                [
                    'slug' => $agentSpec['slug'],
                    'tenant_id' => $tenant->id
                ],
                [
                    'name' => $agentSpec['name'],
                    'type' => 'dynamic',
                    'role' => $agentSpec['role'] ?? null,
                    'capabilities' => $agentSpec['capabilities'] ?? [],
                    'description' => $agentSpec['description'] ?? '',
                    'status' => 'active',
                    'config' => ['pack_id' => $featurePack->id],
                    'pack_id' => $featurePack->id,
                ]
            );
            $this->line("  ✓ Provisioned agent: {$agent->name}");
        }
        
        return 0;
    }
}