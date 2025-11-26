<?php

namespace Database\Seeders;

use App\Models\ContributionBracket;
use Illuminate\Database\Seeder;

class ContributionBracketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = [
            // Placeholder brackets roughly matching current simple rates
            [
                'type' => 'SSS',
                'range_from' => 0,
                'range_to' => null,
                'employee_rate' => 0.04, // 4% of gross
                'employee_amount' => null,
            ],
            [
                'type' => 'PhilHealth',
                'range_from' => 0,
                'range_to' => null,
                'employee_rate' => 0.02, // 2% of gross
                'employee_amount' => null,
            ],
            // Pag-IBIG: 1% of gross, capped at 100
            [
                'type' => 'Pag-IBIG',
                'range_from' => 0,
                'range_to' => 10000,
                'employee_rate' => 0.01,
                'employee_amount' => null,
            ],
            [
                'type' => 'Pag-IBIG',
                'range_from' => 10000.01,
                'range_to' => null,
                'employee_rate' => null,
                'employee_amount' => 100.00,
            ],
        ];

        foreach ($rows as $data) {
            ContributionBracket::updateOrCreate(
                [
                    'type' => $data['type'],
                    'range_from' => $data['range_from'],
                    'range_to' => $data['range_to'],
                ],
                [
                    'employee_rate' => $data['employee_rate'],
                    'employee_amount' => $data['employee_amount'],
                ]
            );
        }
    }
}
