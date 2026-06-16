<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgentSeeder extends Seeder
{
    public function run()
    {
        DB::table('agents')->insert([
            [
                'name' => 'SalesBot Pro',
                'description' => 'Lead qualification',
                'status' => 'active',
                'icon' => '📊',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'SupportAgent',
                'description' => 'Customer support',
                'status' => 'active',
                'icon' => '💬',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'DataAnalyzer',
                'description' => 'Analytics',
                'status' => 'idle',
                'icon' => '🔍',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}