<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@amffa.com.ar')->firstOrFail();

        $adultBrackets = fn (array $prices) => array_map(
            fn ($price, $maxAge) => ['max_age' => $maxAge, 'price' => $price],
            $prices,
            [35, 40, 45, 50, 55, 60, null]
        );

        $flat = fn (float $first, float $rest) => ['mode' => 'flat', 'first' => $first, 'rest' => $rest];
        $ageChildren = fn (int $freeUntil, array $tiers) => ['mode' => 'age', 'free_until' => $freeUntil, 'tiers' => $tiers];

        $data = [
            'START' => [
                'description' => 'Plan de entrada',
                'periods' => [
                    '2026-07' => [
                        'manual' => false,
                        'has_conyuge' => false,
                        'adults' => [
                            ['max_age' => 23, 'price' => 132970.33],
                            ['max_age' => 35, 'price' => 170671.39],
                        ],
                        'children' => ['mode' => 'none'],
                    ],
                ],
            ],
            'BALANCE' => [
                'description' => 'Plan balance',
                'periods' => [
                    '2026-07' => [
                        'manual' => false,
                        'has_conyuge' => true,
                        'adults' => $adultBrackets([136004.80, 165891.73, 195541.18, 225753.15, 256079.62, 285911.41, 315519.87]),
                        'children' => $flat(60038.13, 43086.28),
                    ],
                    '2026-08' => [
                        'manual' => false,
                        'has_conyuge' => true,
                        'adults' => $adultBrackets([138589, 169044, 199256, 230042, 260945, 291344, 321515]),
                        'children' => $flat(61179, 43905),
                    ],
                ],
            ],
            'PLATA' => [
                'description' => 'Plan plata',
                'periods' => [
                    '2026-07' => [
                        'manual' => false,
                        'has_conyuge' => true,
                        'adults' => $adultBrackets([181340.73, 221188.93, 260721.77, 301005.11, 341440.20, 381215.89, 420694.77]),
                        'children' => $flat(84762.03, 64986.28),
                    ],
                    '2026-08' => [
                        'manual' => false,
                        'has_conyuge' => true,
                        'adults' => $adultBrackets([184786, 225392, 265675, 306724, 347928, 388459, 428688]),
                        'children' => $flat(86373, 66221),
                    ],
                ],
            ],
            'ORO' => [
                'description' => 'Plan oro',
                'periods' => [
                    '2026-07' => [
                        'manual' => false,
                        'has_conyuge' => true,
                        'adults' => $adultBrackets([213342.04, 260222.28, 306731.51, 354123.67, 401694.38, 448489.29, 494935.03]),
                        'children' => $flat(102215.61, 80278.87),
                    ],
                    '2026-08' => [
                        'manual' => false,
                        'has_conyuge' => true,
                        'adults' => $adultBrackets([217396, 265167, 312559, 360852, 409327, 457011, 504339]),
                        'children' => $flat(104158, 81804),
                    ],
                ],
            ],
            'SENIOR' => [
                'description' => 'Plan senior',
                'periods' => [
                    '2026-07' => [
                        'manual' => false,
                        'has_conyuge' => true,
                        'adults' => $adultBrackets([277345.05, 338290.53, 398749.99, 460358.17, 522204.47, 583036.88, 643414.94]),
                        'children' => $flat(137117.11, 108040.05),
                    ],
                    '2026-08' => [
                        'manual' => false,
                        'has_conyuge' => true,
                        'adults' => $adultBrackets([282615, 344718, 406326, 469105, 532126, 594115, 655640]),
                        'children' => $flat(139722, 110093),
                    ],
                ],
            ],
            'FAMILY' => [
                'description' => 'Plan familiar',
                'periods' => [
                    '2026-08' => [
                        'manual' => false,
                        'has_conyuge' => true,
                        'adults' => $adultBrackets([213342, 260722, 301005, 341440, 381216, 420695, 544023]),
                        'children' => $ageChildren(15, [
                            ['max_age' => 21, 'first' => 35000, 'rest' => 28000],
                            ['max_age' => null, 'first' => 58000, 'rest' => 43000],
                        ]),
                    ],
                ],
            ],
            'FAMILY PROMO' => [
                'description' => 'Plan familiar promocional',
                'periods' => [
                    '2026-08' => [
                        'manual' => false,
                        'has_conyuge' => true,
                        'adults' => $adultBrackets([217395.46, 265676.03, 306724.14, 347927.48, 388458.59, 428688.40, 554359.59]),
                        'children' => $ageChildren(15, [
                            ['max_age' => 21, 'first' => 35665, 'rest' => 28532],
                            ['max_age' => null, 'first' => 59102, 'rest' => 43817],
                        ]),
                    ],
                ],
            ],
            'GO' => [
                'description' => 'Plan GO (precio manual)',
                'periods' => [
                    '2026-08' => ['manual' => true, 'manual_price' => null],
                ],
            ],
            'GO PROMO' => [
                'description' => 'Plan GO promocional (precio manual)',
                'periods' => [
                    '2026-08' => ['manual' => true, 'manual_price' => null],
                ],
            ],
        ];

        foreach ($data as $name => $config) {
            $plan = Plan::firstOrCreate(
                ['name' => $name],
                [
                    'description' => $config['description'],
                    'created_by' => $admin->id,
                ]
            );

            foreach ($config['periods'] as $period => $structure) {
                $plan->prices()->updateOrCreate(
                    ['period' => $period],
                    [
                        'structure' => $structure,
                        'created_by' => $admin->id,
                    ]
                );
            }
        }
    }
}
