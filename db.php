<?php
$host = '127.0.0.1';
$db   = 'sql_samuelgarcia';
$user = 'sql_samuelgarcia';
$pass = 'e7c8b01c5e1958';  
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  echo "Erro ao conectar ao banco: " . $e->getMessage();
  exit;
}
