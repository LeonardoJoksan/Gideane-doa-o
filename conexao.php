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

    // Cria a tabela de vídeos do médico caso não exista
    $sql = "CREATE TABLE IF NOT EXISTS videos_medico (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        video_id VARCHAR(50) NOT NULL,
        data_adicionado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);

} catch (PDOException $e) {
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}
?>