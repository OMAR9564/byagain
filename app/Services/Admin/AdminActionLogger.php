<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AdminActionLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the audit trail.
 *
 * Every administrator action that touches somebody's account goes through
 * here. The rows are immutable — the model throws on update and delete, and
 * the table has no `updated_at` for a change to be written to (FR-076).
 *
 * The repository is public and byagain is single-tenant, so an administrator
 * is often the only user. That is exactly why the log matters: it is the
 * record you check when you cannot remember whether you did something in
 * March, and it is the answer when a reader asks what happened to their
 * account.
 */
final class AdminActionLogger
{
    public const string SUSPEND_USER = 'suspend_user';

    public const string RESTORE_USER = 'restore_user';

    public const string RESEND_VERIFICATION = 'resend_verification';

    public const string RESEND_EMAIL = 'resend_email';

    public const string UPDATE_SETTINGS = 'update_settings';

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $action, ?Model $subject = null, array $context = []): AdminActionLog
    {
        $log = new AdminActionLog;

        $log->admin_id = (int) Auth::id();
        $log->action = $action;
        $log->context = $context === [] ? null : $context;

        if ($subject !== null) {
            $log->subject_type = $subject::class;
            $log->subject_id = (int) $subject->getKey();
        }

        $log->save();

        return $log;
    }
}
