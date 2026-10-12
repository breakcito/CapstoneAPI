# SDD ledger — plan: docs/superpowers/plans/2026-10-12-ia-chat-module.md

Spec: no hay documento de diseño separado (la spec es la conversación de la sesión).
Las decisiones están ledgeradas abajo como Rulings.

## Pre-flight scan

| Consumido por | Producido por | Encontrado |
|---|---|---|
| T3, T6 | T2 `IAData::*`, `SqlGuard::*` | nombres coinciden |
| T8 | T3 `ToolRegistry`, `ToolInterface` | coinciden |
| T8 | T4 `SchemaService::resumen()` | coincide |
| T8 | T5 `MiniMaxClient::stream()` | coincide |
| T3, T8 | T6 `ReportGenerator::*` | coinciden |
| T8 | T7 `AdjuntoExtractor::procesar()` | coincide |
| T10 | T9 types/store/hook | coinciden |

**Ruling: T6 (ReportGenerator) se ejecuta ANTES de T3 (Tools).** — El Task 3 Step 6 hace que
`GenerarReporteTool` delegue en `ReportGenerator`, que el plan definía en la Task 6. Conflicto de
orden del propio plan. Crear `ReportGenerator` primero. Costo si está mal: un reordenamiento de
dos archivos, nada funcional.

**Ruling: sin TDD.** — El repo no tiene `tests/` ni phpunit (`composer.json` no lo declara,
`vendor/bin/` no tiene phpunit) y `AGENTS.md` §3 prohíbe instalar dependencias sin permiso.
Verificación = `./vendor/bin/phpstan` + `./vendor/bin/pint --test` (declarados en `README.md:141`)
+ smoke con `php artisan tinker --execute`. Costo si está mal: sin suite automatizada.

**Ruling: sin `git commit`.** — `AGENTS.md` §3 lo prohíbe. El ledger es el registro.

**Ruling: `system_prompt()` como función suelta → clase `SystemPrompt::build()`.** — El plan pedía
`Prompts/system_prompt.php` con una función. PSR-4 solo autocarga CLASES: la función nunca se
cargaba y el stream moría con "Call to undefined function". Detectado por el smoke HTTP, no por
phpstan. Costo si está mal: nada, es la única forma que funciona en Laravel.

**Ruling: `SchemaService` usa 2 queries en vez de 74.** — El plan decía "llamar describirTabla()
por cada tabla". Medido: 37 tablas × 2 queries a un MySQL remoto = **46 s**, y PHP mata el proceso
a los **30 s** (FatalError). El cache estático lo tapaba, pero la primera request tras reiniciar
muraba siempre. `IAData::describirEsquema()` hace 1 query de columnas + 1 de índices → **2.5 s**.
Costo si está mal: ninguno, es estrictamente mejor.

**Ruling: `set_time_limit(0)` en el stream.** — `max_execution_time=30s` por defecto en el web
SAPI cortaría cualquier respuesta de MiniMax que pase de 30 s, dejando el chat colgado con spinner.
El plan no lo contemplaba. Costo si está mal: un request puede retener un worker más tiempo.

**Ruling: descarga del .xlsx con `StreamedResponse`, no `Storage::download()`.** — Ver Finding 1.

---

## Tareas

- **Task 1 (config):** complete. OpenSpout `^4.32` instalado; `config/ia.php`, bloque `minimax`
  en `services.php`, vars en `.env` y `.env.example`. phpstan OK.
- **Task 2 (Data + SqlGuard):** complete. Smoke: 8 escrituras, 6 funciones peligrosas y 4 falsos
  positivos verificados. **El smoke cazó un bug**: `SELECT REPLACE(nombre,'a','b')` era rechazado
  por el blacklist → `REPLACE` saiu de la lista (la amenaza real `REPLACE INTO` tiene regla
  propia). forzarLimite: 3 casos OK. phpstan OK.
- **Task 6 (ReportGenerator):** complete (adelantado). .xlsx válido OOXML verificado con `unzip -l`
  y re-leído con OpenSpout. Path traversal neutralizado. phpstan OK.
- **Task 3 (tools):** complete. 4 tools, 37 tablas y 7 vistas `v_bi_*` detectadas. Nombre inexistente
  → `[ERROR]` sin excepción. Injection en nombre de tabla → rechazada. phpstan OK.
- **Task 4 (SchemaService):** complete. Cache verificada: 2.5 s vs 0 ms.
- **Task 5 (MiniMaxClient):** complete (código). `reasoning_split: true` activo. **NO verificado
  contra la API real**: falta `IA_API_KEY`. Falla limpio con `RuntimeException` sin credenciales.
- **Task 7 (AdjuntoExtractor):** complete. PNG pegado sin nombre, CSV, XLSX, data URL completo,
  PDF rechazado con mensaje claro, base64 corrupto y vacío. phpstan OK.
- **Task 8 (controller + endpoints + prompt + wiring):** complete. phpstan OK. Rutas con
  `auth.jwt.custom`. Prompt de 8164 chars con el esquema real embebido.
- **Task 9 (frontend types/store/hook):** complete.
- **Task 10 (frontend UI + header):** complete. eslint 0, `tsc -b && vite build` OK.

## Findings (smoke HTTP)

**Finding 1 — descarga binaria corrupta (CRÍTICO, resuelto).** La respuesta de
`GET /api/ia/reporte/{uuid}` llegaba con 4 bytes `\n\n\n\n` delante del magic `PK` y 4 bytes
perdidos al final: zip inválido, Excel no lo abre. Causa: `Storage::download()` devuelve una
`BinaryFileResponse` de Symfony, que hace `ob_end_clean()` y se desalinea con la salida previa
del buffer. Resuelto con `StreamedResponse` + limpieza explícita. Verificado: md5 idéntico al
disco, `file` = "Microsoft Excel 2007+", 22 filas leídas.

**Finding 2 — preexistente, NO resuelto (reportado al usuario).** TODAS las respuestas de la
aplicación (incluidas preexistentes como `/api/indicadores/generales`) salen con 4 newlines
delante. Probado y descartado como causa mía: sigue igual con el módulo IA removido del boot
path, con `php -S` en vez de `artisan serve`, con `implicit_flush=0`, y con
`laravel/boost|mcp|roster` fuera de `packages.php`. JSON lo tolera (whitespace válido) por eso
nunca se notó; binarios no. **Requiere investigación aparte, no lo toqué.**

**Finding 3 — preexistente, ajeno.** `SQLSTATE[42S22] Unknown column 'ra.id_empleado_solicitante'`
y `act.placa` en consultas de otros módulos. No relacionado con este trabajo.

**Finding 4 — nota de despliegue.** El resumen del esquema tarda 2.5 s en el PRIMER request de cada
proceso (y tras cada TTL de 1 h). Con OpenSpout v4 hace falta **PHP >= 8.3 en el VPS**: local es
8.4.23 y `ext-zip` está OK, pero si Coolify corre 8.2 el `composer install` fallará allí.

---

## Round 2 — reporte visual (usuario: "se queda ahí ejecutando sql / renderiza horrible / el Excel es una puta mierda")

**Ruling: `markdown-it` como dependencia declarada.** — El plan original decía "no hay librería de
markdown, renderizar texto plano, no añadir dependencia". El usuario lo rechazó explícitamente y
autorizó `npm install markdown-it`. Ahora está en `dependencies` (`^14.3.2`) + `@types/markdown-it`.
Configurado con `html: false`: 5 vectores XSS probados (script, img/onerror, javascript:, iframe,
onclick) quedan todos como texto inerte. Costo si está mal: dependencia más que el mínimo.

**Ruling: el spinner de herramienta se congela con un check verde.** — El spinner giraba para
siempre porque `estado` nunca pasaba a un estado terminal y `AIChatMessage` no sabía si la respuesta
seguía viva. Ahora `AIChat` le pasa `transmitiendo = cargando && es el último mensaje`; al llegar el
evento `fin` el spinner se sustituye por un check.

**Ruling: preview de herramienta legible desde el backend.** — Antes se mandaban los primeros 300
chars del JSON crudo (lo que se veía: `{"columnas":["producto","almacen",...`). Ahora
`IAService::resumirHerramienta()` traduce por herramienta: "2 filas · 7 columnas", "37 tablas y
vistas del esquema", "producto · 10 columnas".

**Ruling: post-proceso del .xlsx con ZipArchive para ancho de columna y panel congelado.** —
OpenSpout 4 expone estilos POR CELDA (`Cell::fromValue($v, $style)`, `Row::fromValues($vals, $style)`)
y NO tiene API de auto-ancho ni de freeze panes. Se inyecta `<cols>` + `<pane state="frozen">` en
`xl/worksheets/sheet1.xml` con ZipArchive después de cerrar el writer. Costo si está mal: si
OpenSpout 5 cambia el nombre de la hoja, la inyección no aplica (y el reporte vuelve al ancho por
defecto, no se rompe).

**Finding 5 — nombres de clase adivinados y corregidos antes de escribir.** — Escribí primero
`HorizontalAlignment`/`VerticalAlignment`; las clases reales son `CellAlignment`/
`CellVerticalAlignment`. Verificar el vendor antes de codificar evitó tres imports rotos.

### Verificado en el round 2

- Markdown: `<table>` OK, 7 `<th>`, 14 `<td>`, 3 `<strong>`, `<ul>`+2 `<li>`, acentos UTF-8 OK,
  0 guiones `|---|` crudos, 0 asteriscos sueltos, XSS escapado.
- Excel: encabezados `Producto | Almacen | Stock Actual Base | Costo | Fecha Vencimiento`;
  `s="1"` en las 5 celdas de encabezado; `fonts 1→2`, `fills 2→3`, `cellXfs 1→2`; `<cols>` con
  anchos 23/19/19/10/19; `<pane state="frozen">` presente; `<cols>` antes de `<sheetData>`;
  `file` = "Microsoft Excel 2007+"; sigue leyéndose con OpenSpout.
- Descarga tras el post-proceso: md5 idéntico al disco, 200, `504b0304`, xlsx válido.
- `phpstan` 0 errores, `pint --test` pass, `eslint` 0 en lo nuevo, `vite build` ✓.

1. Poner `IA_API_KEY` en `capstoneAPI/.env` (y en Coolify). Sin eso el chat responde
   "No puedo completar la consulta: Falta IA_API_KEY en el archivo .env."
2. Confirmar versión de PHP del VPS (>= 8.3) por OpenSpout.
3. PDF sigue sin soporte (requeriría `smalot/pdfparser`, no instalado sin permiso).
4. Finding 2, si quiere que lo investigue.
---

## Round 3 — "me dio de todo menos lo que le pedi / no me muestres detalles tecnicos"

**Finding 6 — EL USUARIO PUSO 99999 EN LOS LIMITES DE `.env`.** — No es un cambio nuestro:
`IA_MAX_FILAS`, `IA_MAX_ITERACIONES`, `IA_MAX_MENSAJES`, `IA_MAX_ADJUNTOS`, `IA_MAX_ADJUNTOS_MB`
e `IA_SCHEMA_TTL` quedaron en 99999. Eso desactiva el tope de 500 filas, el tope del loop de
tools, el limite de mensajes, el de adjuntos y el de peso, y hace que el esquema nunca se
reconstruya. **No se toco `.env` (prohibido por `AGENTS.md`).** Reportado al usuario.

**Ruling: dos secciones nuevas en el SystemPrompt.** — "Que NUNCA le muestras al usuario" (prohibe
SQL, nombres de tabla/vista/columna, nombre de BD y motor, errores de colacion/tipos/sintaxis,
nombres de herramientas y narracion del proceso; con ejemplo MALO y BUENO copiados del caso real)
y "Si pide un reporte o un archivo: dale el archivo" (llamar generar_reporte PRIMERO, sin texto
previo; respuesta visible de 1-2 lineas; no repetir las tablas en el chat). Prompt: 10 923 chars.

**Ruling: `generar_reporte` acepta `secciones` multi-hoja.** — Un "reporte general" son 5 tematicas
distintas; con un solo SQL salia un archivo de 8 filas y el resto iba al chat. Ahora
`generarConSecciones([[nombre, sql], ...])` produce UN xlsx con una hoja por tematica. Verificado
con 5 hojas. `sql` + `nombre_archivo` siguen funcionando igual. Maximo 12 secciones.

**Ruling: nombres de hoja inyectados en `xl/workbook.xml`.** — OpenSpout 4 crea `Sheet`, `Sheet2`...
y `Sheet` NO tiene setter (`getName()` es solo lectura, `addNewSheetAndMakeItCurrent()` no recibe
nombre). Se renombran por `preg_replace_callback` con `htmlspecialchars` para no romper el XML.

**Finding 7 — `preg_replace_callback` con limite 1 solo renombraba la primera hoja.** — El smoke lo
detecto (hojas 2-5 seguian como "SheetN"). Sin el smoke, el 80% del reporte queda con nombres
genericos. Corregido.

**Finding 8 — una seccion sin filas no rompe el archivo.** — Se escribe
`(sin registros para esta seccion)` con estilo de encabezado en vez de una hoja vacia sin pistas.

### Verificado en el round 3
- Multi-hoja: 5 hojas, nombres correctos, encabezados legibles, datos reales de las vistas BI.
- `<cols>` y `<pane state="frozen">` inyectados en las 5 hojas.
- Tool: `DROP` en una seccion -> `[ERROR] SQL rechazado` (el guard corre antes de escribir);
  sin `sql` ni `secciones` -> `[ERROR]` claro.
- Descarga byte-exacta: md5 identico al disco, 11 537 bytes, Microsoft Excel 2007+.
- `phpstan` 0 errores, `pint --test` pass, `eslint` OK, `vite build` OK.
