<?php

return [
    'timezone' => 'Europe/Rome',
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))))),
    'notifications' => env('HUB_EMAIL_NOTIFICATIONS', false),
    'upload_max_kb' => (int) env('HUB_UPLOAD_MAX_KB', 10240),
    'audit_retention_days' => (int) env('HUB_AUDIT_RETENTION_DAYS', 730),
    'session_retention_days' => (int) env('HUB_SESSION_RETENTION_DAYS', 7),
];
