<?php
$dsn = "mysql:host=localhost;dbname=leantime;port=3306";
$user = "leantimeuser";
$pass = "Leantime123!";

try {
    $pdo = new PDO($dsn, $user, $pass);
    echo "Conexión OK";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
