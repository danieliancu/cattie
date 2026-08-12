<?php

namespace App\Domain\Admin\Actions;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;

class RecordAdminAudit
{
    public function handle(string $action, Model $subject, array $before = [], array $after = []): AdminAuditLog
    {
        return AdminAuditLog::create(['admin_user_id' => auth()->id(), 'action' => $action, 'subject_type' => $subject::class, 'subject_id' => $subject->getKey(), 'before' => $before ?: null, 'after' => $after ?: null]);
    }
}
