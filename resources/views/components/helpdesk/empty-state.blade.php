@props(['icon'=>'heroicon-o-inbox','title','description'])
<div class="desk-empty" role="status"><span class="desk-empty-icon"><x-filament::icon :icon="$icon" /></span><h3>{{ $title }}</h3><p>{{ $description }}</p></div>
