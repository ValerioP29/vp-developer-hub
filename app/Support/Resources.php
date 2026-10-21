<?php

namespace App\Support;

use App\Models;

class Resources
{
    public static function all(): array
    {
        $status = ['active' => 'Attivo', 'suspended' => 'Sospeso', 'archived' => 'Archiviato'];
        $models = ['package' => 'Pacchetto a ore', 'payg' => 'A consuntivo', 'fixed' => 'Canone fisso'];
        $f = fn ($label, $type = 'text', $rules = 'nullable|string|max:255', $options = []) => compact('label', 'type', 'rules', 'options');
        $name = $f('Nome', 'text', 'required|string|max:180');
        $description = $f('Descrizione', 'textarea', 'nullable|string|max:10000');
        $tenant = $f('Azienda', 'tenant', 'required|integer');
        $active = $f('Stato', 'select', 'required|in:active,suspended,archived', $status);
        $money = fn ($label) => $f($label.' (€)', 'money', 'required|numeric|min:0|max:1000000');
        $minutes = $f('Minuti inclusi', 'number', 'required|integer|min:0|max:1000000');

        return [
            'clients' => ['model' => Models\Tenant::class, 'title' => 'Clienti', 'singular' => 'cliente', 'admin' => true, 'fields' => [
                'name' => $f('Ragione sociale', 'text', 'required|string|max:180'), 'slug' => $f('Identificativo URL', 'text', 'required|alpha_dash|max:100'),
                'vat_number' => $f('Partita IVA'), 'tax_code' => $f('Codice fiscale'), 'email' => $f('Email aziendale', 'email', 'nullable|email|max:180'),
                'phone' => $f('Telefono'), 'contact_name' => $f('Referente'), 'address' => $f('Indirizzo', 'textarea'), 'status' => $active,
                'email_notifications' => $f('Abilita notifiche email', 'checkbox', 'boolean')]],
            'projects' => ['model' => Models\Project::class, 'title' => 'Progetti', 'singular' => 'progetto', 'fields' => ['tenant_id' => $tenant, 'name' => $name, 'description' => $description, 'status' => $active]],
            'applications' => ['model' => Models\Application::class, 'title' => 'Applicativi', 'singular' => 'applicativo', 'fields' => ['tenant_id' => $tenant, 'project_id' => $f('Progetto', 'relation', 'nullable|integer', ['projects']), 'name' => $name, 'description' => $description, 'status' => $active]],
            'services' => ['model' => Models\Service::class, 'title' => 'Servizi', 'singular' => 'servizio', 'fields' => ['tenant_id' => $tenant, 'application_id' => $f('Applicativo', 'relation', 'nullable|integer', ['applications']), 'name' => $name, 'description' => $description, 'status' => $active]],
            'contracts' => ['model' => Models\Contract::class, 'title' => 'Contratti', 'singular' => 'contratto', 'ability' => 'billing', 'fields' => [
                'tenant_id' => $tenant, 'name' => $name, 'model' => $f('Modello economico', 'select', 'required|in:package,payg,fixed', $models),
                'starts_on' => $f('Valido dal', 'date', 'required|date'), 'ends_on' => $f('Valido fino al', 'date', 'nullable|date|after_or_equal:starts_on'),
                'fee_cents' => $money('Importo mensile'), 'hourly_rate_cents' => $money('Tariffa oraria'), 'included_minutes' => $minutes,
                'carryover' => $f('Consenti riporto delle ore residue', 'checkbox', 'boolean'),
                'included_activities' => $f('Attività incluse', 'textarea', 'nullable|string|max:10000'),
                'excluded_activities' => $f('Attività escluse', 'textarea', 'nullable|string|max:10000'), 'extra_terms' => $f('Condizioni per gli extra', 'textarea', 'nullable|string|max:10000'), 'status' => $active]],
            'packages' => ['model' => Models\Package::class, 'title' => 'Catalogo pacchetti', 'singular' => 'pacchetto', 'admin' => true, 'fields' => ['name' => $name, 'included_minutes' => $minutes, 'price_cents' => $money('Prezzo indicativo'), 'active' => $f('Disponibile', 'checkbox', 'boolean')]],
            'categories' => ['model' => Models\Category::class, 'title' => 'Categorie', 'singular' => 'categoria', 'admin' => true, 'fields' => ['name' => $name, 'maintenance' => $f('Attività di manutenzione', 'checkbox', 'boolean')]],
            'interventions' => ['model' => Models\Intervention::class, 'title' => 'Interventi', 'singular' => 'intervento', 'fields' => [
                'tenant_id' => $tenant, 'project_id' => $f('Progetto', 'relation', 'nullable|integer', ['projects']),
                'application_id' => $f('Applicativo', 'relation', 'nullable|integer', ['applications']), 'ticket_id' => $f('Richiesta collegata', 'relation', 'nullable|integer', ['tickets']),
                'category_id' => $f('Categoria', 'relation', 'required|integer', ['categories']),
                'performed_on' => $f('Data attività', 'date', 'required|date'), 'title' => $f('Titolo', 'text', 'required|string|max:180'),
                'description' => $description, 'public_notes' => $f('Note per il cliente', 'textarea', 'nullable|string|max:10000'),
                'internal_notes' => $f('Note interne riservate', 'textarea', 'nullable|string|max:10000'),
                'status' => $f('Stato', 'select', 'required|in:planned,in_progress,completed', ['planned' => 'Da iniziare', 'in_progress' => 'In corso', 'completed' => 'Completato']),
                'billable' => $f('Fatturabile', 'checkbox', 'boolean'), 'uses_package' => $f('Incluso nel monte ore', 'checkbox', 'boolean')]],
            'tickets' => ['model' => Models\Ticket::class, 'title' => 'Richieste assistenza', 'singular' => 'richiesta', 'ability' => 'tickets', 'fields' => [
                'tenant_id' => $tenant, 'application_id' => $f('Applicativo', 'relation', 'nullable|integer', ['applications']),
                'title' => $f('Oggetto', 'text', 'required|string|max:180'), 'description' => $f('Descrivi la richiesta', 'textarea', 'required|string|max:10000'),
                'status' => $f('Stato', 'select', 'required|in:new,review,in_progress,waiting,resolved,closed', self::ticketStatuses()),
                'priority' => $f('Priorità', 'select', 'required|in:low,normal,high,urgent', ['low' => 'Bassa', 'normal' => 'Normale', 'high' => 'Alta', 'urgent' => 'Urgente'])]],
        ];
    }

    public static function get(string $key): array
    {
        return self::all()[$key] ?? abort(404);
    }

    public static function ticketStatuses(): array
    {
        return ['new' => 'Nuova', 'review' => 'In valutazione', 'in_progress' => 'In lavorazione', 'waiting' => 'In attesa del cliente', 'resolved' => 'Risolta', 'closed' => 'Chiusa'];
    }

    public static function money(int $cents): string
    {
        return '€ '.number_format($cents / 100, 2, ',', '.');
    }

    public static function hours(int $seconds): string
    {
        return number_format($seconds / 3600, 2, ',', '.').' h';
    }

    public static function label(string $value): string
    {
        return (self::ticketStatuses() + ['active' => 'Attivo', 'suspended' => 'Sospeso', 'archived' => 'Archiviato', 'planned' => 'Da iniziare', 'completed' => 'Completato', 'pending' => 'In attesa', 'approved' => 'Approvata', 'rejected' => 'Rifiutata', 'to_invoice' => 'Da fatturare', 'invoiced' => 'Fatturato', 'paid' => 'Pagato', 'package' => 'Pacchetto', 'payg' => 'A consuntivo', 'fixed' => 'Canone fisso', 'normal' => 'Normale', 'urgent' => 'Urgente', 'high' => 'Alta', 'low' => 'Bassa'])[$value] ?? $value;
    }
}
