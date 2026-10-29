<?php

namespace Database\Seeders;

use App\Domain\Tickets\DTOs\CreateTicketData;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Services\TicketObserverService;
use App\Domain\Tickets\Services\TicketService;
use App\Domain\Users\Enums\Role;
use App\Models\Company;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Los datos demo son solo para local/testing.');
        }
        $password = 'password';
        $admin = User::updateOrCreate(['email' => 'admin@example.test'], ['name' => 'Administrador demo', 'password' => $password, 'role' => Role::Admin, 'active' => true]);
        $agents = collect([1, 2])->map(fn ($i) => User::updateOrCreate(['email' => "agent{$i}@example.test"], ['name' => "Agente {$i}", 'password' => $password, 'role' => Role::Agent, 'active' => true]));
        $categories = collect(['Soporte técnico', 'Redes', 'Servidores', 'Seguridad informática', 'Respaldos', 'Telefonía / VoIP', 'Mantenimiento', 'Microsoft 365', 'Otros'])->map(fn ($name) => TicketCategory::firstOrCreate(['name' => $name], ['active' => true]));
        $service = app(TicketService::class);
        foreach (['Acme Demo', 'Caribe Demo', 'Norte Demo'] as $index => $name) {
            $company = Company::firstOrCreate(['name' => $name], ['active' => true]);
            $client = User::updateOrCreate(['email' => 'client'.($index + 1).'@example.test'], ['name' => 'Cliente '.($index + 1), 'company_id' => $company->id, 'password' => $password, 'role' => Role::Client, 'active' => true]);
            if ($company->tickets()->exists()) {
                continue;
            }
            foreach (TicketPriority::cases() as $position => $priority) {
                $ticket = $service->create($client, new CreateTicketData('Solicitud demo '.($position + 1), 'Incidencia ficticia para demostrar el ciclo de soporte.', $categories[$position]->id, $priority));
                if ($position > 0) {
                    $ticket = $service->assign($admin, $ticket, $agents[$position % 2]->id);
                }
                if ($position > 1) {
                    $ticket = $service->transition($agents[0], $ticket, TicketStatus::InProgress);
                }
                if ($position > 2) {
                    $service->transition($agents[0], $ticket, TicketStatus::Resolved, 'Solución ficticia aplicada y verificada.');
                    app(TicketObserverService::class)->sync($admin, $ticket, $agents->pluck('id')->all());
                }
            }
        }
        $this->command?->info('Usuarios demo: admin@example.test, agent1@example.test, agent2@example.test, client1@example.test (hasta client3). Contraseña de usuarios demo: '.$password);
    }
}
