<?php

use Laravel\Fortify\Features;

return ['guard' => 'web', 'passwords' => 'users', 'username' => 'email', 'email' => 'email', 'lowercase_usernames' => true,
    'home' => '/dashboard', 'prefix' => '', 'domain' => null, 'middleware' => ['web'], 'views' => true,
    'limiters' => ['login' => 'login', 'two-factor' => 'two-factor'],
    'features' => [Features::resetPasswords(), Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])],
];
