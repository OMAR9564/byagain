<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_ordinary_reader_is_refused(): void
    {
        // 403, not 404: the panel's existence is not a secret, and a signed-in
        // non-admin deserves a straight answer (FR-069).
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    #[Test]
    public function a_signed_out_visitor_is_sent_to_sign_in(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    #[Test]
    public function an_administrator_gets_in(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk();
    }

    #[Test]
    public function a_suspended_administrator_is_refused(): void
    {
        $admin = User::factory()->admin()->suspended()->create();

        // The role is not a bypass. A suspended account is suspended.
        $this->actingAs($admin)->get('/admin')->assertForbidden();
    }

    #[Test]
    public function the_panel_reads_across_accounts(): void
    {
        $admin = User::factory()->admin()->create();

        $reader = User::factory()->create();
        $source = Source::factory()->for($reader)->create(['title' => 'Somebody Elses Book']);
        Highlight::factory()->for($reader)->for($source)->create();

        // The ownership scope is dropped here by design — this is the only
        // kind of place allowed to (Constitution art. III).
        $this->actingAs($admin)
            ->get('/admin/sources')
            ->assertOk()
            ->assertSee('Somebody Elses Book');
    }

    #[Test]
    public function a_reader_cannot_reach_any_admin_screen(): void
    {
        $reader = User::factory()->create();

        foreach (['/admin', '/admin/users', '/admin/sources', '/admin/highlights', '/admin/email-deliveries'] as $path) {
            $this->actingAs($reader)->get($path)->assertForbidden();
        }
    }
}
