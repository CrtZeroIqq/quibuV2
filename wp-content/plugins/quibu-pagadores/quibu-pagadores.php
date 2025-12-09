<?php
/**
 * Plugin Name: Quibu - Registro de Pagadores
 * Description: Plugin para gestionar el acceso de pagadores usando shortcode y RUT.
 * Version: 1.2
 * Author: Quibu
 */

if (!defined('ABSPATH')) exit;

add_shortcode('quibu_pagador', 'quibu_pagador_shortcode');

function quibu_pagador_shortcode() {
    ob_start();

    global $wpdb;
    $tabla = $wpdb->prefix . 'pagadores';

    $grupo_id = isset($_GET['grupo']) ? intval($_GET['grupo']) : 0;

    if (!$grupo_id) {
        echo '<div style="color:red; font-weight:bold;">Falta el identificador del grupo (?grupo=ID en la URL).</div>';
        return ob_get_clean();
    }

    $rut = '';
    $pagadorExiste = false;

    if (isset($_POST['registrar_pagador'])) {
        $rut = sanitize_text_field($_POST['rut']);
        $nombre = sanitize_text_field($_POST['nombre']);
        $email = sanitize_email($_POST['email']);
        $telefono = sanitize_text_field($_POST['telefono']);
        $grupo_id = intval($_POST['grupo_id']);

        $insertado = $wpdb->insert(
            $tabla,
            [
                'id_grupo' => $grupo_id,
                'rut' => $rut,
                'nombre' => $nombre,
                'email' => $email,
                'telefono' => $telefono,
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );

        if ($insertado !== false) {
            echo '<div style="margin-top: 1.5em; padding: 1em; background: #e6ffed; border: 1px solid #b5e3c5; border-radius: 8px;">
                    <strong>✅ Registro exitoso:</strong> Ya puedes revisar tus cuotas.
                  </div>';
            $pagadorExiste = true;
        } else {
            echo '<div style="margin-top: 1.5em; padding: 1em; background: #ffe6e6; border: 1px solid #ff8f8f; border-radius: 8px;">
                    <strong>❌ Error:</strong> No se pudo registrar. Intenta nuevamente.
                  </div>';
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['rut'])) {
        $rut = sanitize_text_field($_POST['rut']);
        $grupo_id = intval($_POST['grupo_id']);

        $existe = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tabla WHERE rut = %s AND id_grupo = %d",
            $rut, $grupo_id
        ));

        $pagadorExiste = ($existe > 0);
    }

    ?>
    <style>
    .quibu-wrapper {
        padding: 1rem;
        font-family: system-ui, sans-serif;
        width: 100%;
        box-sizing: border-box;
    }
    .quibu-responsive-table {
        width: 100%;
        border-radius: 12px;
        background-color: #fff;
        border-collapse: collapse;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        font-size: 14px;
    }
    .quibu-responsive-table thead { display: none; }
    .quibu-responsive-table tr {
        display: block;
        border-bottom: 1px solid #eee;
        padding: 10px;
    }
    .quibu-responsive-table td {
        display: flex;
        justify-content: space-between;
        padding: 8px 12px;
        border: none;
    }
    .quibu-responsive-table td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #666;
        width: 50%;
        text-align: left;
    }
    .quibu-total {
        font-size: 16px;
        font-weight: bold;
        text-align: right;
        margin-top: 1rem;
        color: #333;
    }
    .quibu-button {
        background-color: #553BFF;
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 10px;
        font-size: 16px;
        cursor: pointer;
        width: 100%;
        margin-top: 1rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .quibu-button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    </style>

    <div style="max-width: 420px; margin: 0 auto; padding: 2em; font-family: system-ui, sans-serif;">
        <?php if (!$rut || !$pagadorExiste): ?>
            <h2 style="text-align: center; font-size: 1.4em; margin-bottom: 1.2em;">
                <?php echo !$rut ? 'Ingresa tu RUT' : '⚠️ No estás registrado: completa tus datos'; ?>
            </h2>
            <form method="post">
                <input type="hidden" name="grupo_id" value="<?php echo esc_attr($grupo_id); ?>">
                <?php if (!$rut): ?>
                    <input type="text" name="rut" placeholder="Ej: 12345678-9" style="width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; margin-bottom: 1em; font-size: 1em;" required>
                    <button type="submit" style="background-color: #553BFF; color: white; border: none; padding: 12px 20px; border-radius: 8px; width: 100%; font-size: 1em; cursor: pointer;">Continuar</button>
                <?php else: ?>
                    <input type="hidden" name="rut" value="<?php echo esc_attr($rut); ?>">
                    <input type="text" name="nombre" placeholder="Nombre completo" required style="width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 6px; border: 1px solid #ccc;">
                    <input type="email" name="email" placeholder="Correo electrónico" required style="width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 6px; border: 1px solid #ccc;">
                    <input type="text" name="telefono" placeholder="Teléfono" required style="width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 6px; border: 1px solid #ccc;">
                    <button type="submit" name="registrar_pagador" style="background-color: #00B894; color: white; border: none; padding: 12px 20px; border-radius: 8px; width: 100%; font-size: 1em;">Registrarme</button>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div style="margin-top: 1.5em; padding: 1em; background: #e6ffed; border: 1px solid #b5e3c5; border-radius: 8px;">
                <strong>✅ Bienvenido:</strong> Mostrando tus cuotas...
            </div>
            <?php quibu_mostrar_cuotas($rut, $grupo_id); ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function quibu_mostrar_cuotas($rut, $grupo_id) {
    global $wpdb;
    $tabla_cuotas = $wpdb->prefix . 'cuotas_definidas';
    $tabla_pagos  = $wpdb->prefix . 'pagos_cuotas';

    // Traemos todas las cuotas del grupo ordenadas por fecha
    $cuotas = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $tabla_cuotas WHERE id_grupo = %d ORDER BY fecha_cuota ASC", $grupo_id)
    );

    // Traemos las cuotas que ya están pagadas por este RUT
    $cuotas_pagadas = $wpdb->get_col(
        $wpdb->prepare("SELECT id_cuota FROM $tabla_pagos WHERE rut = %s", $rut)
    );

    if (empty($cuotas)) {
        echo '<p>No hay cuotas definidas para este grupo.</p>';
        return;
    }

    ?>
    <div class="quibu-wrapper">
        <form id="form-cuotas" method="post" action="<?php echo plugin_dir_url(__FILE__); ?>api/procesar_pago.php">
            <input type="hidden" name="rut" value="<?php echo esc_attr($rut); ?>">
            <input type="hidden" name="grupo_id" value="<?php echo esc_attr($grupo_id); ?>">

            <table class="quibu-responsive-table">
                <tbody>
                <?php
                $cuotas_mostradas = 0;
                foreach ($cuotas as $cuota):
                    if (in_array($cuota->id, $cuotas_pagadas)) {
                        continue; // Saltar las ya pagadas
                    }

                    $cuotas_mostradas++;

                    /*
                     * En la tabla 'cuotas_definidas' cada fila representa UNA cuota.
                     * Por lo tanto, $cuota->valor es el valor base de esa cuota (sin fee).
                     */
                    $valor_cuota_inicial = (float) $cuota->valor;

                    // --- Determinar tramo de fee base y fee progresivo ---
                    if ($valor_cuota_inicial >= 1000 && $valor_cuota_inicial <= 5000) {
                        $fee_base = 200; $fee_prog = 0.005; // 0,50 %
                    } elseif ($valor_cuota_inicial >= 5100 && $valor_cuota_inicial <= 8000) {
                        $fee_base = 220; $fee_prog = 0.006; // 0,60 %
                    } elseif ($valor_cuota_inicial >= 8100 && $valor_cuota_inicial <= 10000) {
                        $fee_base = 230; $fee_prog = 0.008; // 0,80 %
                    } elseif ($valor_cuota_inicial >= 10100 && $valor_cuota_inicial <= 20000) {
                        $fee_base = 260; $fee_prog = 0.01;  // 1 %
                    } elseif ($valor_cuota_inicial >= 20100 && $valor_cuota_inicial <= 30000) {
                        $fee_base = 300; $fee_prog = 0.01;  // 1 %
                    } else {
                        // Fuera de rango: aplicar tramo más alto como fallback
                        $fee_base = 300; $fee_prog = 0.01;
                    }

                    // --- Cálculo del fee por esta cuota ---
                    $fee_progresivo = $valor_cuota_inicial * $fee_prog;  // 0,5 % a 1 % del valor cuota
                    $fee_total      = $fee_base + $fee_progresivo;       // Fee base + progresivo
                    $valor_con_fee  = $valor_cuota_inicial + $fee_total; // Monto final que se cobrará por esta cuota
                ?>
                    <tr>
                        <td data-label="Cuota">Cuota <?php echo esc_html($cuota->nro_cuota); ?></td>
                        <td data-label="Monto + Fee">$
                            <?php echo number_format($valor_cuota_inicial, 0, ',', '.'); ?>
                        </td>
                        <td data-label="Vencimiento">
                            <?php echo date('d-m-Y', strtotime($cuota->fecha_cuota)); ?>
                        </td>
                        <td data-label="Seleccionar">
                            <input type="checkbox"
                                   class="cuota-checkbox"
                                   name="cuotas[]"
                                   value="<?php echo $cuota->id; ?>"
                                   data-monto="<?php echo round($valor_con_fee); ?>"
                                   style="width: 20px; height: 20px;">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($cuotas_mostradas === 0): ?>
                <p style="text-align: center; margin-top: 1rem;">🎉 No tienes cuotas pendientes por pagar.</p>
            <?php else: ?>
                <div class="quibu-total">
                    Total a pagar: $<span id="total-pagar">0</span>
                </div>
                <button type="submit" class="quibu-button" disabled>
                    Pagar cuotas seleccionadas
                </button>
            <?php endif; ?>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkboxes = document.querySelectorAll('.cuota-checkbox');
        const totalSpan  = document.getElementById('total-pagar');
        const btnPagar   = document.querySelector('form#form-cuotas button[type="submit"]');

        function actualizarTotal() {
            let total = 0;
            let seleccionadas = 0;
            checkboxes.forEach(cb => {
                if (cb.checked) {
                    total += parseInt(cb.dataset.monto, 10);
                    seleccionadas++;
                }
            });
            totalSpan.textContent = total.toLocaleString('es-CL');
            btnPagar.disabled    = seleccionadas === 0;
        }

        checkboxes.forEach(cb => cb.addEventListener('change', actualizarTotal));
        actualizarTotal();
    });
    </script>
    <?php
}
