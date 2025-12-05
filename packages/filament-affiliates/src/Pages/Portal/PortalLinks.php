<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;
use AIArmada\FilamentAffiliates\Concerns\InteractsWithAffiliate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;
use Livewire\Attributes\Computed;

class PortalLinks extends Page
{
    use InteractsWithAffiliate;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament-affiliates::pages.portal.links';

    public string $targetUrl = '';

    public ?string $generatedLink = null;

    public static function getNavigationLabel(): string
    {
        return __('Links');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Affiliate Links');
    }

    public function mount(): void
    {
        $this->targetUrl = url('/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $affiliate = $this->getAffiliate();

        return [
            'affiliate' => $affiliate,
            'hasAffiliate' => $this->hasAffiliate(),
            'affiliateCode' => $affiliate?->code,
            'defaultLink' => $this->getDefaultLink(),
        ];
    }

    #[Computed]
    public function getDefaultLink(): ?string
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return null;
        }

        try {
            return app(AffiliateLinkGenerator::class)->generate(
                $affiliate->code,
                url('/')
            );
        } catch (InvalidArgumentException) {
            // Fallback to simple URL parameter if link generator fails validation
            $param = config('affiliates.links.parameter', 'aff');

            return url('/').'?'.$param.'='.$affiliate->code;
        }
    }

    public function generateLink(): void
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            Notification::make()
                ->title(__('No affiliate account'))
                ->danger()
                ->send();

            return;
        }

        if (empty($this->targetUrl)) {
            $this->targetUrl = url('/');
        }

        try {
            $this->generatedLink = app(AffiliateLinkGenerator::class)->generate(
                $affiliate->code,
                $this->targetUrl
            );

            Notification::make()
                ->title(__('Link generated successfully'))
                ->success()
                ->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title(__('Failed to generate link'))
                ->body(__('The provided URL is invalid or not allowed.'))
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateLink')
                ->label(__('Generate Link'))
                ->form([
                    TextInput::make('url')
                        ->label(__('Target URL'))
                        ->url()
                        ->required()
                        ->default(url('/'))
                        ->placeholder('https://example.com/product'),
                ])
                ->action(function (array $data): void {
                    $this->targetUrl = $data['url'];
                    $this->generateLink();
                }),
        ];
    }
}
