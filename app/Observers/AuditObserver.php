<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditObserver
{
    /**
     * Prevent the observer from logging itself.
     */
    private function isAuditLog(Model $model): bool
    {
        return $model instanceof AuditLog;
    }

    /**
     * Helper to get user ID safely.
     */
    private function getUserId(): ?int
    {
        return Auth::check() ? Auth::id() : null;
    }

    public function created(Model $model)
    {
        if ($this->isAuditLog($model)) return;

        AuditLog::create([
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->id,
            'event'          => 'created',
            'new_values'     => json_encode($model->getAttributes()),
            'user_id'        => $this->getUserId(),
        ]);
    }

    public function updated(Model $model)
    {
        if ($this->isAuditLog($model)) return;

        $old = $model->getOriginal();
        $new = $model->getChanges();

        // Only log if attributes actually changed
        if (!empty($new)) {
            AuditLog::create([
                'auditable_type' => get_class($model),
                'auditable_id'   => $model->id,
                'event'          => 'updated',
                'old_values'     => json_encode(array_intersect_key($old, $new)),
                'new_values'     => json_encode($new),
                'user_id'        => $this->getUserId(),
            ]);
        }
    }

    public function deleted(Model $model)
    {
        if ($this->isAuditLog($model)) return;

        AuditLog::create([
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->id,
            'event'          => 'deleted',
            'old_values'     => json_encode($model->getAttributes()),
            'user_id'        => $this->getUserId(),
        ]);
    }
}