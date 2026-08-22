<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\Admin\AdminActionLogger;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Instance-wide switches an operator can throw without a deploy (FR-075).
 *
 * These are not user preferences — they belong to the installation, which is
 * why they live in `settings` rather than on any account.
 */
final class MaintenanceSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Instance settings';

    protected string $view = 'filament.pages.maintenance-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->getSchema('form')?->fill([
            'maintenance_mode' => (bool) Setting::read(Setting::MAINTENANCE_MODE, false),
            'registration_open' => (bool) Setting::read(Setting::REGISTRATION_OPEN, true),
            'default_review_size' => (int) Setting::read(
                Setting::DEFAULT_REVIEW_SIZE,
                config('byagain.review.default_size'),
            ),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('maintenance_mode')
                    ->label('Maintenance mode')
                    ->helperText('User-facing pages return 503. The admin panel stays reachable.'),

                Toggle::make('registration_open')
                    ->label('Registration open')
                    ->helperText('When off, /register is refused rather than merely hidden.'),

                TextInput::make('default_review_size')
                    ->label('Default review size')
                    ->numeric()
                    ->minValue(config('byagain.review.min_size'))
                    ->maxValue(config('byagain.review.max_size'))
                    ->helperText('Applies to new accounts. Existing readers keep their own choice.'),
            ])
            ->statePath('data');
    }

    public function save(AdminActionLogger $logger): void
    {
        $data = $this->getSchema('form')?->getState() ?? [];

        Setting::write(Setting::MAINTENANCE_MODE, (bool) ($data['maintenance_mode'] ?? false));
        Setting::write(Setting::REGISTRATION_OPEN, (bool) ($data['registration_open'] ?? true));
        Setting::write(
            Setting::DEFAULT_REVIEW_SIZE,
            (int) ($data['default_review_size'] ?? config('byagain.review.default_size')),
        );

        $logger->record(AdminActionLogger::UPDATE_SETTINGS, null, $data);

        Notification::make()->title('Saved')->success()->send();
    }
}
