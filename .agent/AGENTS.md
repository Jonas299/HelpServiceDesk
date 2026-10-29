# AGENTS.md — Help Service Desk

## 1. Propósito

Este repositorio implementa el sistema del Trabajo Final de Grado:

**“IMPLEMENTACIÓN DE UN SISTEMA PARA LA GESTIÓN, ASIGNACIÓN Y SEGUIMIENTO DE TICKETS DE SOPORTE E INCIDENCIAS DE CLIENTES EMPRESARIALES”**

El sistema será una aplicación web para centralizar el ciclo de vida de las solicitudes de soporte de: creación, clasificación, priorización, asignación, seguimiento, comentarios, resolución, cierre e historial.

El agente debe construir software mantenible, seguro, probado y documentado. No debe agregar funcionalidades ajenas al alcance sin una necesidad real documentada.

---

## 2. Stack obligatorio

Utilizar las versiones estables compatibles más recientes dentro de estas líneas:

- PHP >= 8.3
- Laravel 13.x
- Filament 5.x
- PostgreSQL 18.x
- Docker + Docker Compose
- Redis para cache, colas y tareas asíncronas cuando aplique
- Git para control de versiones
- Composer para dependencias PHP
- Vite / Node para assets requeridos por Laravel y Filament
- PHPUnit como framework principal de pruebas
- Laravel Pint para formato de código
- Larastan/PHPStan para análisis estático cuando sea viable

No degradar Laravel, Filament ni PostgreSQL a versiones antiguas sin una razón técnica explícita.

No utilizar APIs obsoletas de Filament 2, 3 o 4 si Filament 5 ofrece la alternativa actual.

---

## 3. Principios de arquitectura

Aplicar los principios siguientes:

1. Separación de responsabilidades.
2. Dependency Injection.
3. Repository Pattern para acceso a persistencia del dominio.
4. Service/Application Layer para los casos de uso.
5. Laravel Policies para autorización.
6. Eloquent Models enfocados en relaciones, casts, scopes y comportamiento propio del modelo.
7. Filament Resources enfocados en interfaz, formularios, tablas, filtros y acciones de presentación.
8. Reglas de negocio fuera de Filament Resources y controladores.
9. Transacciones de base de datos en operaciones que modifican varios registros relacionados.
10. Eventos de dominio/aplicación para efectos secundarios importantes.
11. Colas para notificaciones y procesos que no necesitan bloquear la respuesta.
12. Código simple antes que abstracciones innecesarias.

El Repository Pattern es obligatorio para los agregados principales, pero no se deben crear repositorios vacíos que únicamente repliquen cada método de Eloquent sin aportar una frontera clara.

---

## 4. Organización recomendada

Usar una estructura similar a:

```text
app/
├── Domain/
│   ├── Companies/
│   │   ├── Contracts/
│   │   ├── DTOs/
│   │   ├── Enums/
│   │   ├── Repositories/
│   │   └── Services/
│   ├── Tickets/
│   │   ├── Contracts/
│   │   ├── DTOs/
│   │   ├── Enums/
│   │   ├── Events/
│   │   ├── Repositories/
│   │   └── Services/
│   └── Users/
│       ├── Contracts/
│       ├── DTOs/
│       ├── Enums/
│       ├── Repositories/
│       └── Services/
├── Filament/
│   ├── Admin/
│   │   ├── Resources/
│   │   ├── Pages/
│   │   └── Widgets/
│   └── Client/
│       ├── Resources/
│       ├── Pages/
│       └── Widgets/
├── Models/
├── Notifications/
├── Policies/
└── Providers/
```

Si la estructura actual del repositorio ya sigue una arquitectura coherente, adaptarse a ella sin realizar refactors masivos innecesarios.

---

## 5. Repository Pattern

Los contratos deben vivir separados de las implementaciones.

Ejemplo conceptual:

```text
App\Domain\Tickets\Contracts\TicketRepositoryInterface
App\Domain\Tickets\Repositories\EloquentTicketRepository
```

Los bindings entre interfaces e implementaciones deben configurarse mediante el Service Container de Laravel, preferiblemente en un Service Provider dedicado.

Los repositorios deben cubrir consultas y persistencia de dominio, por ejemplo:

- crear un ticket;
- buscar ticket por ID público;
- obtener tickets visibles para una empresa;
- obtener tickets asignados a un agente;
- filtrar por estado, prioridad, empresa, agente y fechas;
- persistir cambios asociados con un caso de uso.

No colocar reglas de negocio complejas dentro de los repositorios.

Los servicios de aplicación deben coordinar operaciones tales como:

- creación de ticket;
- asignación o reasignación;
- cambio de estado;
- resolución;
- cierre;
- comentarios;
- adjuntos;
- generación de eventos de auditoría;
- notificaciones.

---

## 6. Alcance funcional

### 6.1 Usuarios y roles

Roles mínimos:

- `admin`
- `agent`
- `client`

Reglas:

- `admin`: administra configuración, empresas, usuarios, categorías y todos los tickets.
- `agent`: consulta tickets internos, recibe asignaciones, actualiza estados, comenta, resuelve y da seguimiento según permisos.
- `client`: pertenece a una empresa y solo puede acceder a información autorizada de su propia empresa.

No implementar registro público de usuarios.

Los usuarios son creados o invitados por personal autorizado.

Un usuario `client` debe estar asociado a una empresa.

Los usuarios internos pueden no tener `company_id`.

Usar un Enum PHP para los roles si no existe una necesidad real de administrarlos dinámicamente.

---

### 6.2 Empresas clientes

Debe existir una entidad `Company`.

Campos mínimos sugeridos:

- id
- name
- legal_name nullable
- tax_id/RNC nullable
- email nullable
- phone nullable
- address nullable
- active
- timestamps

Una empresa puede tener múltiples usuarios y múltiples tickets.

Los usuarios de una empresa nunca deben visualizar datos de otra empresa.

---

### 6.3 Tickets

El ticket es el agregado principal.

Campos mínimos sugeridos:

- id
- public_id o ULID único para exposición externa
- ticket_number legible y único
- company_id
- created_by
- assigned_to nullable
- category_id
- subject
- description
- priority
- status
- source
- first_responded_at nullable
- resolved_at nullable
- closed_at nullable
- timestamps

No exponer IDs secuenciales internos como identificador público si puede evitarse.

Formato sugerido para el número humano:

```text
TK-2026-000001
```

La generación debe ser segura frente a concurrencia y tener restricción `UNIQUE` en PostgreSQL.

---

### 6.4 Estados del ticket

Usar un Enum PHP para el flujo base:

- `new`
- `assigned`
- `in_progress`
- `waiting_customer`
- `resolved`
- `closed`
- `cancelled`

La interfaz debe mostrar etiquetas en español:

- Nuevo
- Asignado
- En proceso
- Esperando cliente
- Resuelto
- Cerrado
- Cancelado

Los estados cancelled y resolved deben justiticarse

Las transiciones deben validarse en la capa de aplicación; no permitir cambios arbitrarios si rompen el flujo.

Ejemplo razonable:

```text
new -> assigned
new -> in_progress
assigned -> in_progress
in_progress -> waiting_customer
waiting_customer -> in_progress
in_progress -> resolved
resolved -> closed
resolved -> in_progress   # reapertura justificada
```

No borrar tickets para representar cancelaciones o cierres.

---

### 6.5 Prioridades

Usar un Enum:

- `low`
- `medium`
- `high`
- `critical`

Etiquetas:

- Baja
- Media
- Alta
- Crítica

La prioridad inicial puede ser seleccionada por el cliente si el negocio lo permite, pero el personal interno debe poder ajustarla.

Nunca confiar únicamente en la prioridad declarada por el cliente para decisiones de autorización o seguridad.

---

### 6.6 Categorías

Las categorías deben almacenarse en base de datos porque pueden cambiar con los servicios.

Ejemplos iniciales:

- Soporte técnico
- Redes
- Servidores
- Seguridad informática
- Respaldos
- Telefonía / VoIP
- Mantenimiento
- Microsoft 365
- Otros

No codificar estas categorías como Enum si deben ser administrables desde Filament.

---

### 6.7 Comentarios y seguimiento

Crear entidad `TicketComment`.

Debe distinguir entre:

- comentario visible para el cliente;
- nota interna visible únicamente para personal del sistema.

Campos sugeridos:

- id
- ticket_id
- user_id
- body
- is_internal
- timestamps

Un cliente nunca puede crear o visualizar notas internas.

Evitar HTML arbitrario. Preferir texto plano o Markdown controlado.

---

### 6.8 Historial y auditoría

Crear un historial append-only mediante `TicketEvent` o equivalente.

Registrar al menos:

- ticket creado;
- asignado;
- reasignado;
- prioridad modificada;
- estado modificado;
- comentario agregado;
- archivo adjunto;
- resolución;
- reapertura;
- cierre.

Campos sugeridos:

- id
- ticket_id
- actor_id nullable
- event_type
- old_values JSONB nullable
- new_values JSONB nullable
- metadata JSONB nullable
- created_at

No permitir edición o eliminación normal de eventos de auditoría desde Filament.

Los eventos deben permitir reconstruir qué ocurrió, quién lo hizo y cuándo.

---

### 6.9 Archivos adjuntos

Los tickets y comentarios pueden admitir adjuntos si se implementa esta funcionalidad.

Reglas:

- almacenar en disco privado;
- nunca confiar en el nombre original;
- generar nombres internos seguros;
- validar tamaño;
- validar MIME real;
- permitir únicamente extensiones necesarias;
- autorización obligatoria para descarga;
- un cliente solo puede descargar archivos de tickets de su empresa;
- no exponer rutas físicas.

No almacenar binarios grandes directamente en PostgreSQL.

---

### 6.10 Dashboard y reportes

Panel interno:

- tickets abiertos;
- nuevos;
- en proceso;
- esperando cliente;
- críticos;
- resueltos/cerrados;
- tickets por agente;
- tickets por empresa;
- tickets por prioridad;
- tickets por categoría;
- tendencia por período.

Filtros importantes:

- empresa;
- estado;
- prioridad;
- categoría;
- agente;
- rango de fechas.

Panel cliente:

- tickets abiertos de su empresa;
- tickets recientes;
- tickets por estado;
- acceso rápido para crear ticket.

No agregar analítica compleja que no esté respaldada por datos reales.

---

## 7. Filament

Usar Filament 5 con sus APIs actuales.

### 7.1 Paneles

Preferir dos paneles separados:

```text
/admin   -> personal interno
/portal  -> clientes
```

El panel interno contiene administración y soporte.

El portal de clientes debe ser más limitado y enfocado en:

- crear tickets;
- visualizar tickets de su empresa;
- consultar historial visible;
- comentar;
- adjuntar archivos permitidos;
- consultar estado.

No reutilizar el panel administrativo para clientes si ello aumenta el riesgo de exposición de datos.

### 7.2 Recursos

Los Resources deben permanecer delgados.

Correcto:

- esquema de formularios;
- definición de columnas;
- filtros;
- badges;
- navegación;
- acciones que delegan en Services/Actions;
- query scoping de presentación.

Incorrecto:

- reglas de negocio extensas;
- transacciones complejas;
- acceso directo no controlado a registros de otras empresas;
- lógica de asignación embebida en callbacks grandes;
- lógica duplicada entre páginas.

### 7.3 Autorización

Usar Laravel Policies para los modelos relevantes.

Como mínimo:

- CompanyPolicy
- UserPolicy
- TicketPolicy
- TicketCommentPolicy
- TicketAttachmentPolicy
- CategoryPolicy

No confiar únicamente en:

```php
->visible(...)
->hidden(...)
```

Ocultar una acción en la interfaz no es una frontera de seguridad.

Toda operación sensible debe ser autorizada en servidor.

### 7.4 Aislamiento de clientes

Un usuario cliente solo puede:

- ver tickets de su empresa;
- crear tickets para su propia empresa;
- comentar tickets de su empresa;
- acceder a adjuntos autorizados de su empresa.

Nunca aceptar `company_id` enviado por un cliente como fuente de verdad.

El `company_id` de un ticket creado desde el portal debe derivarse del usuario autenticado.

Agregar tests explícitos de fuga entre empresas.

---

## 8. Base de datos PostgreSQL

Usar PostgreSQL 18.

Configurar el desarrollo y las pruebas sobre PostgreSQL, no SQLite, para evitar diferencias de comportamiento.

Aprovechar PostgreSQL cuando aporte valor:

- `jsonb` para metadata de eventos;
- índices adecuados;
- constraints;
- transacciones;
- búsquedas case-insensitive cuando sea necesario;
- timestamps consistentes.

### 8.1 Integridad

Toda relación importante debe utilizar claves foráneas.

Definir acciones `ON DELETE` conscientemente.

No usar cascadas destructivas sobre tickets o auditoría sin justificación.

Preferir preservar el historial.

### 8.2 Índices

Crear índices basados en consultas reales.

Mínimos recomendados:

```text
tickets(company_id)
tickets(status)
tickets(priority)
tickets(assigned_to)
tickets(created_at)
tickets(company_id, status)
tickets(assigned_to, status)
tickets(category_id, status)
ticket_comments(ticket_id, created_at)
ticket_events(ticket_id, created_at)
```

No crear índices redundantes sin medir su utilidad.

### 8.3 Eliminación

Evitar hard delete de tickets.

Para tickets usar estados como `closed` o `cancelled`.

Si se necesita archivo posterior, agregar `archived_at`.

La auditoría no debe borrarse desde la aplicación normal.

---

## 9. Transacciones y consistencia

Usar `DB::transaction()` para operaciones como:

- crear ticket + crear evento inicial;
- asignar ticket + registrar evento;
- cambiar estado + registrar evento;
- resolver + registrar resolución + evento;
- cerrar + evento;
- agregar comentario + evento cuando corresponda.

Los side effects externos, como emails, deben dispararse después de confirmar la transacción cuando sea posible.

Evitar estados parcialmente persistidos.

---

## 10. Eventos y notificaciones

Eventos sugeridos:

```text
TicketCreated
TicketAssigned
TicketPriorityChanged
TicketStatusChanged
TicketCommented
TicketResolved
TicketReopened
TicketClosed
```

Notificaciones posibles:

- ticket creado;
- ticket asignado a un agente;
- cambio significativo de estado;
- nuevo comentario visible;
- ticket resuelto;
- ticket cerrado.

Las notificaciones por correo deben ejecutarse en cola.

WhatsApp no forma parte del MVP a menos que se apruebe explícitamente.

---

## 11. Seguridad

Seguridad es obligatoria, no opcional.

Aplicar:

- autenticación de Laravel/Filament;
- autorización mediante Policies;
- protección CSRF;
- validación server-side;
- rate limiting donde sea razonable;
- contraseñas gestionadas con los mecanismos nativos de Laravel;
- sesiones seguras;
- archivos privados;
- consultas con aislamiento por empresa;
- logs sin secretos;
- variables sensibles únicamente en `.env`;
- `.env` jamás versionado;
- `.env.example` actualizado y sin secretos reales.

No construir SQL manual con datos del usuario.

Usar Query Builder/Eloquent y bindings.

No usar `env()` directamente fuera de archivos de configuración.

No usar `dd()`, `dump()` o credenciales hard-coded en código enviado al repositorio.

---

## 12. Fechas y zona horaria

Persistir timestamps en UTC.

Presentar fechas al usuario en:

```text
America/Santo_Domingo
```

La interfaz debe estar principalmente en español.

Los nombres de clases, métodos, variables, tablas y columnas deben escribirse en inglés para mantener consistencia técnica.

Ejemplo:

```php
TicketStatus::IN_PROGRESS
```

UI:

```text
En proceso
```

---

## 13. Calidad de código

Seguir:

- PSR-12;
- convenciones oficiales de Laravel;
- convenciones de Filament 5;
- nombres explícitos;
- métodos pequeños;
- responsabilidades únicas;
- type hints;
- return types;
- Enums para conjuntos cerrados;
- DTOs cuando reduzcan acoplamiento;
- evitar arrays ambiguos en límites de capa cuando un DTO sea más claro.

Antes de considerar una tarea terminada ejecutar:

```bash
vendor/bin/pint
php artisan test
```

Si Larastan está instalado:

```bash
vendor/bin/phpstan analyse
```

No desactivar reglas o tests únicamente para hacer pasar CI.

---

## 14. Testing

El sistema debe desarrollarse con pruebas automatizadas.

### 14.1 Pruebas críticas

Crear tests para:

- autenticación;
- acceso a panel interno;
- acceso a portal cliente;
- permisos por rol;
- aislamiento entre empresas;
- creación de ticket;
- generación de número único;
- asignación;
- reasignación;
- cambios de estado;
- transiciones inválidas;
- comentarios internos;
- comentarios visibles;
- resolución;
- cierre;
- reapertura;
- adjuntos;
- historial;
- filtros importantes;
- Policies.

### 14.2 Aislamiento multiempresa

Este test es obligatorio:

> Un usuario de la Empresa A no puede visualizar, editar, comentar, descargar adjuntos ni inferir datos de tickets pertenecientes a la Empresa B.

Probar acceso tanto por interfaz como por URLs/IDs manipulados.

### 14.3 Base de datos de pruebas

Usar una base PostgreSQL de testing separada.

No ejecutar tests destructivos contra la base de desarrollo.

Usar factories y seeders.

No depender de datos creados manualmente para que los tests pasen.

---

## 15. Factories y seeders

Crear factories para:

- Company
- User
- Ticket
- TicketCategory
- TicketComment
- TicketEvent

Crear seeders para ambiente local con datos ficticios.

Datos iniciales sugeridos:

- 1 administrador;
- 2 agentes;
- varias empresas ficticias;
- usuarios clientes;
- categorías;
- tickets en distintos estados y prioridades.

Nunca incluir información real sensible de clientes en seeders públicos.

---

## 16. Docker

El proyecto debe ejecutarse completamente con Docker.

Mantener un `compose.yaml` claro.

Servicios recomendados:

```text
app
web/nginx
postgres
redis
queue
scheduler
mailpit   # desarrollo
```

Puede utilizarse Laravel Sail si el repositorio ya lo adopta, siempre que PostgreSQL quede correctamente configurado.

Requisitos:

- PostgreSQL con volumen persistente;
- healthchecks;
- red interna;
- variables mediante `.env`;
- sin contraseñas reales hard-coded;
- imagen reproducible;
- comandos documentados;
- separación razonable entre desarrollo y producción.

Comandos comunes deben quedar documentados en README.

Ejemplo conceptual:

```bash
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint
```

No asumir que PHP, Composer o PostgreSQL están instalados en la máquina host.

---

## 17. Git

Usar Git desde el inicio.

### 17.1 Ramas

Recomendación:

```text
main
develop
feat/<descripcion>
fix/<descripcion>
refactor/<descripcion>
docs/<descripcion>
test/<descripcion>
```

`main` debe representar una versión estable.

No desarrollar funcionalidades grandes directamente sobre `main`.

### 17.2 Commits

Usar Conventional Commits:

```text
feat: add ticket assignment workflow
fix: prevent clients from viewing other company tickets
refactor: extract ticket status transition service
test: cover tenant isolation
docs: document docker setup
chore: configure pint and phpstan
```

Los commits deben ser pequeños, coherentes y reversibles.

No hacer commits con mensajes como:

```text
changes
fix stuff
update
final
```

### 17.3 Archivos excluidos

No versionar:

- `.env`
- secretos
- credenciales
- `vendor/`
- `node_modules/`
- dumps de producción
- archivos de clientes reales
- logs innecesarios
- artefactos temporales

Mantener `.gitignore` correctamente.

---

## 18. CI

Si el repositorio está alojado en GitHub, crear o mantener GitHub Actions.

Pipeline mínimo:

1. instalar dependencias;
2. levantar PostgreSQL de testing;
3. ejecutar migraciones;
4. ejecutar Pint en modo check;
5. ejecutar análisis estático;
6. ejecutar tests;
7. fallar si cualquier etapa falla.

No desplegar automáticamente a producción desde un PR.

---

## 19. Documentación

Mantener como mínimo:

```text
README.md
AGENTS.md
.env.example
docs/
```

README debe incluir:

- propósito;
- requisitos;
- instalación con Docker;
- configuración;
- migraciones;
- seeders;
- ejecución;
- tests;
- credenciales ficticias de desarrollo si corresponde;
- estructura de paneles.

Crear documentación adicional cuando aporte valor:

```text
docs/architecture.md
docs/database.md
docs/ticket-workflow.md
docs/testing.md
```

Mantener un diagrama ER en Mermaid o formato textual versionable.

---

## 20. Reglas de dominio

### 20.1 Creación

Al crear un ticket:

1. validar usuario;
2. resolver empresa de forma segura;
3. validar categoría;
4. validar prioridad;
5. generar identificadores;
6. persistir ticket;
7. crear evento `created`;
8. emitir evento de aplicación;
9. enviar notificación si corresponde.

Todo lo crítico debe ser transaccional.

### 20.2 Asignación

Solo personal autorizado puede asignar/reasignar.

Registrar:

- responsable anterior;
- nuevo responsable;
- actor;
- fecha;
- evento.

### 20.3 Seguimiento

Cada modificación importante debe dejar evidencia.

No sobrescribir datos históricos necesarios para auditoría sin dejar registro.

### 20.4 Resolución y cierre

`resolved` y `closed` no son equivalentes.

- `resolved`: se aplicó una solución.
- `closed`: el ciclo del ticket se considera finalizado.

Guardar `resolved_at` y `closed_at`.

Permitir reapertura controlada de un ticket resuelto cuando el problema continúe.

---

## 21. Fuera de alcance inicial

No implementar por defecto:

- facturación fiscal;
- contabilidad;
- nómina;
- inventario completo;
- CRM completo;
- ventas;
- compras;
- integración WhatsApp;
- aplicación móvil nativa;
- inteligencia artificial;
- SLA complejo;
- contratos;
- portal público;
- pagos.

Estas funcionalidades requieren aprobación explícita antes de incorporarse.

El objetivo es un sistema de tickets sólido, no un ERP.

---

## 22. Criterios de aceptación del MVP

El MVP se considera funcional cuando:

1. un administrador puede iniciar sesión;
2. puede crear empresas y usuarios;
3. puede crear/configurar categorías;
4. un cliente puede acceder al portal;
5. un cliente puede crear un ticket para su empresa;
6. el ticket recibe número único;
7. el personal interno puede visualizarlo;
8. puede asignarse a un agente;
9. puede definirse prioridad;
10. puede cambiar de estado;
11. cliente y agente pueden añadir comentarios según permisos;
12. las notas internas permanecen privadas;
13. se conserva historial de acciones;
14. el agente puede resolver;
15. el ticket puede cerrarse;
16. un cliente nunca puede acceder a tickets de otra empresa;
17. existen dashboard y filtros básicos;
18. las pruebas automatizadas críticas pasan;
19. el proyecto puede levantarse desde cero con Docker;
20. README documenta el proceso completo.

---

## 23. Definition of Done

Una tarea no está terminada hasta que:

- cumple el requerimiento;
- respeta la arquitectura;
- incluye autorización cuando corresponda;
- incluye validación;
- incluye migraciones reversibles cuando aplica;
- incluye o actualiza factories/seeders si aplica;
- incluye tests;
- los tests pasan;
- Pint pasa;
- análisis estático pasa si está configurado;
- no introduce fugas multiempresa;
- no contiene secretos;
- documentación relevante está actualizada;
- la aplicación funciona dentro de Docker.

---

## 24. Flujo obligatorio para el agente

Antes de modificar código:

1. leer este `AGENTS.md`;
2. inspeccionar el repositorio;
3. identificar versiones instaladas;
4. revisar código existente antes de crear abstracciones nuevas;
5. ejecutar tests existentes;
6. entender la funcionalidad solicitada;
7. proponer internamente el cambio mínimo coherente.

Durante el desarrollo:

1. trabajar en una funcionalidad a la vez;
2. evitar reescrituras masivas no solicitadas;
3. reutilizar componentes existentes;
4. respetar Repository + Service/Application Layer;
5. escribir tests junto con la funcionalidad;
6. ejecutar tests relevantes después de cada cambio importante.

Antes de finalizar:

1. revisar diff;
2. ejecutar formato;
3. ejecutar tests;
4. ejecutar análisis estático si está disponible;
5. comprobar migraciones;
6. comprobar autorización;
7. comprobar aislamiento entre empresas;
8. verificar que Docker continúe funcionando;
9. informar archivos modificados, decisiones tomadas y pruebas ejecutadas.

---

## 25. Reglas de comportamiento del agente

- No inventar requisitos empresariales.
- No ampliar el alcance sin necesidad.
- No reemplazar tecnologías acordadas.
- No introducir paquetes externos si Laravel o Filament ya resuelven correctamente el problema.
- Antes de agregar un paquete, justificar su necesidad, mantenimiento y compatibilidad.
- No modificar archivos no relacionados sin motivo.
- No borrar código del usuario sin entender por qué existe.
- No realizar comandos destructivos contra datos no descartables.
- No ejecutar `migrate:fresh`, `db:wipe`, `docker compose down -v` ni equivalentes fuera de un entorno confirmado como local/testing.
- No cambiar contratos públicos existentes sin revisar impacto.
- No crear deuda técnica para “resolver rápido”.
- No desactivar seguridad para simplificar Filament.
- No confiar en datos del frontend para empresa, rol o autorización.
- No dejar TODOs silenciosos en funcionalidades consideradas terminadas.
- Si hay ambigüedad que afecte datos, seguridad o reglas del negocio, detener esa parte y documentar claramente la decisión necesaria.
- Para detalles menores sin impacto funcional, elegir la opción más simple y consistente con el repositorio.

---

## 26. Convenciones del proyecto

- Código: inglés.
- UI: español.
- Documentación técnica: español o inglés, pero mantener consistencia por archivo.
- Base de datos: snake_case.
- Clases: StudlyCase.
- Métodos/variables: camelCase.
- Enums: nombres de casos consistentes con el estándar elegido por el repositorio.
- Rutas y slugs: minúsculas y legibles.
- Timestamps: UTC.
- Zona de presentación: America/Santo_Domingo.
- Moneda y facturación no pertenecen al alcance del sistema inicial.

---

## 27. Prioridad técnica

Cuando haya conflicto entre rapidez y calidad, priorizar en este orden:

1. seguridad y aislamiento de datos;
2. integridad de información;
3. corrección funcional;
4. mantenibilidad;
5. pruebas;
6. rendimiento razonable;
7. estética;
8. optimizaciones prematuras.

---

## 28. Resultado esperado

El repositorio debe terminar representando una aplicación que pueda ser demostrada académicamente y utilizada como prueba piloto real durante 15 días, manteniendo evidencia clara de:

- tickets creados;
- responsables;
- estados;
- prioridades;
- comentarios;
- historial;
- resolución;
- cierre;
- segregación entre empresas;
- resultados de pruebas.

El sistema debe quedar preparado para una futura incorporación a producción, pero el alcance del Trabajo Final de Grado corresponde a una implementación y evaluación en ambiente de pruebas.

---

## 29. Documentación oficial de referencia

Priorizar siempre la documentación oficial actual antes que tutoriales antiguos:

- Laravel 13: https://laravel.com/docs
- Laravel Service Container: https://laravel.com/docs/container
- Laravel Authorization: https://laravel.com/docs/authorization
- Laravel Testing: https://laravel.com/docs/testing
- Laravel Sail: https://laravel.com/docs/sail
- Filament 5: https://filamentphp.com/docs/5.x
- PostgreSQL 18: https://www.postgresql.org/docs/18/

Si una API encontrada en un blog o ejemplo entra en conflicto con la documentación oficial de la versión actual, seguir la documentación oficial.
