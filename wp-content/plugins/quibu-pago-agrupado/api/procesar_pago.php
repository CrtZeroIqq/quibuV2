<?php
unset($_SESSION['quibu_pago_credentials']);
require_once dirname(__FILE__, 5) . '/wp-load.php';
require_once dirname(__FILE__, 5) . '/vendor/autoload.php';

use Transbank\Webpay\WebpayPlus\Transaction;
use Transbank\Webpay\Options;

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wp_redirect(home_url());
    exit;
}

// Sanitizar inputs
$rut         = sanitize_text_field($_POST['rut']);
$grupo_id    = intval($_POST['grupo_id']);
$cantidad    = intval($_POST['cuotas_a_pagar']);
$total_front = intval($_POST['total_a_pagar']); // <-- lo que llega desde el input hidden del formulario

if ($cantidad <= 0 || $total_front <= 0) {
    wp_redirect(home_url());
    exit;
}

// Tablas
global $wpdb;
$tabla_cuotas = $wpdb->prefix . 'cuotas_definidas';
$tabla_pagos  = $wpdb->prefix . 'pagos_cuotas';

// Obtener cuotas NO pagadas por el RUT
$cuotas = $wpdb->get_results(
    $wpdb->prepare("
        SELECT * FROM $tabla_cuotas
        WHERE id_grupo = %d
        AND id NOT IN (
            SELECT id_cuota FROM $tabla_pagos WHERE rut = %s
        )
        ORDER BY fecha_cuota ASC
        LIMIT %d
    ", $grupo_id, $rut, $cantidad)
);

if (count($cuotas) === 0) {
    echo "❌ No se encontraron cuotas pendientes.";
    exit;
}

// Suponemos que todas las cuotas tienen el mismo valor
$valor_cuota = (int)$cuotas[0]->valor;
$total_base  = $valor_cuota * $cantidad;

// --- Determinar tramo de fee base y progresivo ---
if ($valor_cuota >= 1000 && $valor_cuota <= 5000) {
    $fee_base = 200; $fee_prog = 0.005;
} elseif ($valor_cuota >= 5100 && $valor_cuota <= 8000) {
    $fee_base = 220; $fee_prog = 0.006;
} elseif ($valor_cuota >= 8100 && $valor_cuota <= 10000) {
    $fee_base = 230; $fee_prog = 0.008;
} elseif ($valor_cuota >= 10100 && $valor_cuota <= 20000) {
    $fee_base = 260; $fee_prog = 0.01;
} elseif ($valor_cuota >= 20100 && $valor_cuota <= 30000) {
    $fee_base = 300; $fee_prog = 0.01;
} else {
    $fee_base = 300; $fee_prog = 0.01;
}

// Calcular total backend
$fee_progresivo = $total_base * $fee_prog * $cantidad;
$total_fee      = $fee_base + $fee_progresivo;
$total_backend  = round($total_base + $total_fee);

// Validación de seguridad
if (abs($total_front - $total_backend) > 50) {
    echo "<div style='color:red;padding:2em;'>❌ Error: el monto calculado no coincide con el recibido. (Cliente: $total_front | Servidor: $total_backend)</div>";
    exit;
}

// Guardar en sesión los datos del pago
$_SESSION['quibu_pago'] = [
    'cuotas'    => array_map(fn($c) => $c->id, $cuotas),
    'rut'       => $rut,
    'grupo_id'  => $grupo_id,
    'total'     => $total_backend
];

// Credenciales Transbank (modo integración)
$apiKey       = '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C';
$commerceCode = '597055555532';
$options      = new Options($apiKey, $commerceCode, Options::ENVIRONMENT_INTEGRATION);

// Guardar también las credenciales
$_SESSION['quibu_pago_credentials'] = [
    'api_key'       => $apiKey,
    'commerce_code' => $commerceCode
];

// Crear transacción
$buy_order  = 'quibu_' . time();
$session_id = session_id();
$return_url = 'https://quibu.cl/api/respuesta-pago.php';

try {
    $transaction = new Transaction($options);
    $response = $transaction->create(
        $buy_order,
        $session_id,
        $total_backend,
        $return_url
    );

    header("Location: " . $response->getUrl() . "?token_ws=" . $response->getToken());
    exit;

} catch (Exception $e) {
    echo '<div style="padding:2em;color:red;">Error al iniciar el pago: ' . $e->getMessage() . '</div>';
    exit;
}
