<?php

namespace Database\Seeders;

use App\Models\Action;
use App\Models\User;
use Illuminate\Database\Seeder;

class RecommendAppActionSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            // Verificar se já existe
            $exists = $user->actions()->where('action_type', 'recommend_app')->exists();
            
            if (!$exists) {
                $user->actions()->create([
                    'action_type' => 'recommend_app',
                    'label' => 'Recomendar Aplicativo',
                    'enabled' => true,
                    'params' => null,
                    'custom_instructions' => null,
                    'daily_limit' => 0,
                    'daily_count' => 0,
                ]);
            }
        }

        $this->command->info('Action recommend_app adicionada para todos os usuários!');
    }
}
