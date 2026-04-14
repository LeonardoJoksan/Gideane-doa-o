<?php
// conexao.php
$host = 'localhost';
$dbname = 'gideane_gi';
$user = 'gideane_gi'; // Altere para o seu usuário do MySQL
$pass = 'JUN33saur!@#';     // Altere para a sua senha do MySQL

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    // Configura o PDO para lançar exceções em caso de erros
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}
?>