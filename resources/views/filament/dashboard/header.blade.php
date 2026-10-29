<header class="desk-welcome">
    <div class="desk-welcome-copy">
        <span class="desk-eyebrow"><span class="desk-live-dot"></span> CENTRO DE SOPORTE</span>
        <h1>Todo bajo control<span class="desk-accent">.</span></h1>
        <p>Hola, {{ str(auth()->user()->name)->before(' ') }}. Así van {{ auth()->user()->isInternal() ? 'las solicitudes de tu equipo' : 'las solicitudes de tu empresa' }}.</p>
        <div class="desk-welcome-actions">
            @foreach($this->getCachedHeaderActions() as $action) {{ $action }} @endforeach
            @if(auth()->user()->isInternal())
                <x-filament::button tag="a" href="{{ url('/admin/reports') }}" color="gray" icon="heroicon-o-chart-bar-square">Ver reportes</x-filament::button>
            @endif
        </div>
    </div>
    <div class="desk-welcome-art" aria-hidden="true">
        <div class="desk-art-orbit"></div>
        <div class="desk-art-ticket"><x-filament::icon icon="heroicon-o-ticket" /></div>
        <span class="desk-art-check"><x-filament::icon icon="heroicon-o-check" /></span>
        <span class="desk-art-spark"><x-filament::icon icon="heroicon-o-sparkles" /></span>
    </div>
</header>
