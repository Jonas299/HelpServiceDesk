<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priority_guidelines', function (Blueprint $table): void {
            $table->id();
            $table->string('priority')->unique();
            $table->text('criteria');
            $table->unsignedInteger('response_minutes')->nullable();
            $table->unsignedInteger('resolution_minutes')->nullable();
            $table->string('calendar')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement("ALTER TABLE priority_guidelines ADD CONSTRAINT priority_guidelines_values_check CHECK (priority IN ('low','medium','high','critical') AND ((response_minutes IS NULL AND resolution_minutes IS NULL AND calendar IS NULL) OR (response_minutes IS NOT NULL AND response_minutes > 0 AND resolution_minutes IS NOT NULL AND resolution_minutes >= response_minutes AND calendar IS NOT NULL AND calendar = 'continuous')))");
        foreach ([
            'low' => 'Consultas, ajustes o incidencias menores que no interrumpen la operación y permiten continuar trabajando.',
            'medium' => 'Incidencias que afectan parcialmente el trabajo y cuentan con una alternativa temporal para continuar.',
            'high' => 'Un servicio importante está degradado o bloqueado, afecta a varios usuarios y la alternativa disponible es limitada.',
            'critical' => 'Interrupción general de un servicio esencial o incidente de seguridad activo, con impacto grave y sin alternativa operativa.',
        ] as $priority => $criteria) {
            DB::table('priority_guidelines')->insert(['priority' => $priority, 'criteria' => $criteria, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('priority_guidelines');
    }
};
