<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Agent;
use App\Models\Flow;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Create default tenant
        $tenant = Tenant::create([
            'name' => 'Default Organization',
            'domain' => 'spidernetos.com',
            'settings' => json_encode(['theme' => 'dark', 'timezone' => 'UTC'])
        ]);

        // Create admin user
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@spidernetos.com',
            'password' => Hash::make('Zukaarimoto01!'),
            'tenant_id' => $tenant->id,
        ]);

        // Create sample agents
        Agent::create([
            'tenant_id' => $tenant->id,
            'name' => 'SalesBot Pro',
            'slug' => 'salesbot-pro',
            'role' => 'Sales',
            'capabilities' => json_encode(['lead_qualification', 'product_recommendations', 'pricing_info']),
            'description' => 'AI sales assistant for lead qualification and product recommendations',
            'status' => 'active',
        ]);

        Agent::create([
            'tenant_id' => $tenant->id,
            'name' => 'SupportAgent',
            'slug' => 'support-agent',
            'role' => 'Support',
            'capabilities' => json_encode(['ticket_resolution', 'faq_answers', 'issue_tracking']),
            'description' => 'Customer support agent for ticket resolution and FAQs',
            'status' => 'active',
        ]);

        // Create sample flow
        Flow::create([
            'tenant_id' => $tenant->id,
            'name' => 'Order Processing',
            'slug' => 'order-processing',
            'trigger' => 'webhook',
            'config' => json_encode(['actions' => [
                ['type' => 'email', 'to' => 'admin@example.com', 'subject' => 'New Order', 'body' => 'Order received']
            ]]),
            'executions' => 0,
        ]);
    }
}
