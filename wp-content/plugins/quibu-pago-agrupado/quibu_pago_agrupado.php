<?php
/**
 * Plugin Name: Quibu - Pago Agrupado
 * Description: Plugin para mostrar cuotas pagadas, registro si no existe, y selección de cuotas a pagar con fee dinámico.
 * Version: 1.4
 * Author: Quibu
 */

if (!defined('ABSPATH')) exit;

add_shortcode('quibu_pago_agrupado', 'quibu_pago_agrupado_shortcode');

function quibu_pago_agrupado_shortcode() {
    ob_start();

    global $wpdb;
    $tabla_pagadores = $wpdb->prefix . 'pagadores';
    $tabla_cuotas = $wpdb->prefix . 'cuotas_definidas';
    $tabla_pagos = $wpdb->prefix . 'pagos_cuotas';

    $grupo_id = isset($_GET['grupo']) ? intval($_GET['grupo']) : 0;

    if (!$grupo_id) {
        echo '<div style="color:red;">Falta el identificador del grupo (?grupo=ID en la URL).</div>';
        return ob_get_clean();
    }

$tabla_grupos = $wpdb->prefix . 'grupos_cobranza';
$tabla_usuarios = $wpdb->prefix . 'usuarios_app';

$grupo_info = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $tabla_grupos WHERE id = %d", $grupo_id)
);

$nombre_grupo = '';
$tesorero_nombre = '';

if ($grupo_info) {
    $nombre_grupo = $grupo_info->nombre_grupo;
    $tesorero = $wpdb->get_row(
        $wpdb->prepare("SELECT nombre FROM $tabla_usuarios WHERE id = %d", $grupo_info->id_usuario)
    );
    if ($tesorero) {
        $tesorero_nombre = $tesorero->nombre;
    }
}

    $rut = isset($_POST['rut']) ? sanitize_text_field($_POST['rut']) : '';
    $nombre = isset($_POST['nombre']) ? sanitize_text_field($_POST['nombre']) : '';
    $correo = isset($_POST['correo']) ? sanitize_email($_POST['correo']) : '';
    $telefono = isset($_POST['telefono']) ? sanitize_text_field($_POST['telefono']) : '';

    // Si se envió RUT, verificar si está registrado
    if ($rut) {
        $existe_pagador = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $tabla_pagadores WHERE rut = %s AND id_grupo = %d", $rut, $grupo_id)
        );

        // Si no existe, mostrar formulario para registrar nombre, correo, teléfono
        if (!$existe_pagador && $nombre && $correo && $telefono) {
            $wpdb->insert($tabla_pagadores, [
                'rut' => $rut,
                'nombre' => $nombre,
                'email' => $correo,
                'telefono' => $telefono,
                'id_grupo' => $grupo_id
            ]);
        } elseif (!$existe_pagador) {
            echo '<form method="post" id="registro-form">
                <input type="hidden" name="grupo_id" value="' . esc_attr($grupo_id) . '">
                <input type="hidden" name="rut" value="' . esc_attr($rut) . '">
                <p><strong>Faltan algunos datos. Regístrate para continuar:</strong></p>
                <input type="text" name="nombre" placeholder="Nombre completo" required style="width:100%; padding:8px; margin-bottom:8px;"><br>
                <input type="email" name="correo" placeholder="Correo electrónico" required style="width:100%; padding:8px; margin-bottom:8px;"><br>
                <input type="text" name="telefono" placeholder="Teléfono" required style="width:100%; padding:8px; margin-bottom:8px;"><br>
                <button type="submit" style="background:#553BFF;color:#fff;padding:10px 20px;border:none;border-radius:6px;">Registrar y continuar</button>
              </form>';
            return ob_get_clean();
        }
    }

    // Si no hay RUT, pedirlo
    if (!$rut) {
    

        echo '<form method="post" id="rut-form">
            <input type="hidden" name="grupo_id" value="' . esc_attr($grupo_id) . '">
            <input type="text" id="rut-input" name="rut" placeholder="Ingresa tu RUT" required style="width: 100%; padding: 10px; border-radius: 6px;">
            <div id="rut-error" style="color:red;display:none;margin-top:6px;">RUT inválido</div>
            <button type="submit" style="margin-top: 10px; background-color:#553BFF;color:white;padding:10px 20px;border-radius:6px;">Continuar</button>
        </form>';
        echo '<script>
        function validarRut(rut) {
            rut = rut.replace(/^0+|\.|-/g, "");
            if (rut.length < 2) return false;
            let cuerpo = rut.slice(0, -1);
            let dv = rut.slice(-1).toUpperCase();
            let suma = 0, multiplo = 2;
            for (let i = cuerpo.length - 1; i >= 0; i--) {
                suma += parseInt(cuerpo.charAt(i)) * multiplo;
                multiplo = multiplo < 7 ? multiplo + 1 : 2;
            }
            let dvEsperado = 11 - (suma % 11);
            dvEsperado = dvEsperado === 11 ? "0" : dvEsperado === 10 ? "K" : dvEsperado.toString();
            return dv === dvEsperado;
        }

        document.getElementById("rut-form").addEventListener("submit", function(e) {
            const rutInput = document.getElementById("rut-input");
            let rut = rutInput.value.replace(/\./g, "").replace(/-/g, "");
            rut = rut.slice(0, -1) + "-" + rut.slice(-1);
            rutInput.value = rut;
            if (!validarRut(rut)) {
                e.preventDefault();
                document.getElementById("rut-error").style.display = "block";
            } else {
                document.getElementById("rut-error").style.display = "none";
            }
        });
        </script>';
        return ob_get_clean();
    }

    // Recuperar valor cuota
    $valor_cuota = (float) $wpdb->get_var(
        $wpdb->prepare("SELECT valor FROM $tabla_cuotas WHERE id_grupo = %d ORDER BY fecha_cuota ASC LIMIT 1", $grupo_id)
    );

    if (!$valor_cuota) {
        echo '<p>No hay cuotas definidas para este grupo.</p>';
        return ob_get_clean();
    }

    $cuotas_totales = $wpdb->get_results(
        $wpdb->prepare("SELECT id, fecha_cuota FROM $tabla_cuotas WHERE id_grupo = %d ORDER BY fecha_cuota ASC", $grupo_id)
    );

    $cuotas_pagadas = $wpdb->get_col(
        $wpdb->prepare("SELECT id_cuota FROM $tabla_pagos WHERE rut = %s", $rut)
    );

    $cantidad_pagadas = count($cuotas_pagadas);
    $cuotas_pendientes = count($cuotas_totales) - $cantidad_pagadas;

    // Definir fee
    if ($valor_cuota <= 5000) {
        $fee_base = 200; $fee_prog = 0.005;
    } elseif ($valor_cuota <= 8000) {
        $fee_base = 220; $fee_prog = 0.006;
    } elseif ($valor_cuota <= 10000) {
        $fee_base = 230; $fee_prog = 0.008;
    } elseif ($valor_cuota <= 20000) {
        $fee_base = 260; $fee_prog = 0.01;
    } elseif ($valor_cuota <= 30000) {
        $fee_base = 300; $fee_prog = 0.01;
    } else {
        $fee_base = 300; $fee_prog = 0.01;
    }

    ?>
    <div style="max-width: 500px; margin: 0 auto;">
        <div style="margin-bottom: 20px; padding: 15px; background-color: #e6ffed; border: 1px solid #b5e3c5; border-radius: 8px;">
            ✅ Has pagado <?php echo $cantidad_pagadas; ?> de <?php echo count($cuotas_totales); ?> cuotas.
        </div>

        <table style="width:100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th style="padding: 8px; border: 1px solid #ccc;">Fecha</th>
                    <th style="padding: 8px; border: 1px solid #ccc;">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cuotas_totales as $cuota): ?>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ccc; text-align: center;">
                            <?php echo date('d-m-Y', strtotime($cuota->fecha_cuota)); ?>
                        </td>
                        <td style="padding: 8px; border: 1px solid #ccc; text-align: center;">
                            <?php echo in_array($cuota->id, $cuotas_pagadas) ? '✅ Pagada' : '❌ Pendiente'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php if ($nombre_grupo && $tesorero_nombre): ?>
    <div style="margin-bottom: 10px;">
        <strong>Grupo:</strong> <?php echo esc_html($nombre_grupo); ?><br>
        <strong>Tesorero:</strong> <?php echo esc_html($tesorero_nombre); ?>
    </div>
<?php endif; ?>

        <div style="margin-bottom: 10px;">
            <strong>Valor base por cuota:</strong> $<?php echo number_format($valor_cuota, 0, ',', '.'); ?>
        </div>

        <form method="post" id="form-cuota" action="<?php echo plugin_dir_url(__FILE__) . 'api/procesar_pago.php'; ?>">
            <input type="hidden" name="grupo_id" value="<?php echo esc_attr($grupo_id); ?>">
            <input type="hidden" name="rut" value="<?php echo esc_attr($rut); ?>">

            <label for="cuotas_a_pagar"><strong>¿Cuántas cuotas quieres pagar?</strong></label>
            <select name="cuotas_a_pagar" id="cuotas_a_pagar" style="width:100%; padding:10px; border-radius:6px; margin-top:6px;">
                <?php for ($i = 1; $i <= $cuotas_pendientes; $i++): ?>
                    <option value="<?php echo $i; ?>"><?php echo $i; ?> cuota(s)</option>
                <?php endfor; ?>
            </select>
			<input type="hidden" id="total_a_pagar" name="total_a_pagar" value="">
            <div id="resultado" style="margin-top: 15px; font-weight: bold;"></div>

            <button type="submit" style="margin-top: 20px; background-color: #00B894; color: white; padding: 12px 20px; border: none; border-radius: 6px; width: 100%; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">
                Pagar ahora
            </button>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const dropdown = document.getElementById('cuotas_a_pagar');
        const resultado = document.getElementById('resultado');

        function calcularTotal() {
            const cuotas = parseInt(dropdown.value);
            const valor_cuota = <?php echo $valor_cuota; ?>;
            const fee_base = <?php echo $fee_base; ?>;
            const fee_prog = <?php echo $fee_prog; ?>;

            const total_cuotas = valor_cuota * cuotas;
            const fee_progresivo = total_cuotas * fee_prog * cuotas;
            const total_fee = fee_base + fee_progresivo;
            const total_final = total_cuotas + total_fee;
        
       		document.getElementById('total_a_pagar').value = total_final;


            resultado.innerHTML = `Subtotal cuotas: $${total_cuotas.toLocaleString('es-CL')}<br>Costo Servicio Automatizado Quibu: $${Math.round(total_fee).toLocaleString('es-CL')}<br><strong>Total a pagar: $${Math.round(total_final).toLocaleString('es-CL')}</strong>`;
        }

        dropdown.addEventListener('change', calcularTotal);
        calcularTotal();
    });
    </script>
    <?php

    return ob_get_clean();
}
