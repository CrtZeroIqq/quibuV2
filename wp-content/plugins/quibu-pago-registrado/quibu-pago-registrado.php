<?php
/**
 * Plugin Name: Quibu - Pago Directo Registrados
 * Description: Permite a pagadores ya registrados ver su grupo y pagar cuotas directamente.
 * Version: 1.0
 * Author: Quibu
 */

if (!defined('ABSPATH')) exit;

add_shortcode('quibu_pago_registrado', 'quibu_pago_registrado_shortcode');

function quibu_pago_registrado_shortcode() {
    ob_start();

    global $wpdb;
    $tabla_pagadores = $wpdb->prefix . 'pagadores';
    $tabla_cuotas = $wpdb->prefix . 'cuotas_definidas';
    $tabla_pagos = $wpdb->prefix . 'pagos_cuotas';

    $rut = isset($_POST['rut']) ? sanitize_text_field($_POST['rut']) : '';

    if (!$rut) {
        echo '<form method="post" id="rut-form">
            <input type="text" id="rut-input" name="rut" placeholder="Ingresa tu RUT" required style="width: 100%; padding: 10px; border-radius: 6px;">
            <div id="rut-error" style="color:red;display:none;margin-top:6px;">RUT inválido</div>
            <button type="submit" style="margin-top: 10px; background-color:#553BFF;color:white;padding:10px 20px;border-radius:6px;">Continuar</button>
        </form>
        <script>
        function validarRut(rut) {
            rut = rut.replace(/^0+|\\.|-/g, "");
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
            let rut = rutInput.value.replace(/\\./g, "").replace(/-/g, "");
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

    $pagador = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla_pagadores WHERE rut = %s", $rut));

    if (!$pagador) {
        echo "<div style='color:red;'>⚠️ Este RUT no está registrado. Para pagar, necesitas el link con ?grupo=ID proporcionado por el tesorero.</div>";
        return ob_get_clean();
    }

    // Reutilizamos la lógica del plugin anterior
    $_GET['grupo'] = $pagador->id_grupo;
    $_POST['rut'] = $rut;
    $_POST['nombre'] = $pagador->nombre;
    $_POST['correo'] = $pagador->correo;
    $_POST['telefono'] = $pagador->telefono;

    // Llamar al shortcode del plugin principal (si está activo)
    if (function_exists('quibu_pago_agrupado_shortcode')) {
        return quibu_pago_agrupado_shortcode();
    } else {
        return '<p>Error: Plugin "Quibu - Pago Agrupado" no está activo.</p>';
    }
}
