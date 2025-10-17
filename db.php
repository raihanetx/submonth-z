<?php
$db_host = 'localhost';
$db_name = 'u802637580_submont';
$db_user = 'u802637580_submonthmysql';
$db_pass = 'submontH2:)';
$charset = 'utf8mb4';
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";
$options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, ];
try { $pdo = new PDO($dsn, $db_user, $db_pass, $options); } catch (\PDOException $e) { die("Database connection failed: " . $e->getMessage()); }
?>