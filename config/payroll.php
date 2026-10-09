<?php

return [
    // Default overtime premium multiplier (e.g. 1.30 = 130% of base rate)
    'overtime_multiplier' => 1.30,

    // Working days assumptions for rate conversions
    'days_per_week' => 6,
    'days_per_month' => 26,

    // Cash advance policy caps (typical PH construction context, override per company as needed)
    'ca' => [
        // Whether part-time workers may request cash advances at all.
        'allow_part_time' => false,

        // Maximum outstanding CA balance per employment type (PHP).
        'cap' => [
            // Regular workers: e.g. up to one-half of typical monthly net pay.
            'regular' => 5000.00,

            // Part-time workers: much lower exposure; can be enabled via allow_part_time.
            'part_time' => 3000.00,
        ],

        // Safety guard: maximum CA as a fraction of last known monthly net pay (if available).
        'max_percent_of_monthly_net' => 0.8,

        // Minimum repayment per payroll (PHP), aligned with company policy.
        'min_deduction' => 500.00,
    ],
];
