<?php

use Laravel\Fortify\Features;

return ['guard' => 'web', 'passwords' => 'users', 'username' => 'login', 'email' => 'email', 'lowercase_usernames' => true,
    'home' => '/dashboard', 'prefix' => '', 'domain' => null, 'middleware' => ['web'], 'views' => true,
    'limiters' => ['login' => 'login'],
    'features' => [Features::resetPasswords()],
];
