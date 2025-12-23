<?php

declare(strict_types=1);

return [
    'overview' => [
        'title' => 'Billing Overview',
        'welcome' => 'Welcome to your billing portal',
    ],

    'subscriptions' => [
        'title' => 'My Subscriptions',
        'new' => 'New Subscription',
        'empty' => 'You have no active subscriptions.',
        'load_more' => 'Load more',
        'cancel' => 'Cancel',
        'resume' => 'Resume',
        'change_plan' => 'Change Plan',
        'cancel_success' => 'Subscription canceled successfully.',
        'cancel_error' => 'Unable to cancel subscription.',
        'resume_success' => 'Subscription resumed successfully.',
        'resume_error' => 'Unable to resume subscription.',
        'unauthorized' => 'You are not authorized to perform this action.',
    ],

    'payment_methods' => [
        'title' => 'Payment Methods',
        'add' => 'Add Payment Method',
        'empty' => 'No payment methods on file.',
        'default' => 'Default',
        'set_default' => 'Set as Default',
        'delete' => 'Delete',
        'default_updated' => 'Default payment method updated.',
        'deleted' => 'Payment method deleted.',
        'error' => 'An error occurred.',
        'gateway_not_available' => 'Gateway is not available.',
    ],

    'invoices' => [
        'title' => 'Invoices',
        'empty' => 'No invoices yet.',
        'download' => 'Download',
        'view' => 'View',
        'status' => [
            'paid' => 'Paid',
            'open' => 'Open',
            'void' => 'Void',
        ],
    ],
];
