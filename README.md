# Cupper & Hannia — API (BlackSilver)

API del ERP Cupper & Hannia para la industria minera. Digitaliza y conecta operaciones logísticas entre el corporativo (compras, finanzas) y el campo (almacenes remotos en mina, distribución de insumos).

## Módulos

- **Configuración**: Empresas, Almacenes, Concesiones, Minas/Labores, Contratistas, Organigrama
- **Inventarios**: Productos, Categorías, Lotes, Kardex
- **Compras**: Proveedores, Clientes, Cotizaciones, Órdenes de Compra, Transferencias de OC
- **Salidas de almacén**: Requerimientos (atención), Solicitudes de Reabastecimiento (atención), Préstamos entre Almacenes (atención)
- **Personal y accesos**: Empleados, Login, Perfil, Roles, Cuentas
- **Operaciones**: Activos Fijos, Control de Uso, Mantenimiento, Control de Consumo, Producción Mineral, Lote Mineral
- **RR.HH.**: Programación de Horarios, Asistencia, Planilla, Contratos de Empleado
- **Otros**: Modo Auditoría, Personal Externo

## Stack

- PHP 8.2 / Laravel 12
- JWT: `php-open-source-saver/jwt-auth`
- WebSockets: Laravel Reverb (eventos en tiempo real)
- Dev local: Laravel Octane + FrankenPHP
- DB: MySQL con SQL crudo (`DB::select`, `DB::insertGetId`) + Eloquent con métodos estáticos
- Análisis estático: PHPStan (Larastan)

## Arquitectura: Hybrid Modular

No usa la estructura estándar de Laravel. Cada proceso de negocio es un módulo independiente en `app/Modules/<Dominio>/`. Las entidades compartidas (productos, almacenes, kardex, etc.) viven en una capa global en `app/Controllers/`, `app/Endpoints/`, `app/Data/`, `app/Services/`.

### Capas

| Capa        | Ruta                                  | Qué hace                                     |
| ----------- | ------------------------------------- | -------------------------------------------- |
| Endpoints   | `app/Modules/<D>/XEndpoints.php`      | Define rutas.                                |
| Controllers | `app/Modules/<D>/Controller/`         | Valida request y orquesta. Sin SQL.          |
| Services    | `app/Modules/<D>/Service/`            | Lógica de negocio. Sin SQL directo.          |
| Data        | `app/Modules/<D>/Data/` o `app/Data/` | Único lugar con SQL.                         |
| Models      | `app/Models/`                         | Métodos estáticos para insert/update/select. |

### Capa global

Para catálogos recurrentes (almacenes, productos, marcas, empleados, unidades de medida, etc.) usar `AuxController` + `AuxEndpoints` (`/api/aux/...`). Prohibido duplicar endpoints genéricos dentro de los módulos.

`ArchivoController` / `ArchivoEndpoints` gestionan adjuntos. `MenuNavController` / `MenuNavEndpoints` construyen la navegación dinámica según el rol del usuario.

### Enums

En `app/Shared/Enums/`. **Cada proceso físico tiene su Enum dedicado** dentro de su subcarpeta. Ejemplo: las recepciones usan `EstadoOCTransRecepcion`, las transferencias usan `EstadoOCTransferencia` — nunca se reciclan entre procesos distintos.

### Respuestas

SIEMPRE `App\Shared\Responses\ApiResponse::success()` o `::error()`. Helper estático, estructura `{success, data, message, errors?}`.

## Reglas críticas

1. **Services sin acceso a BD**. Toda consulta va por la capa Data. El Service solo orquesta.
2. **`DB::transaction()`** en cualquier flujo que toque múltiples tablas.
3. **Sin reutilización forzada**. Registrar y Actualizar son métodos separados aunque compartan partes. Mejor duplicación clara que un "universal" complejo.
4. **Arrays como parámetro** solo en cabecera+detalle (OC, comparativos). Si un método recibe `array`, documentar con `@param array $x` describiendo cada clave.
5. **DocBlocks** breves, sin `@param` para primitivos (`int`, `string`, etc.). Solo en arrays y objetos.
6. **Stock en unidad base**: `cantidad_base = cantidad × contenido_por_presentacion`. El stock siempre se mide en unidad base.
7. **Toda afectación de stock** pasa por `App\Services\LotesProductosService` (transaccional + Kardex). Prohibido tocar stock desde otro lado.
8. **Kardex inmutable**. No se borra ni se edita. Para errores usar "Ajuste de Stock". Cada movimiento guarda `stock_anterior` y `stock_nuevo`.
9. **No se usan Foreign Keys**, solo `INDEX` declarados manualmente. Las relaciones se garantizan por aplicación.
10. **No se usan migrations ni seeders de Laravel**. Las tablas y datos iniciales se crean con SQL plano ejecutado directamente sobre el motor.
11. **Toda tabla nueva** debe tener `id INT PRIMARY KEY AUTO_INCREMENT`, `INDEX` por cada columna usada en `WHERE`/`JOIN`, `INT` para IDs, `VARCHAR` con largo definido para textos, `DECIMAL` para montos, `DATE`/`DATETIME` para fechas, y campo `estado` (varchar) si requiere borrado lógico en lugar de `DELETE`.

## Conceptos de negocio

- **Cantidad base**: la unidad universal de stock. Todo se multiplica por `contenido_por_presentacion`.
- **Auto-PO**: al aprobar una cotización, la OC se genera automáticamente heredando precios, unidades e impuestos del comparativo.
- **Almacén puente**: una OC puede recepcionarse en almacén equivocado y luego transferirse al correcto vía `OrdenesCompraRecepcionTransferencias`.
- **Requerimiento granular**: cada ítem de un requerimiento se aprueba/rechaza/pendiente por separado, con su línea de tiempo de auditoría propia.
- **Cotización parcial**: solo se aprueban ítems específicos; el resto queda pendiente o rechazado.
- **Lotes**: insumos críticos entran como Lotes. Stock se rastrea por lote, no por producto.
- **Kardex**: registro inmutable de movimientos. Cada fila guarda stock anterior y stock nuevo.

## Comportamiento HTTP

- `200 OK` para GET/PUT/POST exitosos.
- `204 No Content` para `OPTIONS` (preflight CORS). El middleware `HandleCors` corta la request antes de llegar al controller. Es comportamiento estándar, no un bug.
- `401 Unauthorized` cuando el JWT es inválido o expiró. Middleware: `auth.jwt.custom`.
- Errores controlados: `ApiResponse::error()` retorna `4xx/5xx` con `{success:false, message, errors?}`.

## Estructura de un módulo

```
app/Modules/Cotizaciones/
├── CotizacionesEndpoints.php
├── Controller/        # valida y orquesta
├── Service/           # lógica, sin SQL
└── Data/              # SQL
```

## Ejecución local

Setup inicial (una vez por máquina):

```bash
composer install
php artisan key:generate
php artisan storage:link
```

Diario (3 terminales):

```bash
# T1 — API
php artisan serve

# T2 — WebSockets
php artisan reverb:start
```

Si algo no se refleja después de editar, o cambios en `composer.json`, limpiar todos los caches con un solo comando:

```bash
php artisan optimize:clear
```

### Laravel Herd

Setup inicial (una vez por máquina):

1. Descargar e instalar Herd desde **https://herd.laravel.com/download**.
2. Abrir terminal y vincular la api:

```bash
herd link api-local-blacksilver
```

Diario (1 terminal):

```bash
# T1 — Sitio Laravel (nginx + php-fpm vía Herd)
herd start

# T1 — WebSockets (Reverb corre como proceso PHP normal)
php artisan reverb:start
```

## Reglas para IA

1. **Leer este README completo antes de actuar.** Es la fuente de verdad del proyecto. Si el usuario da contexto que contradice lo aquí documentado, avisar antes de cambiar nada.
2. **Verificar versiones en `composer.json`** antes de usar APIs de librerías. Si hay duda sobre comportamiento actual, **buscar en internet** — el entrenamiento del modelo puede estar desactualizado o diferir con docs vigentes.
3. **No commitear ni hacer push** sin que el usuario lo pida explícitamente.
4. **No alterar la base de datos** (migraciones, seeds, registros). Indicar al usuario qué debe correr o modificar.
5. Después de cualquier cambio: `./vendor/bin/phpstan`.
6. **Cuestionar reusos forzados.** Si piden "una función que sirva para X e Y", proponer separar antes de implementar.
7. Si una idea rompe alguna regla de este documento, plantear la alternativa antes de codear.
