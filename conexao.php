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

    // Cria a tabela de usuários online caso não exista
    $sql_online = "CREATE TABLE IF NOT EXISTS usuarios_online (
        sessao_id VARCHAR(100) PRIMARY KEY,
        ultimo_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql_online);

} catch (PDOException $e) {
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}

// Inicia a sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Lógica de Usuários Online
$sessao_atual = session_id();

if (!empty($sessao_atual)) {
    try {
        // Remove usuários inativos há mais de 5 minutos (300 segundos)
        $pdo->exec("DELETE FROM usuarios_online WHERE ultimo_acesso < (NOW() - INTERVAL 5 MINUTE)");

        // Insere ou atualiza o acesso da sessão atual
        $stmtOnline = $pdo->prepare("INSERT INTO usuarios_online (sessao_id, ultimo_acesso) VALUES (:sessao_id, NOW()) ON DUPLICATE KEY UPDATE ultimo_acesso = NOW()");
        $stmtOnline->execute([':sessao_id' => $sessao_atual]);
    } catch (PDOException $e) {
        // Ignora silenciosamente erros relacionados à tabela de usuários online no frontend
    }
}
?>