<?php
declare(strict_types=1);

/**
 * phpMyAdmin configuration
 */

// Necesario para las cookies (32 caracteres aleatorios)
$cfg['blowfish_secret'] = 'aP9fT7zLmN2qR8kJ4xWcY6sV0uHbX3pD'; 

/* Servidores */
$i = 0;
$i++;

/* Autenticación */
$cfg['Servers'][$i]['auth_type'] = 'cookie';

/* Parámetros del servidor MySQL */
$cfg['Servers'][$i]['host'] = '127.0.0.1';  // Conecta al MySQL de WSL
$cfg['Servers'][$i]['port'] = '3306';
$cfg['Servers'][$i]['compress'] = false;
$cfg['Servers'][$i]['AllowNoPassword'] = false;

/* Configuración de almacenamiento (opcional) */
//$cfg['Servers'][$i]['controluser'] = 'pma';
//$cfg['Servers'][$i]['controlpass'] = 'pmapass';

/* Directorios de subida/descarga */
$cfg['UploadDir'] = '';
$cfg['SaveDir'] = '';
