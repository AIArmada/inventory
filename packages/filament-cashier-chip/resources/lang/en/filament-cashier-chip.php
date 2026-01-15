<?php

declare(strict_types=1);

return [
    'navigation' => [
        'group' => 'Billing',
        'subscriptions' => 'Subscriptions',
        'customers' => 'Customers',
        'invoices' => 'Invoices',
        'dashboard' => 'Billing Dashboard',
        'payment_methods' => 'Payment Methods',
    ],

    'subscriptions' => [
        'title' => 'Subscriptions',
    ],

    'payment_methods' => [
        'title' => 'Payment Methods',
    ],

    'invoices' => [
        'title' => 'Invoices',
    ],

    'subscription' => [
        'label' => 'Subscription',
        'plural' => 'Subscriptions',
        'status' => [
            'active' => 'Active',
            'trialing' => 'Trialing',
            'canceled' => 'Canceled',
            'past_due' => 'Past Due',
            'paused' => 'Paused',
            'incomplete' => 'Incomplete',
            'incomplete_expired' => 'Incomplete Expired',
            'unpaid' => 'Unpaid',
        ],
        'actions' => [
            'cancel' => 'Cancel Subscription',
            'cancel_now' => 'Cancel Immediately',
            'resume' => 'Resume Subscription',
            'pause' => 'Pause Subscription',
            'unpause' => 'Unpause Subscription',
            'extend_trial' => 'Extend Trial',
            'update_quantity' => 'Update Quantity',
            'swap_plan' => 'Swap Plan',
            'sync_status' => 'Sync Status',
        ],
        'messages' => [
            'canceled' => 'The subscription has been canceled.',
            'resumed' => 'The subscription is now active again.',
            'paused' => 'The subscription has been paused.',
            'unpaused' => 'The subscription is now active.',
            'trial_extended' => 'The trial has been extended.',
            'quantity_updated' => 'The subscription quantity has been updated.',
            'status_synced' => 'The subscription status has been recalculated.',
        ],
    ],

    'customer' => [
        'label' => 'Customer',
        'plural' => 'Customers',
        'actions' => [
            'create_in_chip' => 'Create in Chip',
            'sync_to_chip' => 'Sync to Chip',
            'refresh_payment' => 'Refresh Payment Method',
            'add_payment' => 'Add Payment Method',
            'view_in_chip' => 'View in Chip',
        ],
        'messages' => [
            'created_in_chip' => 'Customer created in Chip.',
            'synced_to_chip' => 'Customer synced to Chip.',
            'payment_refreshed' => 'Payment method refreshed.',
        ],
    ],

    'invoice' => [
        'label' => 'Invoice',
        'plural' => 'Invoices',
        'status' => [
            'created' => 'Created',
            'pending' => 'Pending',
            'paid' => 'Paid',
            'captured' => 'Captured',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'refund_pending' => 'Refund Pending',
            'refunded' => 'Refunded',
            'partially_refunded' => 'Partially Refunded',
        ],
        'actions' => [
            'download_pdf' => 'Download PDF',
            'send_invoice' => 'Send Invoice',
            'mark_as_paid' => 'Mark as Paid',
            'view_checkout' => 'View Checkout',
            'copy_url' => 'Copy Checkout URL',
        ],
        'messages' => [
            'pdf_generated' => 'PDF has been generated.',
            'invoice_sent' => 'Invoice has been sent.',
            'marked_as_paid' => 'Invoice marked as paid.',
        ],
    ],

    'widgets' => [
        'mrr' => [
            'title' => 'Monthly Recurring Revenue',
            'no_previous_data' => 'No previous data',
            'from_last_month' => 'from last month',
        ],
        'active_subscribers' => [
            'title' => 'Active Subscribers',
            'from_last_month' => 'from last month',
        ],
        'churn_rate' => [
            'title' => 'Churn Rate',
            'same_as_last' => 'Same as last month',
        ],
        'trial_conversions' => [
            'title' => 'Trial Conversion Rate',
            'active_trials' => 'Active Trials',
            'currently_trialing' => 'Currently trialing',
        ],
        'revenue_chart' => [
            'title' => 'Revenue Trend (Last 12 Months)',
        ],
        'subscription_distribution' => [
            'title' => 'Subscription Distribution',
        ],
    ],

    'dashboard' => [
        'title' => 'Billing Dashboard',
        'subtitle' => 'Monitor your recurring revenue, subscriber growth, and billing metrics.',
    ],

    'intervals' => [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'every_days' => 'Every :count days',
        'every_weeks' => 'Every :count weeks',
        'every_months' => 'Every :count months',
        'every_years' => 'Every :count years',
    ],
];
