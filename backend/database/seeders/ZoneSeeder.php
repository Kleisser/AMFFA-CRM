<?php

namespace Database\Seeders;

use App\Models\Locality;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/zones_data.php';

        $zoneIds = [];
        foreach ($data['zones'] as $zone) {
            $zoneModel = Zone::firstOrCreate(
                ['name' => $zone['name']],
                ['color' => $this->colorFor($zone['name'])]
            );
            $zoneIds[$zone['name']] = $zoneModel->id;
        }

        $existing = Locality::pluck('id', 'zone_id')->toArray();
        $existingKeys = Locality::all()
            ->map(fn ($l) => $l->zone_id . '|' . mb_strtoupper($l->name))
            ->flip();

        $chunk = [];
        foreach ($data['localities'] as $loc) {
            $zoneId = $zoneIds[$loc['zone']] ?? null;
            if (!$zoneId) {
                continue;
            }

            $key = $zoneId . '|' . mb_strtoupper($loc['name']);
            if ($existingKeys->has($key)) {
                continue;
            }

            $chunk[] = [
                'zone_id' => $zoneId,
                'name' => $loc['name'],
                'partido' => $loc['partido'],
                'code' => $loc['code'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($chunk) >= 500) {
                Locality::insert($chunk);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            Locality::insert($chunk);
        }
    }

    private function colorFor(string $zoneName): string
    {
        return match ($zoneName) {
            'ZONA NORTE' => '#3B82F6',
            'ZONA SUR' => '#10B981',
            'ZONA OESTE' => '#F59E0B',
            'LA PLATA' => '#8B5CF6',
            default => '#6B7280',
        };
    }
}
