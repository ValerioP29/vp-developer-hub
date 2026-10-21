<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Package;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Manutenzione', 'Bug fixing', 'Assistenza', 'Aggiornamento', 'Piccola modifica', 'Diagnostica', 'Rilascio', 'Riunione / formazione', 'Nuovo sviluppo'] as $name) {
            Category::firstOrCreate(['name' => $name], ['maintenance' => $name !== 'Nuovo sviluppo']);
        }
        foreach (['Light' => [300, 15000], 'Standard' => [600, 26000], 'Plus' => [900, 39000], 'Extended' => [1500, 60000]] as $name => [$minutes,$cents]) {
            Package::firstOrCreate(['name' => $name], ['included_minutes' => $minutes, 'price_cents' => $cents]);
        }
    }
}
