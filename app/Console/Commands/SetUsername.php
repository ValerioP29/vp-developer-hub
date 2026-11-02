<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit;
use App\Support\Username;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SetUsername extends Command
{
    protected $signature = 'hub:set-username {email} {username}';

    protected $description = 'Assegna uno username univoco a un account esistente, senza modificare password o permessi.';

    public function handle(): int
    {
        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($this->argument('email')))])->first();
        if (! $user) {
            $this->error('Account non trovato.');

            return self::FAILURE;
        }

        $data = ['username' => strtolower(trim($this->argument('username')))];
        $validator = Validator::make($data, ['username' => ['required', ...Username::rules($user->id)]]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $data) {
            $before = ['username' => $user->username];
            $user->update($data);
            Audit::record('username_updated', $user, $before, $data);
        });
        $this->info('Username aggiornato: '.$user->username);

        return self::SUCCESS;
    }
}
