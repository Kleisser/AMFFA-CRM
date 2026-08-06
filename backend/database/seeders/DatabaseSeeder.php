<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->seedUser(
            'admin@amffa.com.ar',
            [
                'name' => 'Admin AMFFA',
                'password' => Hash::make('Admin2026#'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $supervisor1 = $this->seedUser(
            'supervisor@amffa.com.ar',
            [
                'name' => 'Anzelmo Ignacio',
                'password' => Hash::make('Supervisor2026#'),
                'role' => 'supervisor',
                'is_active' => true,
            ]
        );

        $supervisor2 = $this->seedUser(
            'supervisor2@amffa.com.ar',
            [
                'name' => 'Ortiz Gladys',
                'password' => Hash::make('Supervisor2026#'),
                'role' => 'supervisor',
                'is_active' => true,
            ]
        );

        $supervisor3 = $this->seedUser(
            'supervisor3@amffa.com.ar',
            [
                'name' => 'Detry Maria',
                'password' => Hash::make('Supervisor2026#'),
                'role' => 'supervisor',
                'is_active' => true,
            ]
        );

        $supList = [$supervisor1, $supervisor2, $supervisor3];

        foreach (range(1, 24) as $i) {
            $sup = $supList[intdiv($i - 1, 8)];
            User::firstOrCreate(
                ['email' => "asesor{$i}@amffa.com.ar"],
                [
                    'name' => "Asesor {$i}",
                    'password' => Hash::make('Asesor2026#'),
                    'role' => 'seller',
                    'supervisor_id' => $sup->id,
                    'is_active' => true,
                ]
            );
        }

        $pipeline = Pipeline::firstOrCreate(
            ['name' => 'Pipeline Principal'],
            ['created_by' => $admin->id]
        );

        $stages = [
            ['name' => 'Nuevo Lead', 'color' => '#3B82F6'],
            ['name' => 'Contactado', 'color' => '#8B5CF6'],
            ['name' => 'En Negociación', 'color' => '#F59E0B'],
            ['name' => 'Propuesta Enviada', 'color' => '#EC4899'],
            ['name' => 'Cierre Ganado', 'color' => '#10B981'],
            ['name' => 'Cierre Perdido', 'color' => '#EF4444'],
        ];

        foreach ($stages as $index => $stage) {
            PipelineStage::firstOrCreate(
                ['pipeline_id' => $pipeline->id, 'name' => $stage['name']],
                ['order' => $index, 'color' => $stage['color']]
            );
        }

        $this->call(ZoneSeeder::class);
        $this->call(PlanSeeder::class);
        $this->call(RealEquiposSeeder::class);

        if (Contact::count() === 0) {
            $this->call(DemoDataSeeder::class);
        }
    }

    /**
     * Crea el usuario si no existe; si ya existe actualiza nombre/rol/activo
     * pero NO pisa la contraseña (evita resetear logins en cada deploy).
     */
    private function seedUser(string $email, array $data): User
    {
        $user = User::firstOrCreate(['email' => $email], $data);

        if (!$user->wasRecentlyCreated) {
            $user->name = $data['name'] ?? $user->name;
            $user->role = $data['role'] ?? $user->role;
            $user->is_active = $data['is_active'] ?? $user->is_active;
            $user->save();
        }

        return $user;
    }
}
