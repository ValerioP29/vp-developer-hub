<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Intervention;
use App\Models\Project;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Billing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Demo extends Command
{
    protected $signature = 'hub:demo {--credentials-file= : File privato in cui salvare le password generate}';

    protected $description = 'Crea dati fittizi solo in ambiente local/testing; non attiva integrazioni esterne.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Demo disponibile solo in local/testing.');

            return self::FAILURE;
        }
        if (User::where('email', 'admin@vp-hub.test')->exists()) {
            $this->error('Demo già presente. Nessun dato modificato.');

            return self::FAILURE;
        }
        $passwords = ['admin' => Str::password(24), 'client' => Str::password(24), 'user' => Str::password(24)];
        $this->call('db:seed', ['--force' => true]);
        DB::transaction(function () use ($passwords) {
            $admin = User::create(['name' => 'Amministratore Demo', 'email' => 'admin@vp-hub.test', 'password' => $passwords['admin'], 'role' => 'super_admin']);
            Auth::login($admin);
            $names = ['GED · Demo', 'Studio Orizzonte · Demo', 'Forma Digitale · Demo'];
            foreach ($names as $i => $name) {
                $tenant = Tenant::create(['name' => $name, 'slug' => ['ged-demo', 'orizzonte-demo', 'forma-demo'][$i], 'email' => 'ufficio'.($i + 1).'@example.test', 'contact_name' => 'Referente Demo '.($i + 1), 'status' => 'active']);
                if ($i === 0) {
                    User::create(['name' => 'Responsabile Demo', 'email' => 'cliente@vp-hub.test', 'password' => $passwords['client'], 'role' => 'client_admin', 'tenant_id' => $tenant->id]);
                    User::create(['name' => 'Collaboratore Demo', 'email' => 'utente@vp-hub.test', 'password' => $passwords['user'], 'role' => 'client_user', 'tenant_id' => $tenant->id]);
                }
                $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'Continuità operativa', 'description' => 'Assistenza e miglioramento dei servizi digitali.']);
                $apps = $i === 0 ? ['Gestionale GED', 'Sistema presenze e timbrature', 'PWA dipendenti', 'Green App / modulo Verde', 'CRM aziendale'] : ['Portale aziendale', 'Area collaboratori'];
                $created = [];
                foreach ($apps as $n) {
                    $created[] = Application::create(['tenant_id' => $tenant->id, 'project_id' => $project->id, 'name' => $n, 'description' => 'Applicativo dimostrativo. Nessuna connessione a software esterni.']);
                }
                Service::create(['tenant_id' => $tenant->id, 'application_id' => $created[0]->id, 'name' => 'Assistenza applicativa', 'description' => 'Manutenzione e supporto sui servizi concordati.']);
                $model = ['package', 'payg', 'fixed'][$i];
                $fee = [26000, 0, 40000][$i];
                $minutes = [600, 0, 900][$i];
                $contract = Contract::create(['tenant_id' => $tenant->id, 'name' => ['Assistenza Standard', 'Assistenza a consuntivo', 'Continuità Plus'][$i], 'model' => $model, 'starts_on' => now('Europe/Rome')->subMonths(8)->startOfMonth()->toDateString(), 'fee_cents' => $fee, 'hourly_rate_cents' => 3500, 'included_minutes' => $minutes, 'included_activities' => 'Manutenzione, assistenza e correzione anomalie.', 'excluded_activities' => 'Nuovi sviluppi, integrazioni e modifiche strutturali.', 'extra_terms' => 'Gli extra richiedono approvazione esplicita del referente.']);
                $titles = ['Aggiornamento e verifica funzionalità', 'Ottimizzazione ricerca documenti', 'Supporto ai referenti aziendali', 'Correzione esportazione presenze', 'Controllo periodico applicativo', 'Miglioramento navigazione mobile'];
                for ($m = 5; $m >= 0; $m--) {
                    $month = now('Europe/Rome')->startOfMonth()->subMonths($m);
                    app(Billing::class)->activate($contract, ['month' => $month->format('Y-m'), 'label' => $contract->name, 'included_minutes' => $minutes, 'fee_cents' => $fee, 'hourly_rate_cents' => 3500]);
                    for ($j = 0; $j < 3; $j++) {
                        $day = $month->copy()->addDays(2 + $j * 4)->setTime(8 + $i * 3, 0);
                        if ($day->isFuture()) {
                            continue;
                        }
                        $work = Intervention::create(['tenant_id' => $tenant->id, 'project_id' => $project->id, 'application_id' => $created[$j % count($created)]->id, 'category_id' => Category::where('maintenance', true)->skip($j)->first()->id, 'performed_on' => $day->toDateString(), 'title' => $titles[($j + $m) % count($titles)], 'description' => 'Verifica del funzionamento, intervento mirato e controllo del risultato con dati dimostrativi.', 'public_notes' => 'Attività completata. Il servizio è regolarmente disponibile.', 'internal_notes' => 'Nota dimostrativa riservata all’amministratore.', 'status' => 'completed', 'billable' => $j !== 2, 'uses_package' => true]);
                        $duration = 30 + (($m * 13 + $j * 17 + $i * 7) % 65);
                        TimeEntry::create(['tenant_id' => $tenant->id, 'intervention_id' => $work->id, 'user_id' => $admin->id, 'started_at' => $day->copy()->utc(), 'ended_at' => $day->copy()->addMinutes($duration)->utc(), 'seconds' => $duration * 60]);
                    }
                    if ($m > 0) {
                        app(Billing::class)->finalize($tenant->id, $month->format('Y-m'));
                    }
                }
                $ticket = Ticket::create(['tenant_id' => $tenant->id, 'application_id' => $created[0]->id, 'created_by' => $admin->id, 'title' => ['Verifica esportazione del riepilogo', 'Nuova vista per i collaboratori', 'Aggiornamento contenuti portale'][$i], 'description' => 'Vorremmo verificare il comportamento di questa funzione. Dati interamente dimostrativi.', 'status' => ['in_progress', 'new', 'waiting'][$i], 'priority' => 'normal']);
                TicketComment::create(['tenant_id' => $tenant->id, 'ticket_id' => $ticket->id, 'user_id' => $admin->id, 'body' => 'Richiesta ricevuta. Gli aggiornamenti verranno condivisi in questa conversazione.']);
                Intervention::create(['tenant_id' => $tenant->id, 'application_id' => $created[0]->id, 'ticket_id' => $ticket->id, 'category_id' => Category::first()->id, 'performed_on' => now('Europe/Rome')->toDateString(), 'title' => 'Analisi della richiesta #'.$ticket->id, 'description' => 'Verifica preliminare della segnalazione.', 'status' => 'in_progress', 'billable' => true, 'uses_package' => true]);
            }
            Auth::logout();
        });
        $content = "VP Developer Hub — credenziali DEMO locali\n\nadmin@vp-hub.test: {$passwords['admin']}\ncliente@vp-hub.test: {$passwords['client']}\nutente@vp-hub.test: {$passwords['user']}\n\nMFA da configurare al primo accesso amministratore. Non utilizzare in produzione.\n";
        if ($path = $this->option('credentials-file')) {
            file_put_contents($path, $content);
            chmod($path, 0600);
            $this->info('Credenziali salvate nel file privato indicato.');
        } else {
            $this->line($content);
        }

        return self::SUCCESS;
    }
}
