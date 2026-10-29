# Lineamientos de prioridad

Un administrador configura las cuatro prioridades desde **Administración → Prioridades y tiempos** (`/admin/priority-guidelines`). Cada fila contiene criterios de clasificación editables, minutos para primera respuesta, minutos para solución y calendario.

Los tiempos y el calendario empiezan vacíos, por decisión del negocio. La interfaz muestra **Sin definir** hasta configurarlos; no se presentan valores sugeridos como compromisos. Los criterios iniciales ayudan a distinguir impacto bajo, parcial, alto o crítico y pueden adaptarse al piloto.

Para configurar plazos se deben completar ambos tiempos y elegir explícitamente **Horas corridas (24/7)**. Se aceptan enteros entre 1 y 525600 minutos; el tiempo de solución debe ser igual o mayor que el de respuesta. Para retirar los plazos hay que vaciar ambos tiempos y el calendario. Las prioridades son fijas: no se crean ni eliminan desde el panel.

La primera respuesta corresponde al primer comentario público del personal interno. La solución corresponde al estado Resuelto, distinto del cierre. Los lineamientos vigentes se muestran al seleccionar prioridad en la creación y en el detalle del ticket de ambos paneles.

Esta configuración presenta referencias de atención; no calcula vencimientos por ticket ni incumplimientos automáticos. Los cambios se reflejan como lineamientos actuales y no versionan compromisos históricos. El calendario disponible cuenta tiempo continuo, sin excluir fines de semana ni festivos.

PriorityGuideline utiliza SoftDeletes y restricciones PostgreSQL para impedir prioridades inválidas, configuraciones parciales o tiempos incoherentes. Solo administradores pueden editar. PriorityGuidelinesTest cubre valores iniciales vacíos, autorización, validación, limpieza de plazos y edición real mediante Filament.
