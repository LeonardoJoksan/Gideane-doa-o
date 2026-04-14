<?php
// acoes.php
session_start();
require 'conexao.php';

header('Content-Type: application/json');

// Proteção de segurança: só permite executar se estiver logado
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado.']);
    exit;
}

$acao = $_POST['acao'] ?? '';

switch ($acao) {
    case 'salvar_configuracoes':
        $meta = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['meta_total']);
        $arrecadado = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_arrecadado']);
        
        $sql = "UPDATE configuracoes SET 
                meta_total = :meta, 
                valor_arrecadado = :arrecadado, 
                chave_pix = :pix, 
                codigo_pix_copia_cola = :copia_cola, 
                nome_beneficiario = :nome 
                WHERE id = 1";
                
        $stmt = $pdo->prepare($sql);
        $sucesso = $stmt->execute([
            ':meta' => (float)$meta,
            ':arrecadado' => (float)$arrecadado,
            ':pix' => $_POST['chave_pix'],
            ':copia_cola' => $_POST['codigo_pix_copia_cola'],
            ':nome' => $_POST['nome_beneficiario']
        ]);

        if($sucesso) {
            echo json_encode(['status' => 'success', 'message' => 'Configurações atualizadas com sucesso!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar configurações.']);
        }
        break;

    case 'adicionar_atualizacao':
        $sql = "INSERT INTO atualizacoes (data_publicacao, titulo, descricao) VALUES (:data_pub, :titulo, :descricao)";
        $stmt = $pdo->prepare($sql);
        $sucesso = $stmt->execute([
            ':data_pub' => $_POST['data_publicacao'],
            ':titulo' => $_POST['titulo'],
            ':descricao' => $_POST['descricao']
        ]);

        if($sucesso) {
            echo json_encode(['status' => 'success', 'message' => 'Atualização adicionada ao mural!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao adicionar atualização.']);
        }
        break;
        
    case 'excluir_atualizacao':
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM atualizacoes WHERE id = :id");
        if($stmt->execute([':id' => $id])) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
        break;

    case 'adicionar_documento':
        $nome = $_POST['nome'] ?? '';
        $icone = $_POST['icone'] ?? 'fas fa-file';
        
        // Verifica se o arquivo foi enviado sem erros
        if(isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] == 0) {
            $extensao = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
            $extensoes_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
            
            if(in_array($extensao, $extensoes_permitidas)) {
                // Cria um nome único para não sobrepor arquivos
                $novo_nome = uniqid() . '.' . $extensao;
                $caminho_destino = 'uploads/' . $novo_nome;
                
                if(move_uploaded_file($_FILES['arquivo']['tmp_name'], $caminho_destino)) {
                    $stmt = $pdo->prepare("INSERT INTO documentos (nome, caminho_arquivo, icone) VALUES (:nome, :caminho, :icone)");
                    $sucesso = $stmt->execute([
                        ':nome' => $nome,
                        ':caminho' => $caminho_destino,
                        ':icone' => $icone
                    ]);
                    
                    if($sucesso) {
                        echo json_encode(['status' => 'success', 'message' => 'Documento enviado com sucesso!']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar no banco.']);
                    }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Erro ao mover o arquivo. Verifique as permissões da pasta uploads.']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Formato não permitido. Use PDF, JPG ou PNG.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Nenhum arquivo válido enviado.']);
        }
        break;

    case 'excluir_documento':
        $id = $_POST['id'];
        
        // Primeiro busca o caminho do arquivo para deletar do servidor
        $stmt = $pdo->prepare("SELECT caminho_arquivo FROM documentos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($doc) {
            // Apaga o arquivo físico se ele existir
            if(file_exists($doc['caminho_arquivo'])) {
                unlink($doc['caminho_arquivo']);
            }
            // Apaga do banco de dados
            $stmtDel = $pdo->prepare("DELETE FROM documentos WHERE id = :id");
            if($stmtDel->execute([':id' => $id])) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error']);
            }
        }
        break;

    case 'adicionar_galeria':
        $tipo = $_POST['midia_tipo'] ?? 'imagem';
        $midia_url = '';

        if ($tipo === 'video' || $tipo === 'video_medico') {
            $midia_url = $_POST['midia_video_id'] ?? '';
            if(empty($midia_url)) {
                echo json_encode(['status' => 'error', 'message' => 'Cole o ID do vídeo do YouTube.']);
                exit;
            }
        } else {
            if (isset($_FILES['midia_imagem']) && $_FILES['midia_imagem']['error'] === 0) {
                $extensao = strtolower(pathinfo($_FILES['midia_imagem']['name'], PATHINFO_EXTENSION));
                if (in_array($extensao, ['jpg', 'jpeg', 'png'])) {
                    $midia_url = 'uploads/galeria_' . uniqid() . '.' . $extensao;
                    if (!move_uploaded_file($_FILES['midia_imagem']['tmp_name'], $midia_url)) {
                        echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar a imagem.']);
                        exit;
                    }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Formato de imagem inválido.']);
                    exit;
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Selecione uma imagem.']);
                exit;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO galeria (tipo, midia_url) VALUES (:tipo, :url)");
        if($stmt->execute([':tipo' => $tipo, ':url' => $midia_url])) {
            echo json_encode(['status' => 'success', 'message' => 'Mídia adicionada à galeria!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar no banco.']);
        }
        break;

    case 'excluir_galeria':
        $id = $_POST['id'];
        $stmt = $pdo->prepare("SELECT tipo, midia_url FROM galeria WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $midia = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($midia) {
            // Se for imagem, apaga o arquivo físico do servidor
            if($midia['tipo'] == 'imagem' && file_exists($midia['midia_url'])) {
                unlink($midia['midia_url']);
            }
            $pdo->prepare("DELETE FROM galeria WHERE id = :id")->execute([':id' => $id]);
            echo json_encode(['status' => 'success']);
        }
        break;

    case 'registrar_doacao_manual':
        $nome_doador = $_POST['nome_doador'] ?? 'Um anjo anônimo';
        $valor_doado = (float) $_POST['valor_doado'];

        if ($valor_doado <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'O valor deve ser maior que zero.']);
            exit;
        }

        try {
            // Iniciamos uma transação para garantir que o valor e o mural atualizem juntos
            $pdo->beginTransaction();

            // 1. Atualiza a barra de progresso somando o novo valor
            $stmt = $pdo->prepare("UPDATE configuracoes SET valor_arrecadado = valor_arrecadado + :valor WHERE id = 1");
            $stmt->execute([':valor' => $valor_doado]);

            // 2. Prepara o texto para o Mural de Atualizações
            $valor_formatado = number_format($valor_doado, 2, ',', '.');
            $titulo_mural = "Nova doação recebida! ❤️";
            $descricao_mural = "{$nome_doador} acabou de doar R$ {$valor_formatado}! Cada centavo nos aproxima da cirurgia. Muito obrigado pela ajuda!";

            // 3. Salva a postagem no banco com a data de hoje
            $stmtMural = $pdo->prepare("INSERT INTO atualizacoes (data_publicacao, titulo, descricao) VALUES (CURDATE(), :titulo, :descricao)");
            $stmtMural->execute([
                ':titulo' => $titulo_mural,
                ':descricao' => $descricao_mural
            ]);

            // Finaliza a transação salvando tudo
            $pdo->commit();
            
            echo json_encode(['status' => 'success', 'message' => 'Doação registrada! A barra de progresso subiu e o recado está no mural.']);

        } catch (PDOException $e) {
            $pdo->rollBack(); // Desfaz se der erro
            echo json_encode(['status' => 'error', 'message' => 'Erro ao registrar doação no banco de dados.']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Ação não reconhecida.']);
        break;
}
?>