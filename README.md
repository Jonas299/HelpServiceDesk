# Help Service Desk

Sistema de soporte multiempresa con Laravel 13, Filament 5, PostgreSQL 18 y PHP 8.4 en Docker. Implementa empresas, usuarios, categorías, tickets, asignaciones, prioridades, seguimiento, notas internas, adjuntos privados, historial y paneles en español.

## Inicio con Docker

Requisitos: Docker Engine/Desktop con Compose v2. No requiere PHP, Composer ni PostgreSQL instalados en el host.

```bash
cp .env.example .env
```

Cambiar `DB_PASSWORD` en `.env` por un valor local aleatorio antes de iniciar. No versionar `.env`. El puerto de la aplicación se configura con `APP_PORT` (8080 por defecto).

```bash
docker compose build app
docker compose run --rm app composer install --no-interaction
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan filament:assets
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan db:seed
docker compose up -d
```

El seeder local/testing establece `password` para todos los usuarios demo, incluidos los existentes.

| Acceso | URL | Usuario demo |
| --- | --- | --- |
| Administración | http://localhost:8080/admin | admin@example.test |
| Agente | http://localhost:8080/admin | agent1@example.test / agent2@example.test |
| Portal cliente | http://localhost:8080/portal | client1@example.test / client2@example.test / client3@example.test |
| Correo de desarrollo | http://localhost:8025 | Sin autenticación, acceso local |

Los administradores crean empresas, usuarios y categorías. Un cliente requiere una empresa activa. No hay registro público. Las contraseñas de usuarios nuevos deben tener al menos 12 caracteres.

## Operación

```bash
docker compose ps
docker compose logs --tail=100 app queue
docker compose exec app php artisan migrate:status
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
docker compose stop
```

El servicio `queue` procesa las notificaciones por correo después de confirmar la transacción. `scheduler` ejecuta el planificador. Redis gestiona cache y colas; Mailpit captura correos del entorno local. Las sesiones se guardan en PostgreSQL.

El volumen `postgres-data` conserva las bases `helpdesk` y `helpdesk_testing`. Los adjuntos están en `storage/app/private`, fuera del directorio público. No ejecutar `docker compose down -v` si se desea conservar los datos.

## Calidad y pruebas

```bash
docker compose exec app vendor/bin/pint --test
docker compose exec app php artisan test
docker compose exec app vendor/bin/phpstan analyse --no-progress --memory-limit=512M
```

Para aplicar formato: `docker compose exec app vendor/bin/pint`.

Las pruebas usan exclusivamente PostgreSQL y la base separada `helpdesk_testing`. El arranque de tests verifica el nombre de base antes de permitir migraciones destructivas. No ejecutar la suite en paralelo sobre la misma base: contiene pruebas con procesos concurrentes que necesitan una base exclusiva.

`docker/testing.sql` crea la base de pruebas al inicializar un volumen nuevo. Para un volumen preexistente que carezca de ella:

```bash
docker compose exec postgres psql -U helpdesk -d helpdesk -c 'CREATE DATABASE helpdesk_testing OWNER helpdesk;'
```

## Estructura

- `app/Domain`: contratos, repositorios, DTOs, servicios, enums y eventos.
- `app/Policies`: autorización en servidor.
- `app/Filament/Admin`: recursos internos.
- `app/Filament/Client`: recursos del portal.
- `app/Filament/Shared`: formularios, tablas, páginas y widgets compartidos.
- `tests/Feature`: dominio, HTTP, Livewire, aislamiento, concurrencia y transacciones.
- `docs`: arquitectura, modelo de datos, estados y estrategia de pruebas.

Los assets de los paneles son publicados por Filament; estos paneles no necesitan una compilación Vite adicional. Se conserva la configuración estándar de Vite para futuras vistas propias.

## Decisiones del MVP

- Todas las entidades de negocio usan SoftDeletes; el historial permanece append-only y carece de acciones de edición o borrado.
- Los tickets se resuelven, cierran o cancelan mediante estados; el borrado lógico no sustituye el flujo de negocio.
- Los agentes pueden operar los tickets internos; los clientes solo consultan y comentan tickets de su empresa.
- Resolver, cancelar y reabrir requiere justificación. Resolver y cerrar registran fechas independientes.
- Los archivos se adjuntan al ticket. Se admiten PDF, PNG, JPEG y TXT hasta 10 MB, con descarga autenticada. Los adjuntos a comentarios quedan fuera de esta primera implementación.
- Las notas internas no generan correos al cliente. Las notificaciones usan enlaces y no incorporan contenidos internos.
- Predis evita depender de una extensión Redis en el host; Larastan se usa solo en desarrollo para análisis estático. Ambos están fijados por `composer.lock`.

## Entorno piloto

Compose sirve el entorno de desarrollo y demostración en localhost, con el servidor de desarrollo de Laravel. Para exposición pública se requiere un despliegue de producción con servidor web/PHP-FPM, TLS, `APP_DEBUG=false`, cookies seguras, credenciales propias, respaldos y revisión de infraestructura. No exponer Mailpit ni PostgreSQL públicamente.

La zona de persistencia es UTC y la presentación usa America/Santo_Domingo. Los seeders solo se ejecutan en local/testing y generan información ficticia.

Referencias oficiales: [Laravel 13](https://laravel.com/docs/13.x), [Filament 5](https://filamentphp.com/docs/5.x), [PostgreSQL 18](https://www.postgresql.org/docs/18/).

## Interfaz y reportes

El diseño compartido incluye navegación agrupada, iconos, tema claro/oscuro, dashboard con gráficos interactivos y un historial tipo timeline. El personal interno dispone de [Reportes](http://localhost:8080/admin/reports) con filtros, indicadores de respuesta y resolución, gráficos y descarga CSV. Ver [interfaz y definiciones de métricas](docs/interface-and-reports.md).

La vista del ticket coloca la conversación junto a los detalles, con editor enriquecido integrado y un timeline con scroll interno. Los cambios propios se reflejan inmediatamente y los de otras sesiones se sincronizan cada cinco segundos mientras la vista está visible, conservando el borrador.

Cada ticket admite múltiples observadores desde su creación y desde la sección Participantes del detalle. Incluye controles para agregar, retirar y seguir el ticket, además de un teléfono de contacto opcional en la creación. Se muestran como participantes, pueden comentar según su rol y reciben actualizaciones públicas. El personal interno gestiona la selección; se conserva el aislamiento por empresa. Ver [observadores y participantes](docs/ticket-observers.md).

Los administradores configuran criterios y plazos en **Administración → Prioridades y tiempos**. Los tiempos de respuesta y solución comienzan sin definir. Ver [lineamientos de prioridad](docs/priority-guidelines.md).

### Boost, usuarios demo y acceso a PostgreSQL

Laravel Boost está instalado como dependencia de desarrollo, con guías y servidor MCP según la [documentación oficial](https://laravel.com/docs/13.x/boost). `.mcp.json` ejecuta Boost mediante Docker. Mantener `app` iniciado y reiniciar el cliente del agente para cargarlo. En Codex, registrar desde la raíz: `codex mcp add laravel-boost -- docker compose exec -T app php artisan boost:mcp`.

Conectar el gestor de base de datos a `127.0.0.1`, puerto `5432` (o `DB_FORWARD_PORT`), base `helpdesk`, usuario `helpdesk` y contraseña `DB_PASSWORD` del `.env`. El puerto se publica en `compose.yaml`, donde corre PostgreSQL; el Dockerfile corresponde a PHP. Aplicar cambios de puerto con `docker compose up -d postgres`.

El seeder local/testing crea o actualiza los usuarios demo con contraseña `password`: `docker compose exec app php artisan db:seed`. Personal interno y usuarios clientes tienen páginas separadas; los clientes muestran su empresa.

Los tickets nuevos notifican al personal interno activo y participantes autorizados. Los demás cambios notifican al creador, responsable y observadores; las reasignaciones también al responsable anterior. Se excluye al autor. Las notas internas solo llegan a participantes internos. La cola comprueba nuevamente autorización y participación. Los correos especifican el evento y los valores anteriores/nuevos cuando corresponda.
