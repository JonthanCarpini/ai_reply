<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 29.90,
                'messages_limit' => 500,
                'whatsapp_limit' => 1,
                'actions_limit' => 3,
                'analytics_enabled' => false,
                'priority_support' => false,
                'features' => ['create_test', 'list_packages', 'check_status'],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 59.90,
                'messages_limit' => 3000,
                'whatsapp_limit' => 3,
                'actions_limit' => 0,
                'analytics_enabled' => true,
                'priority_support' => false,
                'features' => null,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'price' => 99.90,
                'messages_limit' => 0,
                'whatsapp_limit' => 0,
                'actions_limit' => 0,
                'analytics_enabled' => true,
                'priority_support' => true,
                'features' => null,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
