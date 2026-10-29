# Estrategia de pruebas

La suite combina PHPUnit, HTTP de Laravel, Livewire/Filament y PostgreSQL 18 real. No se usa SQLite. Tests/TestCase valida la conexión y el nombre de base antes de habilitar migraciones. La configuración phpunit.xml fuerza helpdesk_testing.

TicketWorkflowTest usa RefreshDatabase para aislar casos transaccionales. Verifica números, empresa derivada, ciclo completo, reasignación, prioridad, filtros, permisos, contraseñas, estados inválidos, justificaciones, notas internas, primera respuesta, SoftDeletes, adjuntos privados y auditoría inmutable mediante Eloquent y SQL. También comprueba login, paneles, creación/acciones reales de Filament y URLs con ULID de otra empresa o ID secuencial manipulado.

TicketConsistencyTest usa DatabaseMigrations para probar commits reales. Crea seis procesos PHP simultáneos contra la base exclusiva de pruebas y comprueba números distintos y consecutivos. Verifica notificaciones después del commit, ausencia de notificaciones de notas internas y rollback de ticket e historial. No debe ejecutarse simultáneamente otra suite sobre la misma base.

Las pruebas usan factories y no requieren seeders. Storage::fake aísla los archivos. Notification::fake permite comprobar destinatarios sin correo externo. Las pruebas de concurrencia usan procesos reales con APP_ENV=testing y una segunda comprobación del nombre de la base en el proceso hijo.

La suite no sustituye una evaluación de experiencia de usuario en un navegador ni una auditoría de infraestructura de producción. La prueba piloto debe recoger evidencia del uso durante 15 días según la especificación académica.

Comandos reproducibles en README. Pint comprueba formato y Larastan nivel 5 analiza toda la carpeta app sin baseline ni reglas ignoradas.

AnalyticsAndReportsTest comprueba las agregaciones SQL, el aislamiento en gráficos, los límites diarios en hora local, las duraciones reales, filtros y permisos de reportes, descarga CSV y protección contra fórmulas. También verifica filtros Livewire aplicados y borradores inválidos, fechas manipuladas, timeline legible, escape de descripciones y acciones ocultas para tickets terminales.

TicketConversationTest cubre HTML enriquecido saneado, enlaces peligrosos, contenido vacío, compatibilidad con texto plano histórico, envío desde el editor, refresco de badges y fechas, conservación del borrador, notas internas, cambios de empresa y cierre concurrente. La verificación con Chromium utiliza sesiones separadas de soporte y cliente para comprobar que un comentario y los cambios de prioridad/estado se reflejan sin recarga y sin perder el borrador.

TicketObserversTest verifica participantes, selección múltiple, comentarios, permisos, aislamiento, altas/bajas/restauración, filtros y actualización en Filament. Las pruebas con commits reales verifican notificaciones deduplicadas y la revocación de correos pendientes cuando cambian membresías o empresas.

InteractiveParticipantsTest verifica creación atómica con observadores, alcance por empresa, controles de baja inline, seguimiento propio idempotente y conservación del borrador. TicketContactTest comprueba validación, persistencia, privacidad y creación en ambos paneles. PriorityGuidelinesTest cubre configuración sin valores iniciales, plazos completos y coherentes, permisos, edición Filament y ayuda dinámica por prioridad. TicketConsistencyTest verifica una única notificación de creación a observadores después del commit.
