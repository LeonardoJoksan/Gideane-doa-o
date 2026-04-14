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
    try { $pdo->exec("ALTER TABLE usuarios_online ADD COLUMN ip_address VARCHAR(45) AFTER ultimo_acesso"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE usuarios_online ADD COLUMN user_agent VARCHAR(255) AFTER ip_address"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE usuarios_online ADD COLUMN cidade VARCHAR(100) AFTER user_agent"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE usuarios_online ADD COLUMN estado VARCHAR(50) AFTER cidade"); } catch (PDOException $e) {}

    // Cria a tabela de histórico de acessos (permanente)
    $sql_historico = "CREATE TABLE IF NOT EXISTS historico_acessos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sessao_id VARCHAR(100) UNIQUE NOT NULL,
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        cidade VARCHAR(100),
        estado VARCHAR(50),
        data_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql_historico);

    // Tenta atualizar a tabela histórico caso seja antiga
    try { $pdo->exec("ALTER TABLE historico_acessos ADD COLUMN cidade VARCHAR(100) AFTER user_agent"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE historico_acessos ADD COLUMN estado VARCHAR(50) AFTER cidade"); } catch (PDOException $e) {}

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

        // Verifica se a sessão já existe no histórico para evitar chamar a API a cada reload
        $stmtCheck = $pdo->prepare("SELECT cidade, estado FROM historico_acessos WHERE sessao_id = :sessao_id LIMIT 1");
        $stmtCheck->execute([':sessao_id' => $sessao_atual]);
        $sessaoExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $cidade = null;
        $estado = null;

        if ($sessaoExistente && $sessaoExistente['cidade'] !== null) {
            // Usa localização já gravada
            $cidade = $sessaoExistente['cidade'];
            $estado = $sessaoExistente['estado'];
        } else {
            // Tenta obter localização nova se o IP for público e válido
            if (filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                // Endpoint simples e gratuito (limitado a ~45 req/minuto)
                $json = @file_get_contents("http://ip-api.com/json/{$ip_address}?fields=city,region,status");
                if ($json) {
                    $geoInfo = json_decode($json, true);
                    if (isset($geoInfo['status']) && $geoInfo['status'] === 'success') {
                        $cidade = $geoInfo['city'] ?? 'Desconhecida';
                        $estado = $geoInfo['region'] ?? 'N/A';
                    }
                }
            } else {
                $cidade = 'Local/Privado';
                $estado = 'N/A';
            }
        }

        // Insere ou atualiza o acesso da sessão atual (Online)
        $stmtOnline = $pdo->prepare("INSERT INTO usuarios_online (sessao_id, ultimo_acesso, ip_address, user_agent, cidade, estado) VALUES (:sessao_id, NOW(), :ip, :ua, :cid, :est) ON DUPLICATE KEY UPDATE ultimo_acesso = NOW(), ip_address = :ip_up, user_agent = :ua_up, cidade = :cid_up, estado = :est_up");

        $ua_truncado = substr($user_agent, 0, 255);

        $stmtOnline->execute([
            ':sessao_id' => $sessao_atual,
            ':ip' => $ip_address,
            ':ua' => $ua_truncado,
            ':cid' => $cidade,
            ':est' => $estado,
            ':ip_up' => $ip_address,
            ':ua_up' => $ua_truncado,
            ':cid_up' => $cidade,
            ':est_up' => $estado
        ]);

        // Registra o acesso histórico (ignora se a sessão já foi inserida)
        $stmtHistorico = $pdo->prepare("INSERT IGNORE INTO historico_acessos (sessao_id, ip_address, user_agent, cidade, estado) VALUES (:sessao_id, :ip, :ua, :cid, :est)");
        $stmtHistorico->execute([
            ':sessao_id' => $sessao_atual,
            ':ip' => $ip_address,
            ':ua' => $ua_truncado,
            ':cid' => $cidade,
            ':est' => $estado
        ]);

    } catch (PDOException $e) {
        // Ignora silenciosamente erros relacionados à tabela de usuários online no frontend
    }
}
?>