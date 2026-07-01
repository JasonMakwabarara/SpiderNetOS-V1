<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Flow;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SystemObjectProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create([
            'name' => 'Default Organization',
            'domain' => 'spidernetos.com',
            // settings is array-cast — pass an array, not a JSON string.
            'settings' => ['theme' => 'dark', 'timezone' => 'UTC'],
        ]);

        // Super-admin (manages the platform AI provider panel). Change the
        // password immediately after first login.
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@spidernetos.com',
            'password' => Hash::make('change-me-on-first-login'),
            'tenant_id' => $tenant->id,
            'is_super_admin' => true,
            'role' => 'super_admin',
        ]);

        Agent::create([
            'tenant_id' => $tenant->id,
            'name' => 'SalesBot Pro',
            'slug' => 'salesbot-pro',
            'role' => 'Sales',
            'capabilities' => ['lead_qualification', 'product_recommendations', 'pricing_info'],
            'description' => 'AI sales assistant for lead qualification and product recommendations',
            'status' => 'active',
        ]);

        Agent::create([
            'tenant_id' => $tenant->id,
            'name' => 'SupportAgent',
            'slug' => 'support-agent',
            'role' => 'Support',
            'capabilities' => ['ticket_resolution', 'faq_answers', 'issue_tracking'],
            'description' => 'Customer support agent for ticket resolution and FAQs',
            'status' => 'active',
        ]);

        Flow::create([
            'tenant_id' => $tenant->id,
            'name' => 'Order Processing',
            'slug' => 'order-processing',
            'trigger' => 'webhook',
            'config' => ['actions' => [
                ['type' => 'email', 'to' => 'admin@example.com', 'subject' => 'New Order', 'body' => 'Order received'],
            ]],
            'executions' => 0,
        ]);

        // Provision the built-in flexible-data-model objects (People, Companies,
        // Deals) so the CRM/records layer is usable out of the box.
        app(SystemObjectProvisioner::class)->provision($tenant->id);
    }
}
