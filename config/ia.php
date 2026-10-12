<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modulo IA - Chat de analytics
    |--------------------------------------------------------------------------
    |
    | Configuracion del modulo de asistente por lenguaje natural sobre la base
    | de datos. El sistema es de SOLO LECTURA: el modelo nunca escribe.
    |
    */

    // Credenciales y endpoint del proveedor (formato compatible OpenAI).
    'api_key' => env('IA_API_KEY'),

    'base_url' => env('IA_BASE_URL', 'https://api.minimax.io/v1'),

    'model' => env('IA_MODEL', 'MiniMax-M3'),

    /*
     * Rutas SSE minimas entre el cliente y la API. El historial viaja completo
     * en cada request porque el backend es stateless, asi que hay que acotar
     * cuantas pantallas de conversacion se pueden pedir en una sola llamada.
     */
    'max_mensajes' => (int) env('IA_MAX_MENSAJES', 40),

    // Unico rol autorizado a usar el modulo.
    'rol_admin' => (int) env('IA_ROL_ADMIN', 2),

    // Tope de filas que puede devolver ejecutar_sql y generar_reporte.
    'max_filas' => (int) env('IA_MAX_FILAS', 500),

    // Tope de vueltas del loop de herramientas por peticion.
    'max_iteraciones' => (int) env('IA_MAX_ITERACIONES', 8),

    // Segundos que el resumen del esquema se mantiene en memoria del proceso.
    'schema_ttl' => (int) env('IA_SCHEMA_TTL', 3600),

    // Adjuntos por mensaje y peso maximo de cada uno.
    'max_adjuntos' => (int) env('IA_MAX_ADJUNTOS', 5),
    'max_adjuntos_mb' => (int) env('IA_MAX_ADJUNTOS_MB', 10),

    // Formatos que MiniMax procesa nativamente como imagen (no PDF ni Office).
    'tipos_imagen' => ['jpeg', 'png', 'gif', 'webp'],

    // Tokenes que jamas pueden aparecer en un SQL ejecutado por el modelo.
    'sql_palabras_prohibidas' => [
        'INSERT',
        'UPDATE',
        'DELETE',
        'DROP',
        'ALTER',
        'TRUNCATE',
        'CREATE',
        'RENAME',
        'GRANT',
        'REVOKE',
        'CALL',
        'DO',
        'HANDLER',
        'LOCK',
        'UNLOCK',
        'SET',
    ],

    // Funciones de MySQL que sirven para exfiltrar o denegar servicio.
    'sql_funciones_prohibidas' => [
        'INTO\s+OUTFILE',
        'INTO\s+DUMPFILE',
        'LOAD_FILE',
        'SLEEP',
        'BENCHMARK',
    ],

    /*
     * Tablas que el asistente NO puede consultar, por prefijo.
     *
     * Son las vistas BI pre-armadas. El dashboard de Indicadores las sigue
     * usando, pero el chat escribe el SQL de cada reporte sobre las tablas
     * del dominio. Sin esta lista, el modelo cae en la vista de inventario y
     * devuelve un agregado por almacen cuando le pidieron el detalle por
     * producto.
     */
    'tablas_prohibidas' => [
        'v_bi_',
    ],
];
