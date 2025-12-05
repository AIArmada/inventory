<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\AffiliateRegistrationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Register as FilamentRegister;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class PortalRegistration extends FilamentRegister
{
    protected static string $view = 'filament-affiliates::pages.portal.registration';

    protected bool $registrationEnabled;

    protected string $approvalMode;

    public function mount(): void
    {
        $this->registrationEnabled = (bool) config('affiliates.registration.enabled', true);
        $this->approvalMode = (string) config('affiliates.registration.approval_mode', 'admin');

        if (! $this->registrationEnabled) {
            $this->redirect(filament()->getLoginUrl());

            return;
        }

        parent::mount();
    }

    /**
     * @return array<int, Component>
     */
    protected function getForms(): array
    {
        return [
            $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getAffiliateNameFormComponent(),
                        $this->getWebsiteUrlFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getAffiliateNameFormComponent(): Component
    {
        return TextInput::make('affiliate_name')
            ->label(__('Affiliate/Business Name'))
            ->required()
            ->maxLength(255);
    }

    protected function getWebsiteUrlFormComponent(): Component
    {
        return TextInput::make('website_url')
            ->label(__('Website URL'))
            ->url()
            ->maxLength(255);
    }

    public function register(): ?Model
    {
        if (! $this->registrationEnabled) {
            Notification::make()
                ->title(__('Registration Disabled'))
                ->body(__('Affiliate registration is currently not available.'))
                ->danger()
                ->send();

            return null;
        }

        $data = $this->form->getState();

        $user = $this->getUserModel()::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $this->createAffiliateForUser($user, $data);

        $this->sendEmailVerificationNotification($user);

        auth()->guard($this->getGuard())->login($user);

        session()->regenerate();

        return $user;
    }

    protected function createAffiliateForUser(Model $user, array $data): Affiliate
    {
        $registrationService = app(AffiliateRegistrationService::class);

        return $registrationService->register([
            'name' => $data['affiliate_name'],
            'contact_email' => $data['email'],
            'website_url' => $data['website_url'] ?? null,
        ], $user);
    }

    protected function getRegisterFormAction(): Action
    {
        return Action::make('register')
            ->label(__('Register as Affiliate'))
            ->submit('register');
    }

    public function getHeading(): string
    {
        return __('Register as an Affiliate');
    }

    public function getSubheading(): ?string
    {
        if (! $this->registrationEnabled) {
            return __('Registration is currently closed.');
        }

        return match ($this->approvalMode) {
            'auto' => __('Your affiliate account will be automatically activated.'),
            'open' => __('Your account will be created with pending status.'),
            'admin' => __('Your application will be reviewed by an administrator.'),
            default => null,
        };
    }

    public function isRegistrationEnabled(): bool
    {
        return $this->registrationEnabled;
    }

    protected function afterRegister(): void
    {
        $message = match ($this->approvalMode) {
            'auto' => __('Your affiliate account has been activated. You can start sharing links!'),
            'open' => __('Your affiliate account has been created. It is currently pending activation.'),
            'admin' => __('Your affiliate application has been submitted. We will notify you once it is reviewed.'),
            default => __('Your affiliate account has been created.'),
        };

        Notification::make()
            ->title(__('Registration Successful'))
            ->body($message)
            ->success()
            ->send();
    }
}
