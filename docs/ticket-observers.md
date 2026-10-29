# Observadores y participantes

Cada ticket puede tener múltiples observadores. En **Participantes**, el personal interno puede abrir el selector con el icono de agregar y retirar una persona con la X de su fila. La selección permite agregar o retirar varios usuarios a la vez; vaciarla retira todos los observadores explícitos. Cada usuario autorizado puede usar **Seguir / Dejar de observar** para gestionar su propia suscripción, sin modificar las de otras personas ni su función de solicitante o responsable.

El formulario de creación también permite seleccionar observadores. El personal interno puede elegir usuarios internos y clientes de la empresa seleccionada; los clientes únicamente pueden elegir clientes activos de su propia empresa. Cambiar la empresa limpia la selección. La creación del ticket y sus observadores es atómica: se registra un único evento created y se notifica después del commit, incluyendo a los observadores iniciales.

Pueden seleccionarse usuarios activos del equipo interno y clientes activos de la misma empresa del ticket. La validación ocurre también en servidor, dentro de una transacción que bloquea el ticket. El selector limita cada ticket a 200 observadores. No se admiten cuentas eliminadas, inactivas, IDs inexistentes ni clientes de otras empresas.

## Participación

El detalle muestra solicitante, responsable y observadores activos en una sección **Participantes**. Una persona aparece una sola vez, con sus funciones combinadas cuando corresponda. El filtro **Tickets que observo** permite localizar las suscripciones explícitas.

Los observadores participan en la misma conversación, con editor enriquecido y sincronización automática. Los clientes comentan públicamente y los usuarios internos conservan sus permisos de notas internas. Ser observador no cambia el rol de la cuenta, no otorga permisos de gestión y no permite comentar tickets cerrados o cancelados.

Se mantiene el acceso por empresa establecido en el proyecto: los clientes autorizados pueden consultar tickets de su propia empresa aunque no sean observadores. La lista identifica participantes y suscriptores; no concede acceso a otra empresa ni sustituye las Policies. Incluso una membresía inconsistente introducida directamente en la base no evita ese aislamiento.

## Historial e integridad

La entidad TicketObserver usa SoftDeletes, claves foráneas restrictivas y unicidad por ticket/usuario. Al retirar una persona se conserva su membresía borrada lógicamente; al añadirla de nuevo se restaura el mismo registro. La transacción deja un evento observers_changed con actor, lista anterior y lista nueva. El timeline muestra nombres y etiquetas legibles. Guardar una selección sin cambios no duplica registros ni eventos.

Las cuentas que dejan de ser elegibles no aparecen como participantes activos ni reciben notificaciones. Al gestionar de nuevo la selección se presentan solo miembros elegibles; guardar permite retirar las membresías que hayan quedado inactivas. Su historial permanece conservado.

## Notificaciones

Los cambios públicos se notifican por la cola existente al solicitante, responsable y observadores. Los destinatarios se deduplican y se excluye al autor de la actualización. Las notas internas conservan su comportamiento anterior y no generan estos correos.

La notificación se emite después del commit. Al entregar un correo encolado se vuelven a comprobar la cuenta, la empresa, el acceso al ticket y la participación vigente. Un observador retirado, desactivado o trasladado de empresa no recibe correos pendientes que hayan dejado de corresponderle. El solicitante y el responsable siguen siendo participantes aunque se retire su suscripción explícita de observador.

Los datos demo creados desde cero incluyen ejemplos de múltiples observadores. El número de contacto opcional se conserva en el ticket y se muestra en el detalle; no sustituye los datos de la cuenta del solicitante.

## Verificación

TicketObserversTest cubre selección múltiple, autorización, aislamiento, participación, comentarios, lista sin duplicados, alta/baja/restauración, filtros, actualización de la interfaz y conservación del borrador. TicketConsistencyTest comprueba la entrega tras confirmar transacciones, deduplicación, privacidad de notas, revocación de notificaciones pendientes y rollback de membresías e historial.

InteractiveParticipantsTest cubre observadores iniciales, controles inline, seguimiento propio, permisos y rollback de una creación inválida. TicketContactTest verifica formato del teléfono, persistencia y formularios de ambos paneles.
