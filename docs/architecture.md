# Arquitectura

La capa de presentación usa dos paneles separados: `/admin` y `/portal`. Ambos comparten esquemas, tablas y páginas, pero conservan sus rutas y controles de acceso. El administrador dispone de empresas, usuarios y categorías; el agente solo de soporte. No existe registro público.

`TicketService` coordina creación, asignación, prioridad, transiciones y comentarios. `AttachmentService` valida y persiste archivos privados. `ConfigurationService` centraliza la validación y autorización de los catálogos y usuarios. La UI delega en estos servicios.

Los contratos de repositorio separan el agregado Ticket y la persistencia de Company/User. Los bindings viven en `DomainServiceProvider`. Las categorías son un catálogo sencillo y no necesitan un repositorio que duplique Eloquent.

Toda modificación del ticket bloquea la fila dentro de una transacción. La asignación valida que el destinatario sea un agente activo. La numeración humana se serializa mediante un advisory lock transaccional PostgreSQL y mantiene restricciones únicas; cuenta también tickets borrados lógicamente. El identificador público es un ULID independiente.

Las Policies autorizan operaciones tanto en servicios como en interfaz. `visibleTo` es una frontera de consultas para el portal. El company_id del cliente siempre se deriva del usuario, incluso si el payload contiene otro. Los recursos buscan los tickets por public_id; los accesos cruzados por URL responden 404. La descarga valida la Policy y nunca presenta rutas físicas.

Los comentarios antiguos conservan su formato de texto plano escapado. Los nuevos comentarios del editor usan HTML limitado, saneado antes de guardar y al renderizar mediante CommentContent, con una lista explícita de etiquetas y protocolos. Las notas internas se filtran en consultas, policies y el historial público. Los eventos registran actor, tipo, valores anteriores/nuevos, metadata y fecha. Eloquent y un trigger PostgreSQL impiden editar/eliminar auditoría. SoftDeletes se aplica a Company, User, TicketCategory, Ticket, TicketComment y TicketAttachment. El historial es la excepción intencional para preservar evidencia.

`TicketActivity` se emite después del commit. Su listener envía `TicketUpdated`, una notificación en cola a creador y agente (excluyendo al actor). No se notifica una nota interna, ni usuarios desactivados. La cola usa Redis y correo SMTP local Mailpit.

Los archivos se validan por extensión y contenido MIME, limitan tamaño y reciben un nombre aleatorio. Si falla la transacción de metadatos se elimina el archivo recién escrito. El almacenamiento local falla explícitamente ante errores de escritura. Las descargas usan Content-Disposition attachment y nosniff.

La configuración Docker corresponde a un piloto local; el despliegue público requiere infraestructura adicional descrita en README.

El detalle del ticket usa un editor RichEditor de Filament en la misma pantalla. Las acciones propias refrescan inmediatamente el modelo utilizado por el infolist, conversación, adjuntos e historial. Livewire consulta cambios cada cinco segundos mientras la vista está visible y conserva el estado del borrador. Cada refresco vuelve a consultar el ticket mediante el repositorio y su autorización. No requiere infraestructura adicional de WebSockets.

TicketObserverService coordina altas y bajas de observadores sobre el repositorio del agregado, con autorización y bloqueo transaccional. Los candidatos se consultan mediante UserRepositoryInterface con alcance a personal interno o clientes de la empresa del ticket. TicketObserver conserva la membresía mediante SoftDeletes. Las notificaciones toman participantes sin duplicados y vuelven a verificar la autorización al entregarse en la cola. Ver docs/ticket-observers.md.

TicketActivityRecorder centraliza la persistencia de auditoría y emisión de TicketActivity después del commit, compartida por TicketService y TicketObserverService sin dependencia circular. Los observadores iniciales se validan dentro de la misma transacción de creación antes del evento created. PriorityGuidelineService autoriza y valida la configuración de prioridades; el formulario y el detalle reutilizan el mismo componente de presentación.
