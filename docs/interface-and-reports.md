# Interfaz, métricas y reportes

## Navegación

Los dos paneles comparten una identidad visual índigo, tarjetas, iconos Heroicons y componentes responsive. La navegación interna se organiza en Soporte (panel de control y tickets), Análisis (reportes) y Administración (empresas, usuarios y categorías). Los grupos y recursos solo aparecen para roles autorizados. El portal muestra el soporte de la empresa del cliente.

El CSS compartido está en `public/css/helpdesk.css` y se registra mediante la API de assets de Filament. No requiere instalar un tema externo ni compilar assets adicionales. El parámetro de versión deriva de la fecha del archivo. Incluye modo oscuro, foco visible y respeto por reduced-motion.

## Dashboard

El período inicial abarca 30 días, según la zona America/Santo_Domingo. El selector de período se presenta plegado para priorizar los indicadores. Puede ampliarse para cambiar fechas y, para personal interno, empresa.

Los indicadores principales muestran abiertos, en proceso, críticos abiertos y resueltos/cerrados. El gráfico de línea muestra creaciones diarias y los gráficos de anillo distribuyen estados y prioridades. Las solicitudes recientes enlazan al detalle. Los gráficos conservan tooltips, leyendas interactivas y estados vacíos de Filament/Chart.js.

## Reportes

Ruta: `/admin/reports`. Acceso exclusivo para administradores y agentes activos, validado también en el servicio de exportación.

El usuario selecciona período, empresa, agente, categoría, estado y prioridad, y pulsa **Aplicar filtros**. **Restablecer** recupera el período inicial. Los borradores de filtros se separan de los filtros aplicados: una fecha incompleta o inválida no reemplaza el reporte anterior. Se permite un máximo de 366 días. Los parámetros iniciales inválidos producen HTTP 422.

Indicadores, gráficos y descarga CSV utilizan los mismos filtros aplicados. La tabla permite además búsqueda, ordenación y paginación de sus resultados; la descarga incluye todos los tickets correspondientes a los filtros del reporte, independientemente de la búsqueda local o página actual de la tabla.

Además de los indicadores del dashboard, Reportes presenta total de tickets, primera respuesta media y resolución media. Contiene gráficos por estado, prioridad, empresa, agente y categoría, más la tendencia diaria. Los gráficos por empresa/agente/categoría presentan los diez grupos con mayor volumen; las distribuciones por estado y prioridad incluyen todos los valores.

La exportación transmite registros en lotes, sin cargar todos los tickets en memoria. Incluye número, asunto, empresa, categoría, agente, estado, prioridad y fechas. Usa UTF-8 con BOM para facilitar su apertura en hojas de cálculo y neutraliza valores que podrían interpretarse como fórmulas. No exporta comentarios, notas internas, rutas de archivos ni metadata de auditoría.

## Definiciones

| Métrica | Cálculo |
| --- | --- |
| Abiertos | Estados distintos de resolved, closed y cancelled |
| Críticos abiertos | Prioridad critical dentro de los abiertos |
| Resueltos / cerrados | Estado actual resolved o closed |
| Porcentaje resuelto | Resueltos/cerrados dividido entre el total del período, incluyendo cancelados en el denominador |
| Primera respuesta media | Promedio de first_responded_at menos created_at; solo tickets con respuesta pública válida |
| Resolución media | Promedio de resolved_at menos created_at; solo tickets con resolución válida |
| Tendencia | Tickets creados por día local, incluyendo días sin tickets con valor cero |

Los tiempos se expresan en horas con un decimal. Si no existen observaciones válidas, se muestra **Sin datos**. Las métricas no prometen cumplimiento de SLA.

El período se aplica a la fecha de creación del ticket. PostgreSQL almacena timestamps en UTC; los límites de día local se convierten a UTC antes de consultar. Las agregaciones se realizan en SQL y respetan tanto el aislamiento por empresa como SoftDeletes. Las referencias históricas a empresas, agentes y categorías archivados se conservan.

## Historial

El historial se presenta como una línea de tiempo con fecha, actor, icono, cambio anterior/nuevo y justificación cuando existe. Los estados y prioridades usan etiquetas en español y los responsables se muestran por nombre. No se presenta JSON técnico.

Las notas internas y sus eventos permanecen ocultos al cliente. Los comentarios de texto plano y las justificaciones se escapan al renderizar. Los comentarios enriquecidos se sanean con una lista explícita de etiquetas y protocolos, sin estilos arbitrarios, scripts ni imágenes embebidas. Las descargas siguen pasando por su autorización original. El rediseño conserva la auditoría inmutable.

## Verificación

La suite incorpora pruebas de métricas, aislamiento, fechas locales, filtros, estados vacíos, permisos de reportes, CSV, protección de fórmulas, filtros Livewire, timeline y escape de contenido. También comprueba que un borrador inválido conserva el reporte anterior y que tickets terminales ocultan acciones que no pueden ejecutarse.

Se revisaron con Chromium las pantallas de dashboard, reportes y detalle, el portal móvil, el modo oscuro y la ausencia de desbordamiento horizontal a 390 px. Los gráficos usan los componentes nativos de Filament 5; no se añadieron dependencias de frontend al proyecto.

## Conversación y actualización del ticket

En escritorio, los detalles y adjuntos aparecen en la columna izquierda y la conversación con su editor en la derecha. En móvil se apilan. El editor permite negrita, cursiva, subrayado, tachado, listas, enlaces, citas y bloques de código. Las notas internas siguen disponibles únicamente para el personal autorizado.

El comentario se envía desde la misma pantalla, sin modal ni redirección. El borrador se limpia solo después de un envío correcto. El marcador is_rich_text distingue el HTML saneado de los comentarios históricos de texto plano; estos últimos conservan sus caracteres literales y saltos de línea. Los comentarios vacíos, incluido HTML sin texto visible, se rechazan en servidor.

Las acciones de asignación, prioridad, estado, comentarios y adjuntos actualizan inmediatamente todos los datos del ticket. Los cambios de otras sesiones se detectan cada cinco segundos mientras la vista está visible, mediante polling de Livewire. Livewire reduce la frecuencia en pestañas en segundo plano. El refresco conserva el borrador y vuelve a comprobar el acceso a la empresa y el ticket. Si se cierra el ticket, el editor deja de estar disponible y el servidor rechaza nuevos envíos.

El historial muestra los eventos más recientes primero, en una región con scroll interno de hasta 20 rem (18 rem en móvil), accesible con teclado. La conversación tiene su propio scroll para no desplazar el editor fuera de la pantalla al acumular mensajes.
