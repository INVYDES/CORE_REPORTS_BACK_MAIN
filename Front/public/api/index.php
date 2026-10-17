<?php

/**
 * Entrada de la API en el espacio web de IONOS.
 *
 * Layout en el servidor:
 *   ~/core-reports/          <- docroot del dominio (SPA)
 *   ~/core-reports/api/      <- este archivo
 *   ~/core-reports-api/      <- app Laravel completa (fuera del docroot)
 *
 * El .htaccess raíz reescribe /api/* a este archivo y Laravel enruta usando
 * REQUEST_URI (/api/v1/...), por eso solo hay que ajustar SCRIPT_NAME para
 * que las URLs generadas no incluyan /api.
 */

$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$dir = rtrim(dirname($script), '/');
$base = preg_replace('#/api$#', '', $dir);
$_SERVER['SCRIPT_NAME'] = $base . '/index.php';
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

$appRoot = dirname(__DIR__, 2) . '/core-reports-api';

require $appRoot . '/public/index.php';
