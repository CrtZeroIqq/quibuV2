<?php
require_once dirname(__FILE__, 2) . '/wp-load.php';
require_once dirname(__FILE__, 2) . '/vendor/autoload.php';


use Transbank\Webpay\WebpayPlus\Transaction;
use Transbank\Webpay\WebpayPlus\Options;
use Transbank\Webpay\WebpayPlus\Environment;

session_start();

// Validación básica
if (!isset($_POST['token_ws'])) {
    echo "Token no recibido.";
    exit;
}

$token = $_POST['token_ws'];

$options = new Options(
    defined('TRANSBANK_COMMERCE_CODE') ? TRANSBANK_COMMERCE_CODE : '597055555532', // código de pruebas por defecto
    defined('TRANSBANK_API_KEY') ? TRANSBANK_API_KEY : 'Xcbn2q8y1wGHMAJw9x8S8j2j1sQK8Zr5', // key de pruebas por defecto
    Environment::INTEGRATION
);

$transaction = new Transaction($options);

try {
    $response = $transaction->commit($token);

    if ($response->getResponseCode() === 0) {
        // Éxito
        $buy_order = $response->getBuyOrder();
        $authorization_code = $response->getAuthorizationCode();
        $amount = $response->getAmount();
        $payment_type = $response->getPaymentTypeCode();
        $transaction_date = $response->getTransactionDate();
        $transbank_token = $response->getToken();

        // Recuperar datos de la sesión original
        if (!isset($_SESSION['quibu_pago'])) {
            echo "Sesión expirada o datos perdidos.";
            exit;
        }

        $datos = $_SESSION['quibu_pago'];
        $cuotas = $datos['cuotas'];
        $rut = sanitize_text_field($datos['rut']);
        $grupo_id = intval($datos['grupo_id']);

        global $wpdb;
        $tabla_pagos = $wpdb->prefix . 'pagos_cuotas';
        $tabla_pagadores = $wpdb->prefix . 'pagadores';

        $id_pagador = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $tabla_pagadores WHERE rut = %s AND id_grupo = %d", $rut, $grupo_id)
        );

        foreach ($cuotas as $id_cuota) {
            $wpdb->insert(
                $tabla_pagos,
                [
                    'id_pagador'     => $id_pagador,
                    'rut'            => $rut,
                    'id_cuota'       => $id_cuota,
                    'monto_pagado'   => $amount,
                    'id_transaccion' => $transbank_token,
                    'metodo_pago'    => $payment_type,
                ],
                ['%d', '%s', '%d', '%d', '%s', '%s']
            );
        }

        unset($_SESSION['quibu_pago']);

        echo "<h2>✅ ¡Pago realizado exitosamente!</h2>";
        echo "<p>Monto: $" . number_format($amount, 0, ',', '.') . "</p>";
        echo "<p>Orden de compra: $buy_order</p>";
        echo "<p>Fecha: $transaction_date</p>";
        echo "<a href='" . home_url() . "'>Volver al inicio</a>";
    } else {
        echo "<h2>❌ El pago fue rechazado.</h2>";
        echo "<p>Intenta nuevamente.</p>";
    }

} catch (Exception $e) {
    echo "<h2>❌ Error al procesar el pago:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
