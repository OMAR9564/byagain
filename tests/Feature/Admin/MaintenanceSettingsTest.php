<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Pages\MaintenanceSettings;
use App\Models\AdminActionLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MaintenanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    #[Test]
    public function the_page_loads_with_the_current_values(): void
    {
        Setting::write(Setting::REGISTRATION_OPEN, false);

        Livewire::test(MaintenanceSettings::class)
            ->assertSet('data.registration_open', false)
            ->assertOk();
    }

    #[Test]
    public function switching_registration_off_closes_the_route(): void
    {
        Livewire::test(MaintenanceSettings::class)
            ->set('data.registration_open', false)
            ->set('data.maintenance_mode', false)
            ->set('data.default_review_size', 8)
            ->call('save');

        $this->assertFalse((bool) Setting::read(Setting::REGISTRATION_OPEN));

        // Refused at the route, not merely hidden in the interface (FR-075).
        auth()->logout();
        $this->get('/register')->assertForbidden();
    }

    #[Test]
    public function saving_is_recorded_in_the_audit_trail(): void
    {
        Livewire::test(MaintenanceSettings::class)
            ->set('data.registration_open', true)
            ->set('data.maintenance_mode', true)
            ->set('data.default_review_size', 10)
            ->call('save');

        $log = AdminActionLog::query()->sole();

        $this->assertSame('update_settings', $log->action);
        $this->assertSame(10, $log->context['default_review_size']);
    }
}
