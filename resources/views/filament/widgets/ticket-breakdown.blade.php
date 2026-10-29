<x-filament-widgets::widget>
    <x-filament::section heading="Tickets recientes" description="Las últimas solicitudes del período seleccionado." icon="heroicon-o-clock">
        <x-slot name="afterHeader"><x-filament::link :href="url(filament()->getCurrentPanel()->getPath().'/tickets')" icon="heroicon-o-arrow-right" icon-position="after">Ver todos</x-filament::link></x-slot>
        <div class="desk-recent-list">
        @forelse($recent as $ticket)
            <a class="desk-recent-ticket" href="{{ url(filament()->getCurrentPanel()->getPath().'/tickets/'.$ticket->public_id) }}">
                <span class="desk-ticket-symbol"><x-filament::icon icon="heroicon-o-ticket" /></span>
                <span class="desk-recent-copy"><span class="desk-ticket-ref">{{ $ticket->ticket_number }} · {{ $ticket->category->name }}</span><strong>{{ $ticket->subject }}</strong><span class="desk-muted">{{ $ticket->created_at->timezone('America/Santo_Domingo')->diffForHumans() }}</span></span>
                <span class="desk-recent-badges"><x-filament::badge :color="$ticket->status->getColor()" :icon="$ticket->status->getIcon()">{{ $ticket->status->getLabel() }}</x-filament::badge><x-filament::badge :color="$ticket->priority->getColor()" :icon="$ticket->priority->getIcon()">{{ $ticket->priority->getLabel() }}</x-filament::badge></span>
                <x-filament::icon icon="heroicon-o-chevron-right" class="desk-row-arrow" />
            </a>
        @empty
            <x-helpdesk.empty-state icon="heroicon-o-inbox" title="Todo despejado por aquí" description="No hay tickets para los filtros seleccionados. Puedes cambiar el período o crear una solicitud." />
        @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
