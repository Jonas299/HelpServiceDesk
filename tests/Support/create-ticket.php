<?php

use App\Domain\Tickets\DTOs\CreateTicketData;
use App\Domain\Tickets\Services\TicketService;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'helpdesk_testing') {
    throw new RuntimeException('Testing database required');
}
$ticket = app(TicketService::class)->create(User::findOrFail($argv[1]), new CreateTicketData('Concurrent ticket', 'Concurrency integration test', (int) $argv[2]));
echo $ticket->ticket_number;
