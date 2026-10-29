<?php

namespace App\Filament\Admin\Resources\PriorityGuidelines\Pages;

use App\Filament\Admin\Resources\PriorityGuidelines\PriorityGuidelineResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePriorityGuidelines extends ManageRecords
{
    protected static string $resource = PriorityGuidelineResource::class;

    public function getSubheading(): ?string
    {
        return 'Clasifica por impacto y define los objetivos de atención. Los tiempos comienzan sin valores; solo se publican al configurarlos completos.';
    }
}
