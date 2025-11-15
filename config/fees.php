<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default GST Percentage
    |--------------------------------------------------------------------------
    |
    | Percentage used for splitting GST inclusive payments. This can be made
    | configurable via settings UI in later modules. The value should be the
    | GST rate applied on collected fees.
    |
    */

    'gst_percentage' => env('FEES_GST_PERCENTAGE', 18.0),

    'penalty' => [
        'grace_days' => env('FEES_PENALTY_GRACE_DAYS', 5),
        'rate_percent_per_day' => env('FEES_PENALTY_RATE_PERCENT', 1.5),
        'reminder_frequency_days' => env('FEES_REMINDER_FREQUENCY_DAYS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Safe Ratio Threshold
    |--------------------------------------------------------------------------
    |
    | The maximum ratio of online base payments to cash base payments.
    | If the ratio exceeds this threshold, an alert will be shown.
    | Ratio = (online base) / (cash base)
    | Default: 0.8 (80%)
    |
    */

    'safe_ratio_threshold' => env('FEES_SAFE_RATIO_THRESHOLD', 0.8),
];


