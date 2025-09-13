<?php
// test-connection.php
$host = 'srv1676.hstgr.io';
$port = 3306;
$dbname = 'u538986410_arcyantix';
$username = 'u538986410_acyantix';
$password = 'Password@Jayant123';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connection successful!\n";
    echo "Database: " . $dbname . "\n";
    echo "Host: " . $host . "\n";
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
}
?>