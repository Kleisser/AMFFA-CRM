<?php

namespace Database\Seeders;

use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin AMFFA',
            'email' => 'admin@amffa.com.ar',
            'password' => Hash::make('Admin2026#'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $supervisor1 = User::create([
            'name' => 'Supervisor 1',
            'email' => 'supervisor@amffa.com.ar',
            'password' => Hash::make('Supervisor2026#'),
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        $supervisor2 = User::create([
            'name' => 'Supervisor 2',
            'email' => 'supervisor2@amffa.com.ar',
            'password' => Hash::make('Supervisor2026#'),
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        $supervisor3 = User::create([
            'name' => 'Supervisor 3',
            'email' => 'supervisor3@amffa.com.ar',
            'password' => Hash::make('Supervisor2026#'),
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        $supList = [$supervisor1, $supervisor2, $supervisor3];

        foreach (range(1, 24) as $i) {
            $sup = $supList[intdiv($i - 1, 8)];
            User::create([
                'name' => "Asesor {$i}",
                'email' => "asesor{$i}@amffa.com.ar",
                'password' => Hash::make('Asesor2026#'),
                'role' => 'seller',
                'supervisor_id' => $sup->id,
                'is_active' => true,
            ]);
        }

        $pipeline = Pipeline::create([
            'name' => 'Pipeline Principal',
            'created_by' => $admin->id,
        ]);

        $stages = [
            ['name' => 'Nuevo Lead', 'color' => '#3B82F6'],
            ['name' => 'Contactado', 'color' => '#8B5CF6'],
            ['name' => 'En Negociación', 'color' => '#F59E0B'],
            ['name' => 'Propuesta Enviada', 'color' => '#EC4899'],
            ['name' => 'Cierre Ganado', 'color' => '#10B981'],
            ['name' => 'Cierre Perdido', 'color' => '#EF4444'],
        ];

        foreach ($stages as $index => $stage) {
            PipelineStage::create([
                'pipeline_id' => $pipeline->id,
                'name' => $stage['name'],
                'order' => $index,
                'color' => $stage['color'],
            ]);
        }

        $this->call(DemoDataSeeder::class);
    }
}
