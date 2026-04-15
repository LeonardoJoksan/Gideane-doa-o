<?php
// index.php
require 'conexao.php';

// Sistema de Idiomas Simples
$idiomas_suportados = ['pt', 'en', 'es'];
$lang = isset($_GET['lang']) && in_array($_GET['lang'], $idiomas_suportados) ? $_GET['lang'] : 'pt';

// Carrega as traduções estáticas
require 'idiomas.php';
$t = $traducoes[$lang];

// Função de Tradução Dinâmica Gratuita (Google Translate API)
function traduzirTextoDinamico($texto, $idioma_destino) {
    if ($idioma_destino === 'pt' || empty(trim($texto))) return $texto;

    // Pequeno cache em arquivo para evitar bloqueios da API
    $hash = md5($texto . $idioma_destino);
    $arquivo_cache = "uploads/cache_trad_" . $hash . ".txt";

    if (file_exists($arquivo_cache)) {
        return file_get_contents($arquivo_cache);
    }

    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=pt&tl=" . $idioma_destino . "&dt=t&q=" . urlencode($texto);

    $opcoes = [
        "http" => [
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
        ]
    ];
    $contexto = stream_context_create($opcoes);
    $resposta = @file_get_contents($url, false, $contexto);

    if ($resposta) {
        $json = json_decode($resposta, true);
        if (isset($json[0]) && is_array($json[0])) {
            $traducao_final = '';
            foreach ($json[0] as $linha) {
                $traducao_final .= $linha[0];
            }
            // Salva no cache
            if (!is_dir('uploads')) mkdir('uploads', 0777, true);
            file_put_contents($arquivo_cache, $traducao_final);
            return $traducao_final;
        }
    }

    // Se falhar, retorna o original em PT
    return $texto;
}

// Busca configurações principais (valores dinâmicos)
$stmt = $pdo->query("SELECT * FROM configuracoes LIMIT 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

// Cálculos para a barra de progresso
$meta = $config['meta_total'];
$arrecadado = $config['valor_arrecadado'];
$porcentagem = ($meta > 0) ? ($arrecadado / $meta) * 100 : 0;
// Limita a 100% caso ultrapasse a meta
if($porcentagem > 100) $porcentagem = 100;

// Formatação de moeda brasileira para exibição
$arrecadado_formatado = number_format($arrecadado, 2, ',', '.');
$meta_formatada = number_format($meta, 2, ',', '.');
$porcentagem_formatada = number_format($porcentagem, 1, ',', '.');

// Busca atualizações para o mural (Ordenado da mais recente para a mais antiga)
$stmtAtualizacoes = $pdo->query("SELECT * FROM atualizacoes ORDER BY data_publicacao DESC, id DESC");
$atualizacoes = $stmtAtualizacoes->fetchAll(PDO::FETCH_ASSOC);

// Busca os documentos de transparência
$stmtDocs = $pdo->query("SELECT * FROM documentos ORDER BY id DESC");
$documentos = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

// Busca a galeria de mídias dinâmicas
$stmtGaleria = $pdo->query("SELECT * FROM galeria ORDER BY id DESC");
$galeria = $stmtGaleria->fetchAll(PDO::FETCH_ASSOC);

// Busca os vídeos do médico
$videos_medico = [];
try {
    $stmtVideosMedico = $pdo->query("SELECT * FROM videos_medico ORDER BY id DESC");
    $videos_medico = $stmtVideosMedico->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Tabela pode não existir no primeiro carregamento, ignora erro silenciosamente no frontend
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $t['meta_title']; ?></title>
    
    <meta name="description" content="<?php echo $t['meta_desc']; ?>">
    <meta name="keywords" content="braquiterapia ocular, câncer no olho, ajuda para cirurgia, vaquinha online, doação, Gideane Sousa Pereira, tumor ocular maligno, ajuda financeira, solidariedade">
    <meta name="author" content="Amigos e Familiares da Gi">
    <meta name="robots" content="index, follow">
    
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://gideane.com.br/"> 
    <meta property="og:title" content="Ajude a Gi a Vencer o Câncer Ocular">
    <meta property="og:description" content="Temos apenas 4 semanas para arrecadar o valor da Braquiterapia Ocular e salvar a vida da Gi. Toda ajuda importa. Acesse e veja como doar e compartilhar.">
    <meta property="og:image" content="https://gideane.com.br/imagem-compartilhamento.jpg"> 
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="gi.css?v=1.1">
</head>
<body style="overflow-x: hidden; width: 100%;">

    <div class="lang-switcher-wrapper">
        <span class="lang-label">Idioma / Language</span>
        <div class="lang-switcher">
            <a href="?lang=pt" title="Português"><img src="https://flagcdn.com/w40/br.png" class="lang-flag <?php echo $lang == 'pt' ? 'active' : 'inactive'; ?>" alt="Português"></a>
            <a href="?lang=en" title="English"><img src="https://flagcdn.com/w40/us.png" class="lang-flag <?php echo $lang == 'en' ? 'active' : 'inactive'; ?>" alt="English"></a>
            <a href="?lang=es" title="Español"><img src="https://flagcdn.com/w40/es.png" class="lang-flag <?php echo $lang == 'es' ? 'active' : 'inactive'; ?>" alt="Español"></a>
        </div>
    </div>

    <header class="hero">
        <div class="container hero-wrapper">
            <div class="hero-content">
                <span class="badge-urgency" id="countdown-urgency"><i class="fas fa-clock"></i> <?php echo $t['hero_badge']; ?></span>
                <h1><?php echo $t['hero_title']; ?></h1>
                <p><?php echo $t['hero_desc']; ?></p>
                
                <div class="progress-card">
                    <div class="progress-stats">
                        <div class="stat">
                            <span class="stat-label"><?php echo $t['stat_raised']; ?></span>
                            <span class="stat-value text-green">R$ <?php echo $arrecadado_formatado; ?></span>
                        </div>
                        <div class="stat text-right">
                            <span class="stat-label"><?php echo $t['stat_goal']; ?></span>
                            <span class="stat-value">R$ <?php echo $meta_formatada; ?></span>
                        </div>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" id="progressBar" data-percent="<?php echo round($porcentagem, 2); ?>%"></div>
                    </div>
                    <p class="progress-percent"><?php echo $porcentagem_formatada; ?><?php echo $t['stat_percent']; ?></p>
                </div>

                <div class="hero-actions">
                    <a href="#doar" class="btn btn-primary"><i class="fas fa-heart"></i> <?php echo $t['hero_btn_donate']; ?></a>
                    <a href="#historia" class="btn btn-outline"><?php echo $t['hero_btn_story']; ?></a>
                </div>
            </div>

            <div class="hero-media">
                <?php if(count($galeria) > 0): 
                    $primeiraMidia = $galeria[0];
                ?>
                    <div class="media-gallery">
                        <div class="main-media-display" id="mainMediaDisplay">
                            <?php if($primeiraMidia['tipo'] == 'video'): ?>
                                <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($primeiraMidia['midia_url']); ?>?modestbranding=1&rel=0" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen title="Vídeo Principal" style="width: 100%; aspect-ratio: 16/9; border-radius: 8px;"></iframe>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($primeiraMidia['midia_url']); ?>" alt="Imagem da Gi - Campanha Braquiterapia" style="max-width: 100%; height: auto; border-radius: 8px;">
                            <?php endif; ?>
                        </div>

                        <?php if(count($galeria) > 1): ?>
                            <div class="media-thumbnails">
                                <?php foreach($galeria as $index => $item): ?>
                                    <?php if($item['tipo'] == 'video'): ?>
                                        <div class="media-thumb-video <?php echo $index == 0 ? 'active' : ''; ?>" onclick="trocarMidia('video', '<?php echo $item['midia_url']; ?>', this)">
                                            <i class="fas fa-play"></i>
                                            <img src="https://img.youtube.com/vi/<?php echo $item['midia_url']; ?>/default.jpg" alt="Miniatura Vídeo">
                                        </div>
                                    <?php else: ?>
                                        <img src="<?php echo htmlspecialchars($item['midia_url']); ?>" class="media-thumb <?php echo $index == 0 ? 'active' : ''; ?>" onclick="trocarMidia('imagem', '<?php echo htmlspecialchars($item['midia_url']); ?>', this)" alt="Miniatura Foto">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <div class="video-wrapper">
                        <div class="media-placeholder">
                            <i class="fas fa-camera"></i>
                            <span><?php echo $t['media_empty']; ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="section bg-light" id="historia">
        <div class="container max-w-800">
            <h2 class="section-title text-center">A Nossa Corrida Pela Vida</h2>
            
            <p class="lead-text">Tudo começou por volta do dia 20 de março de 2026, quando a Gi percebeu que estava perdendo a visão do olho direito. O que parecia ser um problema de vista comum nos levou a uma corrida contra o tempo.</p>
            
            <p class="paragraph">Sabendo da demora da rede pública, decidimos nos apertar financeiramente para realizar consultas e uma bateria de exames particulares com urgência (Mapeamento de Retina, Retinografia e Ultrassons Oculares de ambos os olhos). O resultado saiu no mesmo dia e caiu como uma bomba: foi identificada uma anomalia grave e fomos encaminhados imediatamente para um especialista em Oncologia Ocular.</p>
            
            <button id="btn-leia-mais" class="btn btn-outline" style="border-color: var(--secondary); color: var(--secondary); margin: 20px auto; display: block;">Leia mais <i class="fas fa-chevron-down"></i></button>

            <div id="restante-historia" style="display: none;">
                <p class="paragraph">Encontramos a <a href="https://clinicabelfort.com.br/equipe/dr-rubens-belfort-neto/" target="_blank" style="color: var(--secondary); font-weight: 600; text-decoration: underline;">Clínica Belfort</a>, onde fomos atendidos pelo Dr. Rubens Belfort. O diagnóstico foi duro: um <strong>Tumor Maligno de 6mm no olho</strong>. Infelizmente, não há chance de recuperar a visão perdida.</p>

                <p class="paragraph">O médico nos apresentou duas opções: a retirada total do globo ocular com implante de prótese, ou a <strong>Braquiterapia Ocular</strong> (a implantação de uma placa de radiação que seca o tumor e evita a remoção do olho). Como ambas têm praticamente o mesmo custo, optamos pela Braquiterapia, que oferece muito mais dignidade à Gi.</p>

                <div class="alert-card" style="display: block;">
                    <div style="display: flex; gap: 20px; align-items: flex-start; margin-bottom: 20px;">
                        <div class="alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="alert-text">
                            <h3>As 3 únicas opções no Brasil e a nossa urgência</h3>
                            <p>Essa cirurgia é altamente complexa e o tumor é agressivo. O médico foi claro: temos no máximo <strong>4 semanas</strong> para operar. No Brasil, existem apenas três caminhos:</p>
                        </div>
                    </div>

                    <ul style="padding-left: 20px; color: #7F1D1D; font-size: 0.95rem; line-height: 1.6;">
                        <li style="margin-bottom: 12px;"><strong>1. Pelo SUS (em Barretos):</strong> A fila de espera ultrapassa 6 meses. Infelizmente, não temos esse tempo.</li>
                        <li style="margin-bottom: 12px;"><strong>2. Hospital A.C.Camargo:</strong> O custo seria o mesmo, mas a equipe do cirurgião escolhido não é focada exclusivamente nesta especialidade.</li>
                        <li><strong>3. Hospital Albert Einstein:</strong> A cirurgia será realizada pela equipe do Dr. Rubens, referência no assunto. Esta é a nossa única opção viável e segura dentro do prazo que temos.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="gi-social-box">
            <h3><i class="fas fa-user-circle"></i> <?php echo $t['social_title']; ?></h3>
            <p><?php echo $t['social_desc']; ?></p>
            
            <div class="gi-social-links">
                <a href="https://www.instagram.com/gipereira.123/" target="_blank" class="gi-link instagram">
                    <i class="fab fa-instagram"></i>
                    <span>@gipereira.123</span>
                </a>
                
                <a href="https://www.facebook.com/gideanesousa.pereira" target="_blank" class="gi-link facebook">
                    <i class="fab fa-facebook-f"></i>
                    <span>Gideane Sousa</span>
                </a>
                
                <a href="https://api.whatsapp.com/send?phone=5511988311212&text=Oi%20Gi!%20Vi%20a%20sua%20campanha%20e%20estou%20torcendo%20por%20voc%C3%AA!" target="_blank" class="gi-link whatsapp-personal">
                    <i class="fab fa-whatsapp"></i>
                    <span>(11) 98831-1212</span>
                </a>
            </div>
        </div>
    </section>

    <section class="section" id="faq">
        <div class="container max-w-800">
            <h2 class="section-title text-center"><?php echo $t['faq_title']; ?></h2>
            <p class="section-subtitle text-center"><?php echo $t['faq_sub']; ?></p>
            
            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question"><?php echo $t['faq_q1']; ?> <i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer"><?php echo $t['faq_a1']; ?></div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question"><?php echo $t['faq_q2']; ?> <i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer"><?php echo $t['faq_a2']; ?></div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question"><?php echo $t['faq_q3']; ?> <i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer"><?php echo $t['faq_a3']; ?></div>
                </div>

                <div class="faq-item">
                    <div class="faq-question"><?php echo $t['faq_q4']; ?> <i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer"><?php echo $t['faq_a4']; ?></div>
                </div>

                <div class="faq-item">
                    <div class="faq-question"><?php echo $t['faq_q5']; ?> <i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer"><?php echo $t['faq_a5']; ?></div>
                </div>

                <div class="faq-item">
                    <div class="faq-question"><?php echo $t['faq_q6']; ?> <i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer"><?php echo $t['faq_a6']; ?></div>
                </div>

                <div class="faq-item">
                    <div class="faq-question"><?php echo $t['faq_q7']; ?> <i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer"><?php echo $t['faq_a7']; ?></div>
                </div>

                <div class="faq-item">
                    <div class="faq-question"><?php echo $t['faq_q8']; ?> <i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer"><?php echo $t['faq_a8']; ?></div>
                </div>

                <div class="faq-item">
                    <div class="faq-question"><?php echo $t['faq_q9']; ?> <i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer"><?php echo $t['faq_a9']; ?></div>
                </div>

                <div class="faq-item">
                    <div class="faq-question"><?php echo $t['faq_q10']; ?> <i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer"><?php echo $t['faq_a10']; ?></div>
                </div>

                <div class="faq-item">
                    <div class="faq-question"><?php echo $t['faq_q11']; ?> <i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer"><?php echo $t['faq_a11']; ?></div>
                </div>

                <div class="faq-item">
                    <div class="faq-question"><?php echo $t['faq_q12']; ?> <i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer"><?php echo $t['faq_a12']; ?></div>
                </div>

                <div class="faq-item">
                    <div class="faq-question"><?php echo $t['faq_q13']; ?> <i class="fas fa-chevron-down"></i></div>
                    <div class="faq-answer"><?php echo $t['faq_a13']; ?></div>
                </div>
            </div>
        </div>
    </section>

    <section class="section destaque-doar" id="doar">
        <div class="container">
            <h2 class="section-title text-center" style="color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?php echo $t['donate_title']; ?></h2>
            <div class="grid cards-grid">
                
                <?php if ($lang == 'pt'): ?>
                    <!-- Formas de Pagamento Locais (PIX/MP) -->
                    <div class="card donate-card">
                        <div class="card-icon"><i class="fab fa-pix"></i></div>
                        <h3><?php echo $t['donate_pix']; ?></h3>
                        <p><?php echo $t['donate_pix_desc']; ?></p>
                        <div class="pix-info">
                            <p style="margin-bottom: 5px;"><strong><?php echo $t['donate_pix_key']; ?></strong></p>
                            <div class="copy-group" style="margin-bottom: 15px;">
                                <input type="text" id="pixKeyEmail" value="<?php echo htmlspecialchars($config['chave_pix']); ?>" readonly>
                                <button onclick="copyPixKeyEmail()" class="btn-copy" id="btnCopyEmail"><i class="fas fa-copy"></i></button>
                            </div>
                            <p><strong><?php echo $t['donate_pix_name']; ?></strong><br> <?php echo htmlspecialchars($config['nome_beneficiario']); ?></p>
                        </div>
                    </div>

                    <div class="card donate-card highlight-card">
                        <div class="card-badge">Mais Rápido</div>
                        <div class="card-icon"><i class="fas fa-qrcode"></i></div>
                        <h3>PIX Copia e Cola</h3>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($config['codigo_pix_copia_cola']); ?>" alt="QR Code PIX" class="qr-image">
                        <div class="copy-group">
                            <input type="text" id="pixCode" value="<?php echo htmlspecialchars($config['codigo_pix_copia_cola']); ?>" readonly>
                            <button onclick="copyPix()" class="btn-copy" id="btnCopy"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>

                    <div class="card donate-card">
                        <div class="card-icon mp-icon"><i class="fas fa-handshake"></i></div>
                        <h3><?php echo $t['donate_mp_title']; ?></h3>
                        <p><?php echo $t['donate_mp_desc']; ?></p>
                        <a href="doar.php" class="btn btn-mp"><i class="fas fa-credit-card"></i> <?php echo $t['donate_mp_btn']; ?></a>
                    </div>
                <?php else: ?>
                    <!-- Formas de Pagamento Internacionais (Stripe) -->
                    <div class="card donate-card highlight-card" style="grid-column: 1 / -1; max-width: 600px; margin: 0 auto; opacity: 0.8;">
                        <div class="card-icon mp-icon" style="color: #6366F1;"><i class="fab fa-stripe"></i></div>
                        <h3><?php echo $t['donate_stripe_title']; ?></h3>
                        <p style="color: #EF4444; font-weight: 600;"><i class="fas fa-tools"></i> <?php echo $t['donate_stripe_desc']; ?></p>
                        <a href="#" class="btn btn-mp" style="background: #94A3B8; cursor: not-allowed;" onclick="return false;"><i class="fas fa-clock"></i> <?php echo $t['donate_stripe_btn']; ?></a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </section>

    <?php if (count($videos_medico) > 0): ?>
    <section class="section bg-light" id="videos-medico">
        <div class="container max-w-800">
            <h2 class="section-title text-center"><?php echo $t['nav_videos']; ?></h2>
            <p class="section-subtitle text-center"><?php echo $t['nav_videos_sub']; ?></p>

            <div class="medico-videos-grid">
                <?php foreach($videos_medico as $video): ?>
                <div class="medico-thumb-wrapper" onclick="openVideoModal('<?php echo htmlspecialchars($video['video_id']); ?>')">
                    <img src="https://img.youtube.com/vi/<?php echo htmlspecialchars($video['video_id']); ?>/hqdefault.jpg" alt="<?php echo htmlspecialchars($video['titulo']); ?>">
                    <div class="play-icon-overlay">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="medico-thumb-title">
                        <?php echo htmlspecialchars(traduzirTextoDinamico($video['titulo'], $lang)); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="section" id="transparencia">
        <div class="container">
            <h2 class="section-title text-center"><?php echo $t['transparency_title']; ?></h2>
            <p class="section-subtitle text-center"><?php echo $t['transparency_sub']; ?></p>
            <div class="grid docs-grid">
                <?php if (count($documentos) > 0): ?>
                    <?php foreach ($documentos as $doc): ?>
                        <a href="<?php echo htmlspecialchars($doc['caminho_arquivo']); ?>" target="_blank" class="doc-card">
                            <i class="<?php echo htmlspecialchars($doc['icone']); ?>"></i>
                            <span><?php echo htmlspecialchars(traduzirTextoDinamico($doc['nome'], $lang)); ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center" style="grid-column: 1 / -1; color: var(--text-muted);">Os laudos e orçamentos serão disponibilizados aqui em breve.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section bg-light">
        <div class="container max-w-800">
            <h2 class="section-title text-center"><?php echo $t['updates_title']; ?></h2>
            <div class="timeline" id="timelineMural">
                <?php if (count($atualizacoes) > 0): ?>
                    <?php 
                    // Limites para brilhar
                    $limite_doacao_grande = 100.00; 
                    $limite_diamante = 500.00;
                    
                    foreach ($atualizacoes as $index => $atualizacao): 
                        $dataObj = new DateTime($atualizacao['data_publicacao']);
                        $dataBR = $dataObj->format('d/m/Y');
                        
                        // LÓGICA PARA IDENTIFICAR DOAÇÃO GRANDE
                        $is_big_donation = false;
                        $is_diamond_donation = false;
                        $valorStr = '';
                        if (preg_match('/doar R\$ ([0-9]+(?:\.[0-9]{3})*,[0-9]{2})/', $atualizacao['descricao'], $matches)) {
                            $valorStr = $matches[1];
                            $valorLimpo = str_replace('.', '', $valorStr);
                            $valorLimpo = str_replace(',', '.', $valorLimpo);
                            $valor = (float)$valorLimpo;
                            if ($valor >= $limite_diamante) {
                                $is_diamond_donation = true;
                            } elseif ($valor >= $limite_doacao_grande) {
                                $is_big_donation = true;
                            }
                        }

                        // LÓGICA PARA IDENTIFICAR MÉTODO DE PAGAMENTO (PIX ou MP)
                        $metodo_badge = '';
                        if (stripos($atualizacao['descricao'], 'pix') !== false) {
                            $metodo_badge = '<span class="badge-metodo pix"><i class="fab fa-pix"></i> PIX</span>';
                        } elseif (stripos($atualizacao['descricao'], 'mercado pago') !== false || stripos($atualizacao['descricao'], 'mercadopago') !== false || stripos($atualizacao['descricao'], 'cartão') !== false) {
                            $metodo_badge = '<span class="badge-metodo mp"><i class="fas fa-credit-card"></i> Mercado Pago</span>';
                        }

                        // CLASSES CSS
                        $hide_class = ($index >= 10) ? 'hidden-update' : '';

                        $card_class = '';
                        $dot_class = '';
                        $icon_html = '';
                        $bg_icon_html = '';

                        if ($is_diamond_donation) {
                            $card_class = 'highlight-diamond';
                            $dot_class = 'dot-diamond';
                            $icon_html = '<i class="fas fa-gem" style="color: #0284C7; margin-right: 5px;"></i>';
                            $bg_icon_html = '<i class="fas fa-gem medal-icon" style="color: #BAE6FD;"></i>';
                        } elseif ($is_big_donation) {
                            $card_class = 'highlight-gold';
                            $dot_class = 'dot-gold';
                            $icon_html = '<i class="fas fa-crown" style="color: #D97706; margin-right: 5px;"></i>';
                            $bg_icon_html = '<i class="fas fa-medal medal-icon"></i>';
                        }
                    ?>
                        
                        <div class="timeline-item timeline-entry <?php echo $hide_class; ?>">
                            <div class="timeline-dot <?php echo $dot_class; ?>"></div>
                            <div class="timeline-date"><?php echo $dataBR; ?></div>
                            
                            <div class="timeline-content <?php echo $card_class; ?>">
                                <h3>
                                    <?php echo $icon_html; ?>
                                    <?php echo htmlspecialchars(traduzirTextoDinamico($atualizacao['titulo'], $lang)); ?>
                                </h3>
                                
                                <p>
                                    <?php if($is_big_donation || $is_diamond_donation): ?>
                                        <?php 
                                            $destaque_valor = '<strong>R$ ' . $valorStr . ' ' . $metodo_badge . '</strong>';
                                            $descricao_destacada = str_replace('R$ ' . $valorStr, $destaque_valor, $atualizacao['descricao']);
                                            // Limpa as palavras chave do texto original para não ficar repetitivo
                                            $descricao_destacada = str_ireplace(['via pix', 'pelo pix', 'via mercado pago', 'pelo mercado pago'], '', $descricao_destacada);
                                            echo nl2br(traduzirTextoDinamico($descricao_destacada, $lang));
                                        ?>
                                        <?php echo $bg_icon_html; ?>
                                    <?php else: ?>
                                        <?php 
                                            // Se não for doação grande, ainda tenta colocar o badge se achar as palavras
                                            $desc_normal = htmlspecialchars($atualizacao['descricao']);
                                            $desc_normal = traduzirTextoDinamico($desc_normal, $lang);
                                            $desc_normal = nl2br($desc_normal);
                                            if($metodo_badge != ''){
                                                $desc_normal .= " " . $metodo_badge;
                                                $desc_normal = str_ireplace(['via pix', 'pelo pix', 'via mercado pago', 'pelo mercado pago'], '', $desc_normal);
                                            }
                                            echo $desc_normal;
                                        ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center" style="color: var(--text-muted);"><?php echo $t['updates_empty']; ?></p>
                <?php endif; ?>
            </div>

            <?php if (count($atualizacoes) > 10): ?>
                <div class="text-center" style="margin-top: 30px;">
                    <button id="btnLoadMoreUpdates" class="btn btn-outline" style="border-color: var(--secondary); color: var(--secondary);"><?php echo $t['btn_load_more']; ?> <i class="fas fa-chevron-down"></i></button>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <footer class="footer">
        <div class="container footer-content">
            <div class="footer-info">
                <h3><?php echo $t['footer_title']; ?></h3>
                <p><?php echo $t['footer_desc']; ?></p>
            </div>
            <div class="social-links">
                <a href="#" onclick="compartilharZap()" class="social-btn whatsapp"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>
    </footer>

    <div class="share-bar-fixed d-mobile-only">
        <a href="#doar" class="btn btn-donate-full">
            <i class="fas fa-heart"></i> <?php echo $t['btn_donate_mobile']; ?>
        </a>
        <button onclick="compartilharZap()" class="btn btn-whatsapp-full">
            <i class="fab fa-whatsapp"></i> <?php echo $t['btn_share_mobile']; ?>
        </button>
    </div>

    <!-- Modal de Vídeo -->
    <div class="video-modal" id="videoModal">
        <div class="video-modal-content">
            <div class="video-modal-close" onclick="closeVideoModal()"><i class="fas fa-times"></i></div>
            <div class="video-modal-iframe-container" id="videoModalContainer">
                <!-- O iframe será injetado aqui pelo JS -->
            </div>
        </div>
    </div>

    <script src="gi.js?v=1.1"></script>
</body>
</html>