<?php
/**
 * Quibu - Procesar Pago
 * Inicia transacción con Transbank WebPay Plus
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Transbank\Webpay\WebpayPlus\Transaction;
use Transbank\Webpay\Options;

session_start();

// Limpiar sesión anterior
unset($_SESSION['quibu_pago']);
unset($_SESSION['quibu_pago_credentials']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pagar/');
    exit;
}

try {
    $pdo = getConnection();

    // Sanitizar inputs
    $rut = isset($_POST['rut']) ? strtoupper(trim($_POST['rut'])) : '';
    $grupo_id = isset($_POST['grupo_id']) ? intval($_POST['grupo_id']) : 0;
    $cuotas_seleccionadas = isset($_POST['cuotas']) ? array_map('intval', $_POST['cuotas']) : [];

    // Validaciones básicas
    if (!$rut || !$grupo_id || empty($cuotas_seleccionadas)) {
        die('Error: Faltan datos obligatorios.');
    }

    // Verificar que el pagador existe
    $stmt = $pdo->prepare("SELECT * FROM wp_pagadores WHERE rut = ? AND id_grupo = ?");
    $stmt->execute([$rut, $grupo_id]);
    $pagador = $stmt->fetch();

    if (!$pagador) {
        die('Error: Pagador no encontrado.');
    }

    // Obtener información de las cuotas seleccionadas
    $placeholders = implode(',', array_fill(0, count($cuotas_seleccionadas), '?'));
    $stmt = $pdo->prepare("
        SELECT * FROM wp_cuotas_definidas
        WHERE id IN ($placeholders) AND id_grupo = ?
    ");
    $stmt->execute(array_merge($cuotas_seleccionadas, [$grupo_id]));
    $cuotas = $stmt->fetchAll();

    if (count($cuotas) !== count($cuotas_seleccionadas)) {
        die('Error: Algunas cuotas seleccionadas no son válidas.');
    }

    // Verificar que ninguna cuota ya esté pagada
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM wp_pagos_cuotas
        WHERE rut = ? AND id_cuota IN ($placeholders)
    ");
    $stmt->execute(array_merge([$rut], $cuotas_seleccionadas));
    $cuotas_ya_pagadas = $stmt->fetch()['total'];

    if ($cuotas_ya_pagadas > 0) {
        die('Error: Algunas de las cuotas seleccionadas ya están pagadas.');
    }

    // Calcular total con fee
    $subtotal = 0;
    $fee_total = 0;

    foreach ($cuotas as $cuota) {
        $valor_cuota = floatval($cuota['valor']);
        $subtotal += $valor_cuota;

        // Determinar tramo de fee
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

        $fee_cuota = $fee_base + ($valor_cuota * $fee_prog);
        $fee_total += $fee_cuota;
    }

    $total = round($subtotal + $fee_total);

    // Validación: el total debe ser mayor a 50 pesos (mínimo Transbank)
    if ($total < 50) {
        die('Error: El monto a pagar debe ser mayor a $50.');
    }

    // Guardar datos en sesión para después del callback
    $_SESSION['quibu_pago'] = [
        'cuotas' => $cuotas_seleccionadas,
        'rut' => $rut,
        'grupo_id' => $grupo_id,
        'total' => $total,
        'subtotal' => $subtotal,
        'fee' => $fee_total
    ];

    // Credenciales Transbank (INTEGRACIÓN - cambiar a PRODUCTION en producción)
    $apiKey = '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C';
    $commerceCode = '597055555532';
    $environment = Options::ENVIRONMENT_INTEGRATION; // Cambiar a PRODUCTION cuando vayas a producción

    // Guardar credenciales en sesión
    $_SESSION['quibu_pago_credentials'] = [
        'api_key' => $apiKey,
        'commerce_code' => $commerceCode,
        'environment' => $environment
    ];

    $options = new Options($apiKey, $commerceCode, $environment);

    // Crear orden de compra única
    $buy_order = 'QUIBU_' . $grupo_id . '_' . time();
    $session_id = session_id();
    $return_url = 'https://www.quibu.cl/api/respuesta-pago.php';

    // Iniciar transacción con Transbank
    $transaction = new Transaction($options);
    $response = $transaction->create(
        $buy_order,
        $session_id,
        $total,
        $return_url
    );

    // Redirigir a WebPay
    $redirect_url = $response->getUrl() . '?token_ws=' . $response->getToken();
    header("Location: " . $redirect_url);
    exit;

} catch (PDOException $e) {
    error_log("Error DB en procesar_pago: " . $e->getMessage());
    die('Error de base de datos al procesar el pago.');
} catch (Exception $e) {
    error_log("Error en procesar_pago: " . $e->getMessage());
    die('Error al iniciar el pago: ' . htmlspecialchars($e->getMessage()));
}
