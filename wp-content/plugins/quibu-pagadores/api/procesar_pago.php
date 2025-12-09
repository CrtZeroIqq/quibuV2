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

$cuotas = isset($_POST['cuotas']) ? array_map('intval', $_POST['cuotas']) : [];
$rut = sanitize_text_field($_POST['rut']);
$grupo_id = intval($_POST['grupo_id']);

if (empty($cuotas)) {
    wp_redirect(home_url());
    exit;
}

// Calcular total
global $wpdb;
$tabla_cuotas = $wpdb->prefix . 'cuotas_definidas';

$total = 0;
foreach ($cuotas as $id) {
    $monto = $wpdb->get_var($wpdb->prepare("SELECT valor FROM $tabla_cuotas WHERE id = %d", $id));
    if ($monto !== null) {
        $total += ((int)$monto + 200);
    }
}

// Guardar en sesión los datos del pago
$_SESSION['quibu_pago'] = [
    'cuotas'    => $cuotas,
    'rut'       => $rut,
    'grupo_id'  => $grupo_id,
    'total'     => $total
];

// Credenciales Transbank de integración
$apiKey = '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C';
$commerceCode = '597055555532';
$options = new Options($apiKey, $commerceCode, Options::ENVIRONMENT_INTEGRATION);

// Guardar también las credenciales en sesión
$_SESSION['quibu_pago_credentials'] = [
    'api_key'       => $apiKey,
    'commerce_code' => $commerceCode
];

// Preparar transacción
$buy_order = 'quibu_' . time();
$session_id = session_id();
$return_url = 'https://quibu.cl/api/respuesta-pago.php';

try {
    $transaction = new Transaction($options);
    $response = $transaction->create(
        $buy_order,
        $session_id,
        $total,
        $return_url
    );

    header("Location: " . $response->getUrl() . "?token_ws=" . $response->getToken());
    exit;

} catch (Exception $e) {
    echo '<div style="padding:2em;color:red;">Error al iniciar el pago: ' . $e->getMessage() . '</div>';
    exit;
}
