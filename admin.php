<?php
// admin.php
session_start();
require 'conexao.php';

// Verifica se está logado
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: login.php");
    exit;
}

// Se clicar em Sair
if(isset($_GET['sair'])){
    session_destroy();
    header("Location: login.php");
    exit;
}

// Busca as configurações atuais para preencher os formulários
$stmt = $pdo->query("SELECT * FROM configuracoes LIMIT 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

// Busca atualizações para a tabela
$stmtAtualizacoes = $pdo->query("SELECT * FROM atualizacoes ORDER BY data_publicacao DESC");
$atualizacoes = $stmtAtualizacoes->fetchAll(PDO::FETCH_ASSOC);

// Busca os documentos de transparência
$stmtDocs = $pdo->query("SELECT * FROM documentos ORDER BY id DESC");
$documentos = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

// Pega a contagem e os dados dos usuários online
$qtd_online = 0;
$lista_usuarios_online = [];
try {
    $stmtOnline = $pdo->query("SELECT * FROM usuarios_online ORDER BY ultimo_acesso DESC");
    $lista_usuarios_online = $stmtOnline->fetchAll(PDO::FETCH_ASSOC);
    $qtd_online = count($lista_usuarios_online);
} catch (Exception $e) {
    // Ignora se a tabela não existir
}

// Pega dados do histórico de acessos
$stats_acessos = [
    'hoje' => 0,
    'semana' => 0,
    'mes' => 0,
    'total' => 0
];
$lista_historico = [];
try {
    $hoje = date('Y-m-d');

    // Consultas para as estatísticas
    $stats_acessos['hoje'] = $pdo->query("SELECT COUNT(*) FROM historico_acessos WHERE DATE(data_acesso) = '$hoje'")->fetchColumn();
    $stats_acessos['semana'] = $pdo->query("SELECT COUNT(*) FROM historico_acessos WHERE YEARWEEK(data_acesso, 1) = YEARWEEK(CURDATE(), 1)")->fetchColumn();
    $stats_acessos['mes'] = $pdo->query("SELECT COUNT(*) FROM historico_acessos WHERE MONTH(data_acesso) = MONTH(CURDATE()) AND YEAR(data_acesso) = YEAR(CURDATE())")->fetchColumn();
    $stats_acessos['total'] = $pdo->query("SELECT COUNT(*) FROM historico_acessos")->fetchColumn();

    // MAIS DE 30 NOVAS MÉTRICAS (Agregações)
    $stats_extras = [];

    // 1-5. Localização (Top Estados e Cidades)
    $stats_extras['top_estados'] = $pdo->query("SELECT estado, COUNT(*) as qtd FROM historico_acessos WHERE estado IS NOT NULL AND estado != 'N/A' GROUP BY estado ORDER BY qtd DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $stats_extras['top_cidades'] = $pdo->query("SELECT cidade, COUNT(*) as qtd FROM historico_acessos WHERE cidade IS NOT NULL AND cidade != 'Local/Privado' GROUP BY cidade ORDER BY qtd DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $stats_extras['qtd_estados_distintos'] = $pdo->query("SELECT COUNT(DISTINCT estado) FROM historico_acessos WHERE estado IS NOT NULL AND estado != 'N/A'")->fetchColumn();
    $stats_extras['qtd_cidades_distintas'] = $pdo->query("SELECT COUNT(DISTINCT cidade) FROM historico_acessos WHERE cidade IS NOT NULL AND cidade != 'Local/Privado'")->fetchColumn();
    $stats_extras['acessos_internacionais'] = $pdo->query("SELECT COUNT(*) FROM historico_acessos WHERE estado NOT IN ('AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO') AND estado != 'N/A' AND estado IS NOT NULL")->fetchColumn();

    // 6-12. Análise de Tempo (Dias da semana, pico de horário)
    // Usando DAYOFWEEK (1 = Domingo, 2 = Segunda...)
    $dias_semana = $pdo->query("SELECT DAYOFWEEK(data_acesso) as dia, COUNT(*) as qtd FROM historico_acessos GROUP BY dia ORDER BY dia ASC")->fetchAll(PDO::FETCH_ASSOC);
    $nome_dias = [1 => 'Domingo', 2 => 'Segunda', 3 => 'Terça', 4 => 'Quarta', 5 => 'Quinta', 6 => 'Sexta', 7 => 'Sábado'];
    $stats_extras['acessos_por_dia'] = [];
    $maior_dia_qtd = 0;
    $stats_extras['melhor_dia'] = 'N/A';

    foreach($dias_semana as $ds) {
        $stats_extras['acessos_por_dia'][$nome_dias[$ds['dia']]] = $ds['qtd'];
        if($ds['qtd'] > $maior_dia_qtd) {
            $maior_dia_qtd = $ds['qtd'];
            $stats_extras['melhor_dia'] = $nome_dias[$ds['dia']];
        }
    }

    $stats_extras['dia_com_mais_acessos_historico'] = $pdo->query("SELECT DATE(data_acesso) as data_pico, COUNT(*) as qtd FROM historico_acessos GROUP BY data_pico ORDER BY qtd DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $stats_extras['hora_pico'] = $pdo->query("SELECT HOUR(data_acesso) as hora, COUNT(*) as qtd FROM historico_acessos GROUP BY hora ORDER BY qtd DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $stats_extras['acessos_madrugada'] = $pdo->query("SELECT COUNT(*) FROM historico_acessos WHERE HOUR(data_acesso) BETWEEN 0 AND 5")->fetchColumn();
    $stats_extras['acessos_manha'] = $pdo->query("SELECT COUNT(*) FROM historico_acessos WHERE HOUR(data_acesso) BETWEEN 6 AND 11")->fetchColumn();
    $stats_extras['acessos_tarde'] = $pdo->query("SELECT COUNT(*) FROM historico_acessos WHERE HOUR(data_acesso) BETWEEN 12 AND 17")->fetchColumn();
    $stats_extras['acessos_noite'] = $pdo->query("SELECT COUNT(*) FROM historico_acessos WHERE HOUR(data_acesso) BETWEEN 18 AND 23")->fetchColumn();
    $stats_extras['fim_de_semana'] = $pdo->query("SELECT COUNT(*) FROM historico_acessos WHERE DAYOFWEEK(data_acesso) IN (1, 7)")->fetchColumn();
    $stats_extras['dias_uteis'] = $stats_acessos['total'] - $stats_extras['fim_de_semana']; // 13

    // 14-17. Retenção e Recência
    $stats_extras['ultimos_7_dias'] = $pdo->query("SELECT COUNT(*) FROM historico_acessos WHERE data_acesso >= (CURDATE() - INTERVAL 7 DAY)")->fetchColumn();
    $stats_extras['ultimos_30_dias'] = $pdo->query("SELECT COUNT(*) FROM historico_acessos WHERE data_acesso >= (CURDATE() - INTERVAL 30 DAY)")->fetchColumn();
    $stats_extras['acessos_ontem'] = $pdo->query("SELECT COUNT(*) FROM historico_acessos WHERE DATE(data_acesso) = (CURDATE() - INTERVAL 1 DAY)")->fetchColumn();

    $crescimento = 0;
    if($stats_extras['acessos_ontem'] > 0) {
        $crescimento = (($stats_acessos['hoje'] - $stats_extras['acessos_ontem']) / $stats_extras['acessos_ontem']) * 100;
    } else if ($stats_acessos['hoje'] > 0) {
        $crescimento = 100; // Crescimento infinito se ontem foi 0
    }
    $stats_extras['crescimento_diario'] = round($crescimento, 1);

    // 18-30. Agrupamento em PHP (Navegadores, OS, Dispositivos) usando as funções nativas
    // Para não sobrecarregar o DB com LIKEs pesados, faremos a triagem em PHP de todos os registros
    $todos_uas = $pdo->query("SELECT user_agent FROM historico_acessos")->fetchAll(PDO::FETCH_COLUMN);

    $browsers = [];
    $oss = [];
    $devices = ['Mobile' => 0, 'Desktop' => 0];
    $bots_filtrados = 0; // Se houver algum bot que passou
    $redes_sociais = ['Instagram' => 0, 'Facebook' => 0, 'WhatsApp' => 0];

    foreach($todos_uas as $ua) {
        $b = getBrowserName($ua);
        $o = getOSName($ua);

        $browsers[$b] = ($browsers[$b] ?? 0) + 1;
        $oss[$o] = ($oss[$o] ?? 0) + 1;

        // Triagem Mobile vs Desktop (Simplificada)
        if(stripos($ua, 'mobile') !== false || stripos($ua, 'android') !== false || stripos($ua, 'iphone') !== false || stripos($ua, 'ipad') !== false) {
            $devices['Mobile']++;
        } else {
            $devices['Desktop']++;
        }

        // Triagem de origem de Redes Sociais no Browser In-App
        if(stripos($ua, 'Instagram') !== false) $redes_sociais['Instagram']++;
        if(stripos($ua, 'FBAN') !== false || stripos($ua, 'FBAV') !== false) $redes_sociais['Facebook']++;
        if(stripos($ua, 'WhatsApp') !== false) $redes_sociais['WhatsApp']++; // Geralmente filtrado, mas caso haja clique direto
    }

    arsort($browsers);
    arsort($oss);
    arsort($redes_sociais);

    $stats_extras['top_browsers'] = array_slice($browsers, 0, 4);
    $stats_extras['top_oss'] = array_slice($oss, 0, 4);
    $stats_extras['devices'] = $devices;
    $stats_extras['redes_sociais'] = $redes_sociais;

    // Métricas Finais em %
    $total_devices = $devices['Mobile'] + $devices['Desktop'];
    $stats_extras['perc_mobile'] = $total_devices > 0 ? round(($devices['Mobile'] / $total_devices) * 100, 1) : 0;
    $stats_extras['perc_desktop'] = $total_devices > 0 ? round(($devices['Desktop'] / $total_devices) * 100, 1) : 0;


    // Pega os últimos 100 acessos para a tabela
    $stmtHistorico = $pdo->query("SELECT * FROM historico_acessos ORDER BY data_acesso DESC LIMIT 100");
    $lista_historico = $stmtHistorico->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Tabela pode não existir ainda
}

// Função simples para extrair o nome do navegador do User Agent
function getBrowserName($user_agent) {
    $t = strtolower($user_agent);
    $t = " " . $t;
    if (strpos($t, 'opera') || strpos($t, 'opr/')) return 'Opera';
    elseif (strpos($t, 'edge')) return 'Edge';
    elseif (strpos($t, 'chrome')) return 'Chrome';
    elseif (strpos($t, 'safari')) return 'Safari';
    elseif (strpos($t, 'firefox')) return 'Firefox';
    elseif (strpos($t, 'msie') || strpos($t, 'trident/7')) return 'Internet Explorer';
    return 'Desconhecido';
}

// Função simples para extrair o OS do User Agent
function getOSName($user_agent) {
    $t = strtolower($user_agent);
    if (strpos($t, 'windows')) return 'Windows';
    elseif (strpos($t, 'mac')) return 'Mac OS';
    elseif (strpos($t, 'linux')) return 'Linux';
    elseif (strpos($t, 'android')) return 'Android';
    elseif (strpos($t, 'iphone') || strpos($t, 'ipad')) return 'iOS';
    return 'Desconhecido';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - Ajude a Gi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        :root { --bg: #F8FAFC; --sidebar: #0F172A; --primary: #0EA5E9; --text: #334155; --card: #FFFFFF; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
        
        .sidebar { width: 250px; background: var(--sidebar); color: white; padding: 20px 0; display: flex; flex-direction: column; }
        .sidebar h2 { text-align: center; font-size: 1.2rem; margin-bottom: 30px; color: var(--primary); }
        .sidebar a { color: #CBD5E1; text-decoration: none; padding: 15px 25px; display: block; border-left: 4px solid transparent; transition: 0.2s; }
        .sidebar a:hover, .sidebar a.active { background: #1E293B; border-left-color: var(--primary); color: white; }
        .sidebar a i { margin-right: 10px; width: 20px; text-align: center; }
        .sidebar .logout { margin-top: auto; border-top: 1px solid #1E293B; }
        .sidebar .logout:hover { border-left-color: #EF4444; }
        
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        h1 { margin-bottom: 30px; font-size: 1.8rem; color: #0F172A; }
        
        .card { background: var(--card); padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .card h3 { margin-bottom: 20px; color: #0F172A; border-bottom: 2px solid #E2E8F0; padding-bottom: 10px; font-size: 1.2rem; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; }
        input[type="text"], input[type="number"], input[type="date"], textarea, select { width: 100%; padding: 10px 15px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 1rem; outline: none; transition: border 0.3s; background: #fff;}
        input:focus, textarea:focus, select:focus { border-color: var(--primary); }
        textarea { resize: vertical; min-height: 100px; }
        
        .btn { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.3s; display: inline-block; }
        .btn:hover { background: #0284C7; }
        .btn-danger { background: #EF4444; padding: 8px 12px; font-size: 0.8rem; }
        .btn-danger:hover { background: #DC2626; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #E2E8F0; }
        th { background: #F1F5F9; font-weight: 600; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2><i class="fas fa-heartbeat"></i> Admin Gi</h2>

        <a href="#usuarios-online-admin" class="nav-link" style="background: rgba(255,255,255,0.1); margin: 0 15px 20px 15px; padding: 15px; border-radius: 8px; text-align: center; border: 1px solid rgba(255,255,255,0.2); display: block; border-left: none;">
            <div style="font-size: 0.85rem; color: #94A3B8; text-transform: uppercase; margin-bottom: 5px;">Pessoas no site agora</div>
            <div style="font-size: 2rem; font-weight: 700; color: #10B981; display: flex; align-items: center; justify-content: center; gap: 10px;">
                <i class="fas fa-circle" style="font-size: 0.8rem; animation: pulse-danger 1.5s infinite;"></i>
                <?php echo $qtd_online; ?>
            </div>
            <div style="font-size: 0.75rem; color: #CBD5E1; margin-top: 5px;">Clique para ver detalhes</div>
        </a>

        <a href="#doacao-manual" class="nav-link active"><i class="fas fa-hand-holding-heart"></i> Lançar Doação</a>
        <a href="#configuracoes" class="nav-link"><i class="fas fa-wallet"></i> Configurações</a>
        <a href="#transparencia" class="nav-link"><i class="fas fa-file-medical"></i> Transparência</a>
        <a href="#mural" class="nav-link"><i class="fas fa-bullhorn"></i> Mural</a>
        <a href="#midia" class="nav-link"><i class="fas fa-images"></i> Galeria</a>
        <a href="#videos-medico-admin" class="nav-link"><i class="fas fa-user-md"></i> Vídeos Médico</a>
        <a href="#historico-acessos-admin" class="nav-link"><i class="fas fa-chart-line"></i> Acessos</a>
        <a href="index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Ver Site</a>
        <a href="?sair=1" class="logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>

    <div class="main-content">
        <h1>Painel de Controle</h1>

        <div class="card admin-section" id="doacao-manual">
            <h3><i class="fas fa-hand-holding-heart"></i> Lançar Doação Manual</h3>
            <p style="margin-bottom: 20px; font-size: 0.9rem; color: #64748B;">Use esta opção para registrar doações recebidas por fora do site (ex: PIX direto, dinheiro físico). Isso somará o valor na barra de progresso e criará um recado automático no mural de atualizações.</p>
            
            <form id="formDoacaoManual">
                <input type="hidden" name="acao" value="registrar_doacao_manual">
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Nome do Doador (Como aparecerá no mural)</label>
                        <input type="text" name="nome_doador" placeholder="Ex: Tia Maria (ou deixe em branco para Anônimo)" required>
                    </div>
                    <div class="form-group">
                        <label>Valor Doado (R$)</label>
                        <input type="number" step="0.01" min="1" name="valor_doado" placeholder="Ex: 150.00" required>
                    </div>
                </div>


                <button type="submit" class="btn" style="background-color: #10B981; color: white;"><i class="fas fa-check-circle"></i> Registrar e Atualizar Site</button>
            </form>
        </div>

        <div class="card admin-section" id="configuracoes" style="display: none;">
            <h3><i class="fas fa-wallet"></i> Valores e Chaves PIX</h3>
            <form id="formConfiguracoes">
                <input type="hidden" name="acao" value="salvar_configuracoes">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Valor da Meta (R$)</label>
                        <input type="text" name="meta_total" value="<?php echo number_format($config['meta_total'], 2, ',', '.'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Valor Arrecadado Atual (R$)</label>
                        <input type="text" name="valor_arrecadado" value="<?php echo number_format($config['valor_arrecadado'], 2, ',', '.'); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Chave PIX (Aparece em texto)</label>
                    <input type="text" name="chave_pix" value="<?php echo htmlspecialchars($config['chave_pix']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Código PIX Copia e Cola (Aquele gigante gerado pelo banco)</label>
                    <input type="text" name="codigo_pix_copia_cola" value="<?php echo htmlspecialchars($config['codigo_pix_copia_cola']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Nome do Beneficiário do PIX</label>
                    <input type="text" name="nome_beneficiario" value="<?php echo htmlspecialchars($config['nome_beneficiario']); ?>" required>
                </div>

                <button type="submit" class="btn"><i class="fas fa-save"></i> Salvar Configurações</button>
            </form>
        </div>

        <div class="card admin-section" id="transparencia" style="display: none;">
            <h3><i class="fas fa-file-medical"></i> Adicionar Exame, Laudo ou Orçamento</h3>
            <form id="formExame" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="adicionar_documento">
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label>Título do Documento</label>
                        <input type="text" name="nome" placeholder="Ex: Laudo Ultrassom 31/03" required>
                    </div>
                    <div class="form-group">
                        <label>Tipo de Ícone</label>
                        <select name="icone">
                            <option value="fas fa-file-medical">Laudo Médico</option>
                            <option value="fas fa-file-invoice-dollar">Orçamento / Recibo</option>
                            <option value="fas fa-microscope">Resultado de Exame</option>
                            <option value="fas fa-image">Imagem / Foto</option>
                            <option value="fas fa-file-pdf">Documento em PDF</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Selecione o Arquivo (PDF ou Imagem)</label>
                    <input type="file" name="arquivo" accept=".pdf, .jpg, .jpeg, .png" required style="padding: 10px 0; border: none;">
                </div>

                <button type="submit" class="btn"><i class="fas fa-upload"></i> Fazer Upload</button>
            </form>

            <h3 style="margin-top: 40px;">Documentos Disponíveis no Site</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Arquivo</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($documentos as $doc): ?>
                    <tr id="doc-<?php echo $doc['id']; ?>">
                        <td><i class="<?php echo htmlspecialchars($doc['icone']); ?>"></i> <?php echo htmlspecialchars($doc['nome']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($doc['caminho_arquivo']); ?>" target="_blank" style="color: var(--primary); font-weight: 600;">Ver Arquivo</a></td>
                        <td>
                            <button onclick="excluirDoc(<?php echo $doc['id']; ?>)" class="btn btn-danger"><i class="fas fa-trash"></i> Excluir</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card admin-section" id="mural" style="display: none;">
            <h3><i class="fas fa-newspaper"></i> Adicionar Nova Atualização no Mural</h3>
            <form id="formAtualizacao">
                <input type="hidden" name="acao" value="adicionar_atualizacao">
                
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
                    <div class="form-group">
                        <label>Data</label>
                        <input type="date" name="data_publicacao" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Título da Atualização</label>
                        <input type="text" name="titulo" placeholder="Ex: Fizemos o primeiro exame!" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Texto da Atualização</label>
                    <textarea name="descricao" placeholder="Escreva os detalhes aqui..." required></textarea>
                </div>

                <button type="submit" class="btn"><i class="fas fa-plus"></i> Publicar no Mural</button>
            </form>

            <h3 style="margin-top: 40px;">Atualizações Publicadas</h3>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Título</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($atualizacoes as $up): ?>
                    <tr id="linha-<?php echo $up['id']; ?>">
                        <td><?php echo date('d/m/Y', strtotime($up['data_publicacao'])); ?></td>
                        <td><?php echo htmlspecialchars($up['titulo']); ?></td>
                        <td>
                            <button onclick="excluirAtualizacao(<?php echo $up['id']; ?>)" class="btn btn-danger"><i class="fas fa-trash"></i> Excluir</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card admin-section" id="midia" style="display: none;">
            <h3><i class="fas fa-images"></i> Galeria de Mídia (Fotos e Vídeos)</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">Adicione várias fotos ou vídeos. Eles aparecerão em formato de galeria no topo do site.</p>
            
            <form id="formGaleria" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="adicionar_galeria">
                
                <div class="form-group">
                    <label>O que você quer adicionar?</label>
                    <div style="display: flex; gap: 20px; margin-top: 10px;">
                        <label><input type="radio" name="midia_tipo" value="imagem" checked> Nova Imagem (Foto)</label>
                        <label><input type="radio" name="midia_tipo" value="video"> Novo Vídeo (YouTube)</label>
                    </div>
                </div>

                <div class="form-group campo-video" style="display:none;">
                    <label>Código do Vídeo do YouTube (Apenas o ID Final)</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: var(--text-muted);">youtube.com/watch?v=</span>
                        <input type="text" name="midia_video_id" placeholder="Ex: dQw4w9WgXcQ">
                    </div>
                </div>

                <div class="form-group campo-imagem">
                    <label>Selecione a Imagem (JPG ou PNG)</label>
                    <input type="file" name="midia_imagem" accept=".jpg, .jpeg, .png" style="padding: 10px 0; border: none;">
                </div>

                <button type="submit" class="btn"><i class="fas fa-plus"></i> Adicionar à Galeria</button>
            </form>

            <h3 style="margin-top: 40px; font-size: 1.1rem;">Itens da Galeria</h3>
            <table>
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Prévia</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmtGaleria = $pdo->query("SELECT * FROM galeria ORDER BY id DESC");
                    while($item = $stmtGaleria->fetch(PDO::FETCH_ASSOC)):
                    ?>
                    <tr id="gal-<?php echo $item['id']; ?>">
                        <td><i class="fas <?php echo $item['tipo'] == 'video' ? 'fa-play-circle text-danger' : 'fa-image text-primary'; ?>"></i> <?php echo ucfirst($item['tipo']); ?></td>
                        <td>
                            <?php if($item['tipo'] == 'video'): ?>
                                <a href="https://youtube.com/watch?v=<?php echo htmlspecialchars($item['midia_url']); ?>" target="_blank" style="color:var(--primary);">Ver Vídeo</a>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($item['midia_url']); ?>" style="max-height: 40px; border-radius: 4px;">
                            <?php endif; ?>
                        </td>
                        <td>
                            <button onclick="excluirGaleria(<?php echo $item['id']; ?>)" class="btn btn-danger" style="padding: 5px 10px;"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="card admin-section" id="videos-medico-admin" style="display: none;">
            <h3><i class="fas fa-user-md"></i> Vídeos do Médico</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">Adicione os vídeos explicativos do médico. Eles aparecerão em uma seção especial estilo FAQ na página principal.</p>

            <form id="formVideoMedico">
                <input type="hidden" name="acao" value="adicionar_video_medico">

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label>Título / Pergunta do Vídeo</label>
                        <input type="text" name="titulo_video" placeholder="Ex: Por que a cirurgia tem que ser feita em 4 semanas?" required>
                    </div>
                    <div class="form-group">
                        <label>ID do Vídeo do YouTube</label>
                        <input type="text" name="youtube_id" placeholder="Ex: dQw4w9WgXcQ" required>
                    </div>
                </div>

                <button type="submit" class="btn"><i class="fas fa-plus"></i> Adicionar Vídeo</button>
            </form>

            <h3 style="margin-top: 40px; font-size: 1.1rem;">Vídeos do Médico Cadastrados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Vídeo</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $stmtVideosMedico = $pdo->query("SELECT * FROM videos_medico ORDER BY id DESC");
                        while($item = $stmtVideosMedico->fetch(PDO::FETCH_ASSOC)):
                    ?>
                    <tr id="video-medico-<?php echo $item['id']; ?>">
                        <td><?php echo htmlspecialchars($item['titulo']); ?></td>
                        <td><a href="https://youtube.com/watch?v=<?php echo htmlspecialchars($item['video_id']); ?>" target="_blank" style="color:var(--primary);"><i class="fas fa-external-link-alt"></i> Ver Vídeo</a></td>
                        <td>
                            <button onclick="excluirVideoMedico(<?php echo $item['id']; ?>)" class="btn btn-danger" style="padding: 5px 10px;"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php
                        endwhile;
                    } catch (Exception $e) {
                        echo "<tr><td colspan='3'>Erro ao carregar vídeos: " . $e->getMessage() . "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="card admin-section" id="usuarios-online-admin" style="display: none;">
            <h3><i class="fas fa-users"></i> Pessoas no Site Agora</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">Esta lista mostra quem está navegando no site nos últimos 5 minutos. Bots conhecidos (como rastreadores do Google ou WhatsApp) são filtrados automaticamente.</p>

            <table>
                <thead>
                    <tr>
                        <th>IP</th>
                        <th>Localização</th>
                        <th>Sistema / Dispositivo</th>
                        <th>Navegador</th>
                        <th>Último Acesso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($lista_usuarios_online) > 0): ?>
                        <?php foreach($lista_usuarios_online as $user): ?>
                        <tr>
                            <td style="font-family: monospace; color: var(--primary);"><?php echo htmlspecialchars($user['ip_address'] ?: 'Desconhecido'); ?></td>
                            <td>
                                <?php
                                    if(!empty($user['cidade']) && !empty($user['estado'])) {
                                        echo htmlspecialchars($user['cidade'] . ' - ' . $user['estado']);
                                    } else {
                                        echo 'Desconhecida';
                                    }
                                ?>
                            </td>
                            <td><?php echo getOSName($user['user_agent']); ?></td>
                            <td><?php echo getBrowserName($user['user_agent']); ?></td>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($user['ultimo_acesso'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan='5' style="text-align: center; color: var(--text-muted);">Nenhuma pessoa online no momento.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card admin-section" id="historico-acessos-admin" style="display: none;">
            <h3><i class="fas fa-chart-line"></i> Dashboard de Acessos</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">Análise completa de tráfego, audiência e retenção do site.</p>

            <h4 style="font-size: 1.1rem; margin-bottom: 15px; color: var(--primary); border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">1. Visão Geral (Volume)</h4>
            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 30px;">
                <div style="background: var(--bg); padding: 15px; border-radius: 8px; text-align: center; border: 1px solid var(--border-color);">
                    <div style="font-size: 0.80rem; color: var(--text-muted); text-transform: uppercase;">Acessos Hoje</div>
                    <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary);"><?php echo $stats_acessos['hoje']; ?></div>
                    <div style="font-size: 0.75rem; color: <?php echo $stats_extras['crescimento_diario'] >= 0 ? 'var(--success)' : '#EF4444'; ?>;">
                        <i class="fas fa-arrow-<?php echo $stats_extras['crescimento_diario'] >= 0 ? 'up' : 'down'; ?>"></i> <?php echo abs($stats_extras['crescimento_diario']); ?>% vs Ontem
                    </div>
                </div>
                <div style="background: var(--bg); padding: 15px; border-radius: 8px; text-align: center; border: 1px solid var(--border-color);">
                    <div style="font-size: 0.80rem; color: var(--text-muted); text-transform: uppercase;">Últimos 7 Dias</div>
                    <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary);"><?php echo $stats_extras['ultimos_7_dias']; ?></div>
                </div>
                <div style="background: var(--bg); padding: 15px; border-radius: 8px; text-align: center; border: 1px solid var(--border-color);">
                    <div style="font-size: 0.80rem; color: var(--text-muted); text-transform: uppercase;">Últimos 30 Dias</div>
                    <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary);"><?php echo $stats_extras['ultimos_30_dias']; ?></div>
                </div>
                <div style="background: var(--bg); padding: 15px; border-radius: 8px; text-align: center; border: 1px solid var(--border-color);">
                    <div style="font-size: 0.80rem; color: var(--text-muted); text-transform: uppercase;">Total</div>
                    <div style="font-size: 1.8rem; font-weight: 700; color: var(--success);"><?php echo $stats_acessos['total']; ?></div>
                </div>
                <div style="background: var(--bg); padding: 15px; border-radius: 8px; text-align: center; border: 1px solid var(--border-color);">
                    <div style="font-size: 0.80rem; color: var(--text-muted); text-transform: uppercase;">Melhor Dia</div>
                    <div style="font-size: 1.2rem; font-weight: 700; color: #F59E0B; margin-top: 5px;"><?php echo $stats_extras['melhor_dia']; ?></div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">

                <div>
                    <h4 style="font-size: 1.1rem; margin-bottom: 15px; color: var(--primary); border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">2. Análise de Comportamento Temporal</h4>
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 8px 0; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Pico Histórico de Acessos:</span>
                            <strong><?php echo isset($stats_extras['dia_com_mais_acessos_historico']['data_pico']) ? date('d/m/Y', strtotime($stats_extras['dia_com_mais_acessos_historico']['data_pico'])) . ' (' . $stats_extras['dia_com_mais_acessos_historico']['qtd'] . ' acessos)' : 'N/A'; ?></strong>
                        </li>
                        <li style="padding: 8px 0; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Horário de Pico Geral:</span>
                            <strong><?php echo isset($stats_extras['hora_pico']['hora']) ? $stats_extras['hora_pico']['hora'] . 'h00 (' . $stats_extras['hora_pico']['qtd'] . ' acessos)' : 'N/A'; ?></strong>
                        </li>
                        <li style="padding: 8px 0; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Acessos Fim de Semana / Úteis:</span>
                            <strong><?php echo $stats_extras['fim_de_semana'] . ' / ' . $stats_extras['dias_uteis']; ?></strong>
                        </li>
                        <li style="padding: 8px 0; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Tráfego por Turno:</span>
                            <span style="font-size: 0.85rem;">
                                Madruga: <strong><?php echo $stats_extras['acessos_madrugada']; ?></strong> |
                                Manhã: <strong><?php echo $stats_extras['acessos_manha']; ?></strong> |
                                Tarde: <strong><?php echo $stats_extras['acessos_tarde']; ?></strong> |
                                Noite: <strong><?php echo $stats_extras['acessos_noite']; ?></strong>
                            </span>
                        </li>
                    </ul>
                </div>

                <div>
                    <h4 style="font-size: 1.1rem; margin-bottom: 15px; color: var(--primary); border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">3. Audiência Geográfica</h4>
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 8px 0; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Total de Estados e Cidades Distintas:</span>
                            <strong><?php echo $stats_extras['qtd_estados_distintos']; ?> estados, <?php echo $stats_extras['qtd_cidades_distintas']; ?> cidades</strong>
                        </li>
                        <li style="padding: 8px 0; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Tráfego Internacional:</span>
                            <strong><?php echo $stats_extras['acessos_internacionais']; ?> acessos</strong>
                        </li>
                        <li style="padding: 8px 0; border-bottom: 1px solid var(--border-color);">
                            <span style="color: var(--text-muted); display: block; margin-bottom: 5px;">Top 3 Estados:</span>
                            <?php
                                $top_e = array_slice($stats_extras['top_estados'], 0, 3);
                                foreach($top_e as $e) echo "<span style='background: #E2E8F0; padding: 2px 8px; border-radius: 4px; font-size: 0.85rem; margin-right: 5px;'>{$e['estado']} ({$e['qtd']})</span>";
                                if(empty($top_e)) echo "<span style='color: #94A3B8; font-size: 0.85rem;'>Nenhum dado</span>";
                            ?>
                        </li>
                        <li style="padding: 8px 0;">
                            <span style="color: var(--text-muted); display: block; margin-bottom: 5px;">Top 3 Cidades:</span>
                            <?php
                                $top_c = array_slice($stats_extras['top_cidades'], 0, 3);
                                foreach($top_c as $c) echo "<span style='background: #E2E8F0; padding: 2px 8px; border-radius: 4px; font-size: 0.85rem; margin-right: 5px;'>{$c['cidade']} ({$c['qtd']})</span>";
                                if(empty($top_c)) echo "<span style='color: #94A3B8; font-size: 0.85rem;'>Nenhum dado</span>";
                            ?>
                        </li>
                    </ul>
                </div>
            </div>

            <h4 style="font-size: 1.1rem; margin-bottom: 15px; color: var(--primary); border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">4. Dispositivos, Tecnologia e Redes Sociais</h4>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; background: var(--bg-light); padding: 20px; border-radius: 8px;">
                <div>
                    <h5 style="margin-bottom: 10px; color: var(--text-dark);">Telas e Dispositivos</h5>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem;">
                        <span><i class="fas fa-mobile-alt"></i> Mobile: <?php echo $stats_extras['devices']['Mobile']; ?></span>
                        <strong><?php echo $stats_extras['perc_mobile']; ?>%</strong>
                    </div>
                    <div style="width: 100%; background: #E2E8F0; height: 8px; border-radius: 4px; margin-bottom: 15px;">
                        <div style="width: <?php echo $stats_extras['perc_mobile']; ?>%; background: var(--primary); height: 100%; border-radius: 4px;"></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem;">
                        <span><i class="fas fa-desktop"></i> Desktop: <?php echo $stats_extras['devices']['Desktop']; ?></span>
                        <strong><?php echo $stats_extras['perc_desktop']; ?>%</strong>
                    </div>
                    <div style="width: 100%; background: #E2E8F0; height: 8px; border-radius: 4px;">
                        <div style="width: <?php echo $stats_extras['perc_desktop']; ?>%; background: var(--secondary); height: 100%; border-radius: 4px;"></div>
                    </div>
                </div>

                <div>
                    <h5 style="margin-bottom: 10px; color: var(--text-dark);">Top Navegadores e SOs</h5>
                    <ul style="font-size: 0.85rem; color: var(--text-muted); list-style: square; padding-left: 15px;">
                        <?php
                        foreach($stats_extras['top_browsers'] as $br => $qtd) echo "<li><strong>$br:</strong> $qtd acessos</li>";
                        ?>
                    </ul>
                    <ul style="font-size: 0.85rem; color: var(--text-muted); list-style: square; padding-left: 15px; margin-top: 10px;">
                        <?php
                        foreach($stats_extras['top_oss'] as $os => $qtd) echo "<li><strong>$os:</strong> $qtd acessos</li>";
                        ?>
                    </ul>
                </div>

                <div>
                    <h5 style="margin-bottom: 10px; color: var(--text-dark);">Origem Social (In-App)</h5>
                    <ul style="font-size: 0.85rem; color: var(--text-muted); list-style: none; padding: 0;">
                        <li style="margin-bottom: 5px;"><i class="fab fa-instagram" style="color: #E1306C;"></i> Instagram: <strong><?php echo $stats_extras['redes_sociais']['Instagram']; ?> acessos</strong></li>
                        <li style="margin-bottom: 5px;"><i class="fab fa-facebook" style="color: #1877F2;"></i> Facebook: <strong><?php echo $stats_extras['redes_sociais']['Facebook']; ?> acessos</strong></li>
                        <li style="margin-bottom: 5px;"><i class="fab fa-whatsapp" style="color: #25D366;"></i> WhatsApp Web: <strong><?php echo $stats_extras['redes_sociais']['WhatsApp']; ?> acessos</strong></li>
                    </ul>
                </div>
            </div>

            <h3 style="margin-top: 20px; font-size: 1.1rem; border-top: 2px solid var(--border-color); padding-top: 20px;">Últimos 100 Visitantes Individuais</h3>
            <table>
                <thead>
                    <tr>
                        <th>Data do Acesso</th>
                        <th>IP</th>
                        <th>Localização</th>
                        <th>Sistema / Dispositivo</th>
                        <th>Navegador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($lista_historico) > 0): ?>
                        <?php foreach($lista_historico as $acesso): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($acesso['data_acesso'])); ?></td>
                            <td style="font-family: monospace; color: var(--primary);"><?php echo htmlspecialchars($acesso['ip_address'] ?: 'Desconhecido'); ?></td>
                            <td>
                                <?php
                                    if(!empty($acesso['cidade']) && !empty($acesso['estado'])) {
                                        echo htmlspecialchars($acesso['cidade'] . ' - ' . $acesso['estado']);
                                    } else {
                                        echo 'Desconhecida';
                                    }
                                ?>
                            </td>
                            <td><?php echo getOSName($acesso['user_agent']); ?></td>
                            <td><?php echo getBrowserName($acesso['user_agent']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan='5' style="text-align: center; color: var(--text-muted);">Nenhum acesso registrado ainda.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="admin.js"></script>

</body>
</html>