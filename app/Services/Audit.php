<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Audit
{
    public static function record(string $event, Model $subject, ?array $before = null, ?array $after = null): void
    {
        $sanitize = fn ($data) => $data === null ? null : json_encode(array_diff_key($data, array_flip(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'path'])), JSON_THROW_ON_ERROR);
        DB::table('audit_logs')->insert(['tenant_id' => $subject instanceof Tenant ? $subject->id : ($subject->tenant_id ?? null), 'user_id' => Auth::id(), 'event' => $event, 'subject_type' => class_basename($subject), 'subject_id' => $subject->id, 'before' => $sanitize($before), 'after' => $sanitize($after), 'created_at' => now()]);
    }
}
