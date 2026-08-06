<?php

namespace Database\Seeders;

use App\Models\GecrosVendedor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Equipo comercial real (generado desde la base local).
 * Los supervisores ya los crea DatabaseSeeder; aquí se crean los
 * asesores reales y el vínculo venafi_id => asesor de GECROS.
 * Idempotente: no pisa usuarios existentes ni vínculos hechos a mano.
 */
class RealEquiposSeeder extends Seeder
{
    public function run(): void
    {
        $pass = env('EQUIPOS_SEED_PASS', 'Asesor2026#');
        $users = [];

        foreach ($this->asesores() as $a) {
            $user = User::firstOrCreate(
                ['email' => $a['email']],
                [
                    'name' => $a['name'],
                    'password' => Hash::make($pass),
                    'role' => 'seller',
                    'supervisor_id' => $a['supervisor_id'],
                    'is_active' => true,
                ]
            );
            $users[$a['email']] = $user->id;
        }

        foreach ($this->vinculos() as $v) {
            $rec = GecrosVendedor::find($v['venafi_id']);
            if ($rec === null) {
                continue;
            }
            $rec->nombre = $v['nombre'];
            $userId = $users[$v['email']] ?? null;
            if ($userId !== null && $rec->user_id === null) {
                $rec->user_id = $userId;
            }
            $rec->save();
        }
    }

    private function asesores(): array
    {
        return [
            ['name' => 'Andrea Beredo', 'email' => 'aberedo@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor3@amffa.com.ar')->value('id')],
            ['name' => 'Cristian Perta', 'email' => 'cperta@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Daiana Giselle Gonzalez', 'email' => 'ggonzalez@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'David German Torrielli', 'email' => 'dtorrielli@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Diego Murillo', 'email' => 'dmurillo@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Gaston Ifran', 'email' => 'gifran@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor3@amffa.com.ar')->value('id')],
            ['name' => 'Gladys Ortiz', 'email' => 'gortiz@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor2@amffa.com.ar')->value('id')],
            ['name' => 'IGNACIO ANZELMO', 'email' => 'ianzelmo@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Jesica Suireszcz', 'email' => 'jesica@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Jessica Taslak', 'email' => 'jtaslak@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor3@amffa.com.ar')->value('id')],
            ['name' => 'Juan Ignacio Mendy', 'email' => 'jmendy@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Juan Pablo Peña Peña', 'email' => 'jpena@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Juan Pedro Murdoch', 'email' => 'jmurdoch@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Julia Bulit', 'email' => 'jbulit@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Karina Cassap', 'email' => 'kcassap@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Laura Patak', 'email' => 'lpatak@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Lorena Bassi', 'email' => 'lbassi@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Lorena Benitez', 'email' => 'lbenitez@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor3@amffa.com.ar')->value('id')],
            ['name' => 'Maria Detry', 'email' => 'mdetry@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor3@amffa.com.ar')->value('id')],
            ['name' => 'Marta Carina Vallejos', 'email' => 'mvallejos@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Martin Julian Mendieta', 'email' => 'mmendieta@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Martin Paz', 'email' => 'mpaz@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Mateo Casco', 'email' => 'mcasco@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor3@amffa.com.ar')->value('id')],
            ['name' => 'Melisa Esperanza', 'email' => 'mesperanza@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Mercedes Perdiguero', 'email' => 'mperdiguero@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Micaela Rojas', 'email' => 'mrojas@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor3@amffa.com.ar')->value('id')],
            ['name' => 'Milena Carrascosa', 'email' => 'mcarrascosa@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Natalia Bazan', 'email' => 'nbazan@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor3@amffa.com.ar')->value('id')],
            ['name' => 'Natalia Manos', 'email' => 'nmanos@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Oriana Cerdá', 'email' => 'ocerda@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Paola Brizuela', 'email' => 'pbrizuela@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor3@amffa.com.ar')->value('id')],
            ['name' => 'Romina Franco', 'email' => 'rfranco@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor3@amffa.com.ar')->value('id')],
            ['name' => 'Sebastian Nicolas Ruiz', 'email' => 'sruiz@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Tamara Larroca', 'email' => 'tlarroca@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Valentina Gomez Brandan', 'email' => 'vbrandan@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Valentina Piscinne', 'email' => 'vpiscinne@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Valeria Mautouchet', 'email' => 'vmautouchet@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Veronica Gonzalez', 'email' => 'vgonzalez@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor@amffa.com.ar')->value('id')],
            ['name' => 'Viviana Ferreira', 'email' => 'vferreira@amffa.com.ar', 'supervisor_id' => User::where('email', 'supervisor3@amffa.com.ar')->value('id')],
        ];
    }

    private function vinculos(): array
    {
        return [
            ['venafi_id' => 62, 'nombre' => 'MURDOCH JUAN PEDRO', 'email' => 'jmurdoch@amffa.com.ar'],
            ['venafi_id' => 63, 'nombre' => 'ORTIZ GLADYS LEONOR', 'email' => 'gortiz@amffa.com.ar'],
            ['venafi_id' => 69, 'nombre' => 'ESPERANZA MELISA VERONICA', 'email' => 'mesperanza@amffa.com.ar'],
            ['venafi_id' => 70, 'nombre' => 'ANZELMO IGNACIO ANDRES', 'email' => 'ianzelmo@amffa.com.ar'],
            ['venafi_id' => 71, 'nombre' => 'BULIT JULIA ROCIO', 'email' => 'jbulit@amffa.com.ar'],
            ['venafi_id' => 73, 'nombre' => 'PISCINNE VALENTINA', 'email' => 'vpiscinne@amffa.com.ar'],
            ['venafi_id' => 74, 'nombre' => 'DAVID GERMAN TORRIELLI', 'email' => 'dtorrielli@amffa.com.ar'],
            ['venafi_id' => 75, 'nombre' => 'SILVANA LORENA BASSI', 'email' => 'lbassi@amffa.com.ar'],
            ['venafi_id' => 76, 'nombre' => 'ORIANNA CERDÁ', 'email' => 'ocerda@amffa.com.ar'],
            ['venafi_id' => 83, 'nombre' => 'PATAK VANESA LAURA', 'email' => 'lpatak@amffa.com.ar'],
            ['venafi_id' => 86, 'nombre' => 'PERTA CRISTIAN', 'email' => 'cperta@amffa.com.ar'],
            ['venafi_id' => 89, 'nombre' => 'SUIRESZCZ JESICA', 'email' => 'jesica@amffa.com.ar'],
            ['venafi_id' => 92, 'nombre' => 'Paz  Martin', 'email' => 'mpaz@amffa.com.ar'],
            ['venafi_id' => 94, 'nombre' => 'Larroca Tamara', 'email' => 'tlarroca@amffa.com.ar'],
            ['venafi_id' => 96, 'nombre' => 'Manos Natalia', 'email' => 'nmanos@amffa.com.ar'],
            ['venafi_id' => 115, 'nombre' => 'MAUTOUCHET VALERIA MABEL', 'email' => 'vmautouchet@amffa.com.ar'],
            ['venafi_id' => 127, 'nombre' => 'CARRASCOSA MILENA SOL', 'email' => 'mcarrascosa@amffa.com.ar'],
            ['venafi_id' => 132, 'nombre' => 'MURILLO DIEGO FERNANDO', 'email' => 'dmurillo@amffa.com.ar'],
            ['venafi_id' => 135, 'nombre' => 'MARIA DETRY', 'email' => 'mdetry@amffa.com.ar'],
            ['venafi_id' => 137, 'nombre' => 'CASCO MATEO', 'email' => 'mcasco@amffa.com.ar'],
            ['venafi_id' => 138, 'nombre' => 'BRIZUELA PAOLA', 'email' => 'pbrizuela@amffa.com.ar'],
            ['venafi_id' => 139, 'nombre' => 'BEREDO ANDREA VANESA', 'email' => 'aberedo@amffa.com.ar'],
            ['venafi_id' => 140, 'nombre' => 'BENITEZ GONZALEZ LORENA ELIZA', 'email' => 'lbenitez@amffa.com.ar'],
            ['venafi_id' => 141, 'nombre' => 'INFRAN GASTON', 'email' => 'gifran@amffa.com.ar'],
            ['venafi_id' => 142, 'nombre' => 'ROJAS MICAELA', 'email' => 'mrojas@amffa.com.ar'],
            ['venafi_id' => 143, 'nombre' => 'TASLAK JESSICA', 'email' => 'jtaslak@amffa.com.ar'],
            ['venafi_id' => 145, 'nombre' => 'FRANCO ROMINA', 'email' => 'rfranco@amffa.com.ar'],
            ['venafi_id' => 147, 'nombre' => 'CORROTTI ROCHA PRISCILLA', 'email' => 'mdetry@amffa.com.ar'],
            ['venafi_id' => 148, 'nombre' => 'FERREIRA VIVIANA', 'email' => 'vferreira@amffa.com.ar'],
            ['venafi_id' => 149, 'nombre' => 'Marta Carina Vallejos', 'email' => 'mvallejos@amffa.com.ar'],
            ['venafi_id' => 150, 'nombre' => 'BAZAN NATALIA', 'email' => 'nbazan@amffa.com.ar'],
            ['venafi_id' => 151, 'nombre' => 'GONZALES VERONICA MABEL', 'email' => 'vgonzalez@amffa.com.ar'],
            ['venafi_id' => 153, 'nombre' => 'PEÑA JUAN PABLO', 'email' => 'jpena@amffa.com.ar'],
            ['venafi_id' => 154, 'nombre' => 'PERDIGUERO MERCEDES', 'email' => 'mperdiguero@amffa.com.ar'],
            ['venafi_id' => 155, 'nombre' => 'GOMEZ BRANDAN VALENTINA', 'email' => 'vbrandan@amffa.com.ar'],
            ['venafi_id' => 156, 'nombre' => 'MENDY JUAN IGNACIO', 'email' => 'jmendy@amffa.com.ar'],
            ['venafi_id' => 158, 'nombre' => 'RUIZ SEBASTIAN NICOLAS', 'email' => 'sruiz@amffa.com.ar'],
            ['venafi_id' => 159, 'nombre' => 'GONZALEZ DAIANA GISELLE', 'email' => 'ggonzalez@amffa.com.ar'],
        ];
    }
}