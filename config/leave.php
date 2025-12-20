<?php

return [
    'employment_entitlements' => [
        'regular' => [
            'vl' => 12.0,
            'sl' => 7.0,
        ],
        'part_time' => [
            'vl' => 0.0,
            'sl' => 0.0,
        ],
    ],

    'leave_type_labels' => [
        'vl' => 'Vacation Leave',
        'sl' => 'Sick Leave',
    ],

    'carryover_max' => [
        'vl' => 5.0,
        'sl' => 0.0,
    ],

    'request_type_map' => [
        'vacation' => 'vl',
        'pto' => 'vl',
        'emergency' => 'vl',
        'sick' => 'sl',
    ],

    'default_paid_bucket' => 'vl',

    'accrual_rounding_scale' => 3,
];
