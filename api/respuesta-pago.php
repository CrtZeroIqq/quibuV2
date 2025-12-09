<?php
require_once dirname(__FILE__, 2) . '/wp-load.php';
require_once '/var/www/html/vendor/autoload.php';

use Transbank\Webpay\WebpayPlus\Transaction;
use Transbank\Webpay\Options;

session_start();

// Token desde POST o GET
$token = $_POST['token_ws'] ?? $_GET['token_ws'] ?? null;

if (!$token) {
    echo "❌ Token no recibido.";
    exit;
}

// Recuperar credenciales guardadas en sesión
if (!isset($_SESSION['quibu_pago_credentials'])) {
    echo "❌ Credenciales de pago no encontradas.";
    exit;
}

$creds = $_SESSION['quibu_pago_credentials'];

$options = new Options(
    $creds['api_key'],
    $creds['commerce_code'],
    Options::ENVIRONMENT_INTEGRATION
);

$transaction = new Transaction($options);

function mostrar_respuesta($titulo, $contenidoHTML, $color = '#4CAF50', $icono = '✅') {
    echo "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <title>$titulo</title>
        <style>
            body {
                font-family: 'Segoe UI', sans-serif;
                background-color: #f9f9f9;
                color: #333;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                margin: 0;
            }

            .respuesta-box {
                background: #fff;
                border-radius: 10px;
                padding: 30px 40px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                text-align: center;
                max-width: 500px;
            }

            .respuesta-box .icon {
                font-size: 48px;
                color: $color;
            }

            .respuesta-box h2 {
                color: $color;
                margin: 10px 0;
            }

            .respuesta-box p {
                margin: 5px 0;
                font-size: 16px;
            }

            .respuesta-box a {
                display: inline-block;
                margin-top: 20px;
                background-color: $color;
                color: #fff;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 6px;
                font-weight: bold;
            }

            .respuesta-box a:hover {
                opacity: 0.9;
            }

            pre {
                text-align: left;
                background-color: #f3f3f3;
                padding: 10px;
                border-radius: 6px;
                overflow-x: auto;
            }
        </style>
    </head>
    <body>
        <div class='respuesta-box'>
            <div class='icon'>$icono</div>
            <h2>$titulo</h2>
            $contenidoHTML
            <a href='https://www.quibu.cl/pago-landing'>Volver al inicio</a>
        </div>
    </body>
    </html>";
}

try {
    $response = $transaction->commit($token);

    if ($response->getResponseCode() === 0) {
        // Éxito
        $buy_order         = $response->getBuyOrder();
        $authorization_code = $response->getAuthorizationCode();
        $amount            = $response->getAmount();
        $payment_type      = $response->getPaymentTypeCode();
        $transaction_date  = $response->getTransactionDate();
        $transbank_token   = $token;

        if (!isset($_SESSION['quibu_pago'])) {
            mostrar_respuesta("❌ Sesión expirada", "<p>No pudimos recuperar los datos de la transacción.</p>", "#f44336", "⚠️");
            exit;
        }

        $datos     = $_SESSION['quibu_pago'];
        $cuotas    = $datos['cuotas'];
        $rut       = sanitize_text_field($datos['rut']);
        $grupo_id  = intval($datos['grupo_id']);

        global $wpdb;
        $tabla_pagos     = $wpdb->prefix . 'pagos_cuotas';
        $tabla_pagadores = $wpdb->prefix . 'pagadores';
        $tabla_cuotas    = $wpdb->prefix . 'cuotas_definidas';

        $id_pagador = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $tabla_pagadores WHERE rut = %s AND id_grupo = %d", $rut, $grupo_id)
        );

        // Calcular monto pagado por cada cuota (división equitativa del total pagado)
$cantidad_cuotas = count($cuotas);
$monto_individual = round($amount / $cantidad_cuotas); // aseguramos enteros redondeando

foreach ($cuotas as $id_cuota) {
    $wpdb->insert(
        $tabla_pagos,
        [
            'id_pagador'     => $id_pagador,
            'rut'            => $rut,
            'id_cuota'       => $id_cuota,
            'monto_pagado'   => $monto_individual,
            'id_transaccion' => $transbank_token,
            'metodo_pago'    => $payment_type,
        ],
        ['%d', '%s', '%d', '%d', '%s', '%s']
    );
}


        // Limpiar sesión
        unset($_SESSION['quibu_pago']);
        unset($_SESSION['quibu_pago_credentials']);

        mostrar_respuesta(
            "✅ ¡Pago realizado exitosamente!",
            "<p><strong>Monto total:</strong> $" . number_format($amount, 0, ',', '.') . "</p>
             <p><strong>Orden de compra:</strong> $buy_order</p>
             <p><strong>Fecha:</strong> " . date('d-m-Y H:i:s', strtotime($transaction_date)) . "</p>"
        );

    } else {
        mostrar_respuesta(
            "❌ El pago fue rechazado",
            "<p>No pudimos procesar tu pago. Puede deberse a fondos insuficientes, error del banco o cancelación del proceso.</p>
             <p>Intenta nuevamente o contacta a soporte si el problema persiste.</p>",
            "#f44336",
            "❌"
        );
    }

} catch (Exception $e) {
    mostrar_respuesta(
        "⚠️ Error al procesar el pago",
        "<p>Ocurrió un error inesperado:</p><pre>" . $e->getMessage() . "</pre>",
        "#ff9800",
        "⚠️"
    );
}
