<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\AffiliateRegistrationService;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalRegistration;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Affiliate::query()->delete();
});

it('renders the correct subheading for each approval mode', function (): void {
    $page = new PortalRegistration;

    $reflection = new ReflectionClass($page);

    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setAccessible(true);
    $enabled->setValue($page, true);

    $approvalMode = $reflection->getProperty('approvalMode');
    $approvalMode->setAccessible(true);

    $approvalMode->setValue($page, 'auto');
    expect($page->getSubheading())->toBe('Your affiliate account will be automatically activated.');

    $approvalMode->setValue($page, 'open');
    expect($page->getSubheading())->toBe('Your account will be created with pending status.');

    $approvalMode->setValue($page, 'admin');
    expect($page->getSubheading())->toBe('Your application will be reviewed by an administrator.');

    $approvalMode->setValue($page, 'unknown');
    expect($page->getSubheading())->toBeNull();
});

it('returns a closed subheading when registration is disabled', function (): void {
    $page = new PortalRegistration;

    $reflection = new ReflectionClass($page);

    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setAccessible(true);
    $enabled->setValue($page, false);

    expect($page->getSubheading())->toBe('Registration is currently closed.');
});

it('creates an affiliate through the registration service during registration handling', function (): void {
    $page = new PortalRegistration;

    $captured = ['payload' => null, 'user' => null];

    app()->instance(AffiliateRegistrationService::class, new class($captured)
    {
        /** @var array{payload:mixed, user:mixed} */
        public array $captured;

        public function __construct(array &$captured)
        {
            $this->captured = &$captured;
        }

        /**
         * @param  array<string, mixed>  $data
         */
        public function register(array $data, $user): Affiliate
        {
            $this->captured['payload'] = $data;
            $this->captured['user'] = $user;

            return Affiliate::create([
                'code' => 'REG-' . Str::uuid(),
                'name' => $data['name'],
                'status' => AffiliateStatus::Active,
                'commission_type' => 'percentage',
                'commission_rate' => 500,
                'currency' => 'USD',
                'owner_type' => $user->getMorphClass(),
                'owner_id' => (string) $user->getKey(),
            ]);
        }
    });

    $reflection = new ReflectionClass($page);

    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setAccessible(true);
    $enabled->setValue($page, true);

    $approvalMode = $reflection->getProperty('approvalMode');
    $approvalMode->setAccessible(true);
    $approvalMode->setValue($page, 'admin');

    $method = $reflection->getMethod('handleRegistration');
    $method->setAccessible(true);

    $user = $method->invoke($page, [
        'name' => 'Portal Register User',
        'email' => 'portal-register-user@example.com',
        'password' => 'secret',
        'affiliate_name' => 'My Affiliate',
        'website_url' => 'https://example.com',
    ]);

    expect($user)->toBeInstanceOf(Model::class)
        ->and($user->email)->toBe('portal-register-user@example.com');

    $affiliate = Affiliate::query()->first();
    expect($affiliate)->not->toBeNull()
        ->and($affiliate->name)->toBe('My Affiliate');
});

it('blocks register() and sends a danger notification when disabled', function (): void {
    $page = new PortalRegistration;

    $reflection = new ReflectionClass($page);
    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setAccessible(true);
    $enabled->setValue($page, false);

    $notification = Mockery::mock();
    $notification->shouldReceive('title')->once()->andReturnSelf();
    $notification->shouldReceive('body')->once()->andReturnSelf();
    $notification->shouldReceive('danger')->once()->andReturnSelf();
    $notification->shouldReceive('send')->once();

    Mockery::mock('alias:' . Notification::class)
        ->shouldReceive('make')
        ->once()
        ->andReturn($notification);

    expect($page->register())->toBeNull();
});

it('afterRegister() sends a success notification with mode-specific message', function (): void {
    $page = new PortalRegistration;

    $reflection = new ReflectionClass($page);

    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setAccessible(true);
    $enabled->setValue($page, true);

    $approvalMode = $reflection->getProperty('approvalMode');
    $approvalMode->setAccessible(true);
    $approvalMode->setValue($page, 'open');

    $notification = Mockery::mock();
    $notification->shouldReceive('title')->once()->andReturnSelf();
    $notification->shouldReceive('body')->once()->andReturnSelf();
    $notification->shouldReceive('success')->once()->andReturnSelf();
    $notification->shouldReceive('send')->once();

    Mockery::mock('alias:' . Notification::class)
        ->shouldReceive('make')
        ->once()
        ->andReturn($notification);

    $method = $reflection->getMethod('afterRegister');
    $method->setAccessible(true);
    $method->invoke($page);

    expect(true)->toBeTrue();
});

it('exposes a register action and custom affiliate form components', function (): void {
    $page = new PortalRegistration;

    $reflection = new ReflectionClass($page);

    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setAccessible(true);
    $enabled->setValue($page, true);

    expect($page->getHeading())->toBe('Register as an Affiliate')
        ->and($page->isRegistrationEnabled())->toBeTrue();

    $action = $page->getRegisterFormAction();
    expect(method_exists($action, 'getName') ? $action->getName() : null)->toBe('register');

    $affiliateName = $reflection->getMethod('getAffiliateNameFormComponent');
    $affiliateName->setAccessible(true);

    $websiteUrl = $reflection->getMethod('getWebsiteUrlFormComponent');
    $websiteUrl->setAccessible(true);

    expect($affiliateName->invoke($page))->toBeInstanceOf(TextInput::class)
        ->and($websiteUrl->invoke($page))->toBeInstanceOf(TextInput::class);
});
