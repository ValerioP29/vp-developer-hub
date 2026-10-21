<?php

use App\Models\Category;
use App\Models\Intervention;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\Timer;
use App\Models\User;
use App\Services\TimeTracking;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

// This integration check deliberately uses independent PHP processes and committed fixtures.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('testing') || DB::getDriverName() !== 'pgsql' || ! str_ends_with(config('database.connections.pgsql.database'), '_test')) {
    fwrite(STDERR, "Run only with APP_ENV=testing and a PostgreSQL database ending in _test.\n");
    exit(1);
}
if (($argv[1] ?? null) === 'worker') {
    Auth::login(User::findOrFail((int) $argv[2]));
    $work = Intervention::findOrFail((int) $argv[3]);
    $deadline = (float) $argv[5];
    while (microtime(true) < $deadline) {
        usleep(1000);
    }
    try {
        $service = app(TimeTracking::class);
        if ($argv[4] === 'timer') {
            $service->start($work);
        } elseif ($argv[4] === 'manual') {
            $service->manual($work, now('Europe/Rome')->subDay()->setTime(9, 0)->format('Y-m-d\TH:i'), 60);
        } else {
            $service->manual($work, now('Europe/Rome')->subDay()->setTime(9, 0)->format('Y-m-d\TH:i'), 90, TimeEntry::findOrFail((int) $argv[6]), 1, 'Concurrent correction test');
        }
        echo 'accepted';
    } catch (ValidationException $e) {
        echo 'conflict';
    }
    exit;
}
$id = bin2hex(random_bytes(5));
$admin = User::create(['name' => 'Concurrency Test', 'email' => "concurrency-$id@example.test", 'role' => 'super_admin', 'password' => Str::password(24)]);
Auth::login($admin);
$tenant = Tenant::create(['name' => 'Concurrency Demo', 'slug' => 'concurrency-'.$id]);
$cat = Category::firstOrCreate(['name' => 'Concurrency maintenance'], ['maintenance' => true]);
$work = Intervention::create(['tenant_id' => $tenant->id, 'category_id' => $cat->id, 'title' => 'Parallel timer test', 'performed_on' => now()->toDateString()]);
$run = function (string $mode, ?int $entryId = null) use ($admin, $work) {
    $deadline = microtime(true) + 2;
    $processes = [];
    for ($i = 0; $i < 2; $i++) {
        $p = new Process([PHP_BINARY, __FILE__, 'worker', (string) $admin->id, (string) $work->id, $mode, (string) $deadline, (string) $entryId], base_path(), null, null, 20);
        $p->start();
        $processes[] = $p;
    }
    $out = [];
    foreach ($processes as $p) {
        $p->wait();
        if (! $p->isSuccessful()) {
            throw new RuntimeException($p->getErrorOutput().$p->getOutput());
        }$out[] = trim($p->getOutput());
    }sort($out);
    if ($out !== ['accepted', 'conflict']) {
        throw new RuntimeException($mode.': unexpected concurrent results '.json_encode($out));
    }
    echo 'PASS '.$mode.": one accepted, one rejected, independent PostgreSQL connections.\n";
};
$run('timer');
if (Timer::where('user_id', $admin->id)->count() !== 1) {
    throw new RuntimeException('Duplicate timer');
}app(TimeTracking::class)->pause($work);
$run('manual');
$entry = TimeEntry::where('user_id', $admin->id)->where('source', 'manual')->firstOrFail();
$run('correction', $entry->id);
if ($entry->fresh()->version !== 2 || $entry->fresh()->seconds !== 5400) {
    throw new RuntimeException('Lost update');
}
echo "PASS unique running timer, overlap rejection and optimistic correction.\n";
