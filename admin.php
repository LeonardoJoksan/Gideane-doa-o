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
        <a href="#doacao-manual" class="nav-link active"><i class="fas fa-hand-holding-heart"></i> Lançar Doação</a>
        <a href="#configuracoes" class="nav-link"><i class="fas fa-wallet"></i> Configurações</a>
        <a href="#transparencia" class="nav-link"><i class="fas fa-file-medical"></i> Transparência</a>
        <a href="#mural" class="nav-link"><i class="fas fa-bullhorn"></i> Mural</a>
        <a href="#midia" class="nav-link"><i class="fas fa-images"></i> Galeria</a>
        <a href="#videos-medico-admin" class="nav-link"><i class="fas fa-user-md"></i> Vídeos Médico</a>
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

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="admin.js"></script>

</body>
</html>