<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantData extends Command
{
    protected $signature = 'hub:tenant-data {tenant} {--export= : Percorso file ZIP privato} {--purge : Cancella definitivamente dopo backup e autorizzazione} {--confirm= : Frase esatta DELETE:slug}';

    protected $description = 'Esporta dati e documenti o cancella un tenant archiviato; operazione controllata e senza cascata implicita.';

    private array $tables = ['adjustments', 'summaries', 'documents', 'approvals', 'timers', 'time_entries', 'interventions', 'ticket_comments', 'tickets', 'activations', 'contracts', 'services', 'applications', 'projects', 'audit_logs'];

    public function handle(): int
    {
        $tenant = Tenant::withoutGlobalScopes()->findOrFail((int) $this->argument('tenant'));
        if ($path = $this->option('export')) {
            if (file_exists($path)) {
                $this->error('Il file di destinazione esiste già.');

                return self::FAILURE;
            }
            $zip = new \ZipArchive;
            if ($zip->open($path, \ZipArchive::CREATE) !== true) {
                return self::FAILURE;
            }
            $data = ['tenant' => $tenant->toArray()];
            foreach ($this->tables as $table) {
                $data[$table] = DB::table($table)->where('tenant_id', $tenant->id)->get()->map(function ($row) {
                    $a = (array) $row;
                    unset($a['path']);

                    return $a;
                })->all();
            }
            $data['users'] = DB::table('users')->where('tenant_id', $tenant->id)->get(['id', 'name', 'email', 'role', 'active', 'permissions', 'created_at']);
            $zip->addFromString('data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            foreach (DB::table('documents')->where('tenant_id', $tenant->id)->get() as $doc) {
                if (! Storage::disk('local')->exists($doc->path)) {
                    $zip->close();
                    unlink($path);
                    $this->error('Documento mancante: esportazione annullata.');

                    return self::FAILURE;
                }
                $zip->addFile(Storage::disk('local')->path($doc->path), 'documents/'.$doc->id.'-'.basename($doc->original_name));
            }
            $zip->close();
            chmod($path, 0600);
            Audit::record('tenant_exported', $tenant);
            $this->info('Esportazione amministrativa completata. Include note interne: revisione necessaria prima della consegna.');

            return self::SUCCESS;
        }
        if (! $this->option('purge')) {
            $this->line('Nessuna modifica. Usa --export oppure --purge --confirm=DELETE:slug per una cancellazione autorizzata.');

            return self::SUCCESS;
        }
        if ($tenant->status !== 'archived' || $this->option('confirm') !== 'DELETE:'.$tenant->slug) {
            $this->error('Richiesti tenant archiviato e conferma esatta DELETE:'.$tenant->slug);

            return self::FAILURE;
        }
        $files = DB::table('documents')->where('tenant_id', $tenant->id)->pluck('path')->all();
        DB::transaction(function () use ($tenant) {
            DB::table('tenants')->where('id', $tenant->id)->lockForUpdate()->first();
            if (DB::table('timers')->where('tenant_id', $tenant->id)->whereNotNull('started_at')->exists()) {
                throw new \RuntimeException('Interrompere prima i timer attivi.');
            }
            $users = DB::table('users')->where('tenant_id', $tenant->id)->get(['id', 'email']);
            DB::table('sessions')->whereIn('user_id', $users->pluck('id'))->delete();
            DB::table('password_reset_tokens')->whereIn('email', $users->pluck('email'))->delete();
            foreach ($this->tables as $table) {
                DB::table($table)->where('tenant_id', $tenant->id)->delete();
            }
            DB::table('users')->where('tenant_id', $tenant->id)->delete();
            DB::table('tenants')->where('id', $tenant->id)->delete();
        });
        Storage::disk('local')->delete($files);
        $this->info('Dati del tenant eliminati. Gestire separatamente retention dei backup e copie esportate.');

        return self::SUCCESS;
    }
}
