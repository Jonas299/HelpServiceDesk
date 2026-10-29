@props(['guide'])
<div class="desk-priority-guide" aria-label="Lineamientos de prioridad">
    <header><x-filament::icon :icon="$guide['priority']->getIcon()" /><strong>Prioridad {{ mb_strtolower($guide['priority']->getLabel()) }}</strong></header>
    <p>{{ $guide['criteria'] }}</p>
    <dl><div><dt>Primera respuesta</dt><dd>{{ $guide['response'] }}</dd></div><div><dt>Solución</dt><dd>{{ $guide['resolution'] }}</dd></div></dl>
    <small>{{ $guide['configured'] ? 'Cómputo: '.$guide['calendar'].'. Primera respuesta pública del equipo; solución al marcar Resuelto.' : 'Los tiempos de atención están pendientes de configuración.' }}</small>
</div>
