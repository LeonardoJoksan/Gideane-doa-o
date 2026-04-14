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
        ultimo_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        user_agent VARCHAR(255)
    )";
    $pdo->exec($sql_online);

    // Tenta atualizar a tabela caso seja antiga e não tenha as novas colunas
    try {
        $pdo->exec("ALTER TABLE usuarios_online ADD COLUMN ip_address VARCHAR(45) AFTER ultimo_acesso");
        $pdo->exec("ALTER TABLE usuarios_online ADD COLUMN user_agent VARCHAR(255) AFTER ip_address");
    } catch (PDOException $e) {
        // Ignora se a coluna já existir
    }

} catch (PDOException $e) {
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}

// Inicia a sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Lógica de Usuários Online (com filtro de bots)
$sessao_atual = session_id();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

// Array simples de palavras-chave comuns de bots
$bots = ['bot', 'spider', 'crawl', 'slurp', 'googlebot', 'bingbot', 'yandex', 'baidu', 'facebookexternalhit', 'whatsapp'];
$is_bot = false;

foreach ($bots as $bot) {
    if (stripos($user_agent, $bot) !== false) {
        $is_bot = true;
        break;
    }
}

if (!empty($sessao_atual) && !$is_bot) {
    try {
        // Remove usuários inativos há mais de 5 minutos (300 segundos)
        $pdo->exec("DELETE FROM usuarios_online WHERE ultimo_acesso < (NOW() - INTERVAL 5 MINUTE)");

        // Insere ou atualiza o acesso da sessão atual, agora com IP e User Agent
        // Usamos SUBSTRING no user_agent para evitar erro caso seja muito longo (max 255)
        $stmtOnline = $pdo->prepare("INSERT INTO usuarios_online (sessao_id, ultimo_acesso, ip_address, user_agent) VALUES (:sessao_id, NOW(), :ip, :ua) ON DUPLICATE KEY UPDATE ultimo_acesso = NOW(), ip_address = :ip_up, user_agent = :ua_up");

        $ua_truncado = substr($user_agent, 0, 255);

        $stmtOnline->execute([
            ':sessao_id' => $sessao_atual,
            ':ip' => $ip_address,
            ':ua' => $ua_truncado,
            ':ip_up' => $ip_address,
            ':ua_up' => $ua_truncado
        ]);
    } catch (PDOException $e) {
        // Ignora silenciosamente erros relacionados à tabela de usuários online no frontend
    }
}
?>