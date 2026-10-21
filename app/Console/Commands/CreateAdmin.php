<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'hub:create-admin {--email=} {--name=}';

    protected $description = 'Crea un Super Admin. Password richiesta in modo interattivo, MFA obbligatoria al primo accesso.';

    public function handle(): int
    {
        $data = ['name' => $this->option('name') ?: $this->ask('Nome amministratore'), 'email' => mb_strtolower($this->option('email') ?: $this->ask('Email amministratore')), 'password' => $this->secret('Password (almeno 12 caratteri, maiuscole, minuscole, numeri e simboli)')];
        $validator = Validator::make($data, ['name' => 'required|string|max:100', 'email' => 'required|email|unique:users|max:180', 'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $user = User::create($data + ['role' => 'super_admin', 'active' => true]);
        Audit::record('admin_created', $user, null, ['email' => $user->email]);
        $this->info('Amministratore creato. Completa la configurazione MFA al primo accesso.');

        return self::SUCCESS;
    }
}
