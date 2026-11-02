<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class Username
{
    public static function rules(?int $ignore = null): array
    {
        return ['nullable', 'string', 'min:3', 'max:64', 'regex:/\A[a-z0-9][a-z0-9._-]*\z/',
            Rule::unique('users', 'username')->ignore($ignore)];
    }
}
