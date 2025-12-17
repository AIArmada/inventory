<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\RecoveryTemplate;

describe('RecoveryTemplate', function (): void {
    it('can be created with required attributes', function (): void {
        $template = RecoveryTemplate::create([
            'name' => 'Standard Recovery Email',
            'description' => 'Default template for abandoned cart recovery',
            'type' => 'email',
            'status' => 'active',
            'is_default' => true,
            'email_subject' => 'You forgot something!',
            'email_body_html' => '<p>Hi {{customer_name}}, you left items in your cart.</p>',
            'email_body_text' => 'Hi {{customer_name}}, you left items in your cart.',
        ]);

        expect($template)->toBeInstanceOf(RecoveryTemplate::class);
        expect($template->id)->not->toBeNull();
        expect($template->name)->toBe('Standard Recovery Email');
        expect($template->type)->toBe('email');
        expect($template->is_default)->toBeTrue();
    });

    it('returns table name from config', function (): void {
        $template = new RecoveryTemplate();
        $tableName = $template->getTable();

        expect($tableName)->toContain('recovery_templates');
    });

    it('renders subject with variables', function (): void {
        $template = RecoveryTemplate::create([
            'name' => 'Test Template',
            'type' => 'email',
            'status' => 'active',
            'email_subject' => 'Hi {{name}}, complete your order!',
        ]);

        $rendered = $template->renderSubject(['name' => 'John']);

        expect($rendered)->toBe('Hi John, complete your order!');
    });

    it('renders HTML body with variables', function (): void {
        $template = RecoveryTemplate::create([
            'name' => 'Test Template',
            'type' => 'email',
            'status' => 'active',
            'email_body_html' => '<p>Cart total: {{cart_total}}</p>',
        ]);

        $rendered = $template->renderHtmlBody(['cart_total' => '$150.00']);

        expect($rendered)->toBe('<p>Cart total: $150.00</p>');
    });

    it('renders text body with variables', function (): void {
        $template = RecoveryTemplate::create([
            'name' => 'Test Template',
            'type' => 'email',
            'status' => 'active',
            'email_body_text' => 'Hi {{name}}, your cart expires in {{hours}} hours.',
        ]);

        $rendered = $template->renderTextBody(['name' => 'Jane', 'hours' => '24']);

        expect($rendered)->toBe('Hi Jane, your cart expires in 24 hours.');
    });

    it('renders SMS body with variables', function (): void {
        $template = RecoveryTemplate::create([
            'name' => 'SMS Template',
            'type' => 'sms',
            'status' => 'active',
            'sms_body' => 'Hi {{name}}! Complete your order: {{link}}',
        ]);

        $rendered = $template->renderSmsBody([
            'name' => 'John',
            'link' => 'https://example.com/cart',
        ]);

        expect($rendered)->toBe('Hi John! Complete your order: https://example.com/cart');
    });

    it('renders push notification with variables', function (): void {
        $template = RecoveryTemplate::create([
            'name' => 'Push Template',
            'type' => 'push',
            'status' => 'active',
            'push_title' => 'Complete your order, {{name}}!',
            'push_body' => 'You have {{count}} items waiting',
            'push_icon' => 'cart-icon.png',
            'push_action_url' => 'https://example.com/cart/{{cart_id}}',
        ]);

        $rendered = $template->renderPush([
            'name' => 'Jane',
            'count' => '3',
            'cart_id' => 'abc123',
        ]);

        expect($rendered['title'])->toBe('Complete your order, Jane!');
        expect($rendered['body'])->toBe('You have 3 items waiting');
        expect($rendered['icon'])->toBe('cart-icon.png');
        expect($rendered['action_url'])->toBe('https://example.com/cart/abc123');
    });

    it('handles null template fields gracefully', function (): void {
        $template = RecoveryTemplate::create([
            'name' => 'Minimal Template',
            'type' => 'email',
            'status' => 'active',
        ]);

        expect($template->renderSubject([]))->toBe('');
        expect($template->renderHtmlBody([]))->toBe('');
        expect($template->renderTextBody([]))->toBe('');
        expect($template->renderSmsBody([]))->toBe('');
    });

    it('calculates open rate correctly', function (): void {
        $template = RecoveryTemplate::create([
            'name' => 'Test Template',
            'type' => 'email',
            'status' => 'active',
            'times_used' => 100,
            'times_opened' => 45,
        ]);

        expect($template->getOpenRate())->toBe(0.45);
    });

    it('calculates click rate correctly', function (): void {
        $template = RecoveryTemplate::create([
            'name' => 'Test Template',
            'type' => 'email',
            'status' => 'active',
            'times_used' => 100,
            'times_clicked' => 20,
        ]);

        expect($template->getClickRate())->toBe(0.2);
    });

    it('calculates conversion rate correctly', function (): void {
        $template = RecoveryTemplate::create([
            'name' => 'Test Template',
            'type' => 'email',
            'status' => 'active',
            'times_used' => 100,
            'times_converted' => 12,
        ]);

        expect($template->getConversionRate())->toBe(0.12);
    });

    it('handles zero usage for rate calculations', function (): void {
        $template = RecoveryTemplate::create([
            'name' => 'New Template',
            'type' => 'email',
            'status' => 'draft',
            'times_used' => 0,
        ]);

        expect($template->getOpenRate())->toBe(0.0);
        expect($template->getClickRate())->toBe(0.0);
        expect($template->getConversionRate())->toBe(0.0);
    });

    it('replaces multiple variables in content', function (): void {
        $template = RecoveryTemplate::create([
            'name' => 'Multi-var Template',
            'type' => 'email',
            'status' => 'active',
            'email_subject' => '{{greeting}} {{name}}, save {{discount}}% today!',
        ]);

        $rendered = $template->renderSubject([
            'greeting' => 'Hello',
            'name' => 'Customer',
            'discount' => '15',
        ]);

        expect($rendered)->toBe('Hello Customer, save 15% today!');
    });
});
