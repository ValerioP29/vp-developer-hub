<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        // Old in-progress sessions must not resume removed verification screens.
        $request->session()->forget(['url.intended', 'login.id', 'login.remember']);

        return $request->wantsJson()
            ? response()->json(['redirect' => '/dashboard'])
            : redirect('/dashboard');
    }
}
