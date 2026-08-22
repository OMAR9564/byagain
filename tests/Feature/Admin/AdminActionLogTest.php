<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminActionLog;
use App\Models\User;
use App\Services\Admin\AdminActionLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class AdminActionLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_action_is_recorded_with_its_actor_and_subject(): void
    {
        $admin = User::factory()->admin()->create();
        $subject = User::factory()->create();

        $this->actingAs($admin);

        app(AdminActionLogger::class)->record(
            AdminActionLogger::SUSPEND_USER,
            $subject,
            ['reason' => 'testing'],
        );

        $log = AdminActionLog::query()->sole();

        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame(AdminActionLogger::SUSPEND_USER, $log->action);
        $this->assertSame(User::class, $log->subject_type);
        $this->assertSame($subject->id, $log->subject_id);
        $this->assertSame(['reason' => 'testing'], $log->context);
        $this->assertNotNull($log->created_at);
    }

    #[Test]
    public function a_log_entry_cannot_be_changed(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $log = app(AdminActionLogger::class)->record(AdminActionLogger::SUSPEND_USER);

        // An audit trail that can be edited is not an audit trail (FR-076).
        $this->expectException(RuntimeException::class);

        $log->action = 'something_else';
        $log->save();
    }

    #[Test]
    public function a_log_entry_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $log = app(AdminActionLogger::class)->record(AdminActionLogger::RESEND_EMAIL);

        $this->expectException(RuntimeException::class);

        $log->delete();
    }

    #[Test]
    public function the_table_has_nowhere_to_record_a_change(): void
    {
        // The guards above are application-level. This is the schema refusing
        // to hold an updated_at at all, so even a bypass leaves no way to
        // rewrite history silently.
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('admin_action_logs', 'updated_at'),
        );
    }
}
