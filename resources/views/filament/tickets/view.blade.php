<x-filament-panels::page>
    <div class="desk-ticket-workspace" wire:poll.5s.visible="refreshTicket">
        <div class="desk-sync-status" role="status">
            <span wire:offline.remove><span class="desk-live-dot" aria-hidden="true"></span> Actualización automática · cada 5 segundos</span>
            <span wire:offline>Sin conexión. La sincronización se reanudará al reconectar.</span>
        </div>

        <div class="desk-ticket-layout">
            <div class="desk-ticket-main">
                <x-filament::section heading="Detalle de la solicitud" icon="heroicon-o-ticket">
                    {{ $this->infolist }}
                    <x-helpdesk.priority-guideline :guide="$this->priorityGuideline()" />
                </x-filament::section>
                <x-filament::section heading="Participantes" description="Solicitante, responsable y observadores activos." icon="heroicon-o-user-group">
                    <x-slot name="afterHeader">
                        <div class="desk-participant-controls">
                            {{ $this->observersAction }}
                            @php($following = $this->isObserving())
                            <x-filament::button size="sm" color="gray" :icon="$following ? 'heroicon-o-eye-slash' : 'heroicon-o-eye'"
                                wire:click="setObserverSubscription({{ $following ? 'false' : 'true' }})" wire:loading.attr="disabled" wire:target="setObserverSubscription">
                                {{ $following ? 'Dejar de observar' : 'Seguir' }}
                            </x-filament::button>
                        </div>
                    </x-slot>
                    <ul class="desk-participants" aria-label="Participantes del ticket">
                    @forelse($this->participants() as $participant)
                        <li wire:key="participant-{{ $participant['user']->id }}" class="desk-participant">
                            <span class="desk-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($participant['user']->name, 0, 1)) }}</span>
                            <div><strong>{{ $participant['user']->name }}</strong><span class="desk-muted">{{ implode(' · ', $participant['roles']) }}</span></div>
                            @if(in_array('Observador', $participant['roles'], true))
                                @if($participant['user']->id === auth()->id() || \Illuminate\Support\Facades\Gate::allows('manageObservers', $this->record))
                                    <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" size="sm"
                                        :label="'Quitar a '.$participant['user']->name.' como observador'"
                                        wire:click="removeObserver({{ $participant['user']->id }})" wire:loading.attr="disabled" wire:target="removeObserver" />
                                @else
                                    <x-filament::icon icon="heroicon-o-eye" class="desk-row-arrow" />
                                @endif
                            @endif
                        </li>
                    @empty
                        <li class="desk-muted">No hay participantes activos.</li>
                    @endforelse
                    </ul>
                </x-filament::section>
                <div class="desk-ticket-files">
                    <x-filament::section heading="Archivos adjuntos" icon="heroicon-o-paper-clip">
                        <div class="desk-attachments">
                        @forelse ($this->record->attachments as $attachment)
                            <a wire:key="attachment-{{ $attachment->public_id }}" class="desk-attachment" href="{{ route('attachments.download', $attachment) }}">
                                <span class="desk-ticket-symbol"><x-filament::icon icon="heroicon-o-document-text" /></span>
                                <span><strong>{{ $attachment->original_name }}</strong><small>{{ number_format($attachment->size / 1024, 1) }} KB · Descargar archivo</small></span>
                                <x-filament::icon icon="heroicon-o-arrow-down-tray" class="desk-row-arrow" />
                            </a>
                        @empty
                            <x-helpdesk.empty-state icon="heroicon-o-document-plus" title="Sin archivos adjuntos" description="Adjunta documentos o capturas para aportar más contexto." />
                        @endforelse
                        </div>
                    </x-filament::section>
                </div>
            </div>

            <div class="desk-ticket-conversation">
                <x-filament::section heading="Conversación" description="Responde y comparte avances sin salir del ticket." icon="heroicon-o-chat-bubble-left-right">
                    <div class="desk-conversation-scroll" role="region" aria-label="Mensajes de la conversación" tabindex="0"
                        x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                        x-on:ticket-comment-posted.window="$nextTick(() => $el.scrollTo({ top: $el.scrollHeight, behavior: 'instant' }))">
                        <div class="desk-conversation" aria-live="polite" aria-relevant="additions">
                        @forelse ($this->visibleComments() as $comment)
                            <article wire:key="comment-{{ $comment->id }}" class="desk-comment {{ $comment->is_internal ? 'desk-comment-internal' : '' }}">
                                <span class="desk-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($comment->user->name,0,1)) }}</span>
                                <div class="desk-comment-content">
                                    <header><strong>{{ $comment->user->name }}</strong><time datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->timezone('America/Santo_Domingo')->format('d/m/Y · H:i') }}</time></header>
                                    @if($comment->is_internal)<x-filament::badge color="warning" icon="heroicon-o-lock-closed">Nota interna</x-filament::badge>@endif
                                    <div class="desk-rich-content">{{ $comment->renderedBody() }}</div>
                                </div>
                            </article>
                        @empty
                            <x-helpdesk.empty-state icon="heroicon-o-chat-bubble-oval-left-ellipsis" title="Aquí comienza la conversación" description="Escribe tu primera respuesta en el editor de abajo." />
                        @endforelse
                        </div>
                    </div>

                    @if(\Illuminate\Support\Facades\Gate::allows('comment', $this->record))
                        <form wire:submit="submitComment" class="desk-comment-composer">
                            {{ $this->composerForm }}
                            <div class="desk-composer-footer">
                                <span class="desk-muted">Tu borrador se conserva durante las actualizaciones.</span>
                                <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled" wire:target="submitComment">
                                    Enviar comentario
                                </x-filament::button>
                            </div>
                        </form>
                    @else
                        <p class="desk-conversation-closed"><x-filament::icon icon="heroicon-o-lock-closed" /> Este ticket está finalizado y no admite nuevos comentarios.</p>
                    @endif
                </x-filament::section>
            </div>
        </div>

        <x-filament::section heading="Historial de actividad" description="Los cambios más recientes aparecen primero." icon="heroicon-o-clock">
            <div class="desk-timeline-scroll" role="region" aria-label="Historial de actividad del ticket" tabindex="0">
                <ol class="desk-timeline" aria-label="Historial de cambios del ticket">
                @foreach($this->timeline() as $item)
                    @php($event=$item['event'])
                    <li wire:key="ticket-event-{{ $event->id }}" class="desk-timeline-item">
                        <span class="desk-timeline-node desk-tone-{{ $item['color'] }}"><x-filament::icon :icon="$item['icon']" /></span>
                        <div class="desk-timeline-body">
                            <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->timezone('America/Santo_Domingo')->format('d M Y · H:i') }}</time>
                            <h3>{{ __('tickets.events.'.$event->event_type) }}</h3>
                            <p class="desk-muted">{{ $event->actor?->name ?? 'Sistema' }}</p>
                            <div class="desk-timeline-changes">
                            @foreach($item['changes'] as $change)
                                <div class="desk-change"><span>{{ $change['label'] }}</span><div>@if($change['old']!==null)<span class="desk-change-old">{{ $change['old'] }}</span><x-filament::icon icon="heroicon-o-arrow-right" />@endif<strong>{{ $change['new'] }}</strong></div></div>
                            @endforeach
                            </div>
                            @if(data_get($event->metadata,'reason'))<blockquote>{{ $event->metadata['reason'] }}</blockquote>@endif
                            @if(data_get($event->metadata,'internal'))<x-filament::badge color="warning" icon="heroicon-o-lock-closed">Nota interna</x-filament::badge>@endif
                        </div>
                    </li>
                @endforeach
                </ol>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
