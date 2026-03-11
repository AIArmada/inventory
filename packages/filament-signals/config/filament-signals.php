<?php

declare(strict_types=1);

return [
    /* Navigation */
    'navigation_group' => 'Insights',

    /* Features */
    'features' => [
        'dashboard' => true,
        'page_views' => true,
        'conversion_funnel' => true,
        'acquisition' => true,
        'journeys' => true,
        'retention' => true,
        'content_performance' => true,
        'live_activity' => true,
        'goals_report' => true,
        'properties' => true,
        'goals' => true,
        'segments' => true,
        'saved_reports' => true,
        'alert_rules' => true,
        'alert_logs' => true,
        'widgets' => true,
        'trend_chart' => true,
        'pending_alerts_widget' => true,
    ],

    /* Resources */
    'resources' => [
        'labels' => [
            'outcomes' => 'Outcomes',
            'monetary_value' => 'Monetary Value',
        ],
        'navigation_sort' => [
            'dashboard' => 10,
            'page_views' => 15,
            'conversion_funnel' => 16,
            'acquisition' => 17,
            'journeys' => 18,
            'retention' => 19,
            'content_performance' => 20,
            'live_activity' => 21,
            'goals_report' => 22,
            'properties' => 30,
            'goals' => 31,
            'segments' => 31,
            'saved_reports' => 32,
            'alert_rules' => 33,
            'alert_logs' => 34,
        ],
    ],
];
