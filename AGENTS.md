# Instrucciones del proyecto

La especificación y las reglas del repositorio están en [.agent/AGENTS.md](.agent/AGENTS.md). Leerlas antes de modificar el sistema.

El usuario solicita SoftDeletes para los modelos de negocio y reutilización de código. TicketEvent es un registro append-only protegido en Eloquent y PostgreSQL: no se edita ni elimina mediante la aplicación.
