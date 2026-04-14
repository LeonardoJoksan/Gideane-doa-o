<?php
// processar_mp.php
header('Content-Type: application/json');

// Conecta ao banco de dados
require 'conexao.php'; 

// O seu Access Token do Mercado Pago
$access_token = "APP_USR-7935841359713388-022811-a5cfa425a337429c9574790b1ad636ce-323657460";

// Recebe os dados do frontend
$dados = json_decode(file_get_contents("php://input"), true);

$payment_data = array(
    "transaction_amount" => (float) $dados['transaction_amount'],
    "token" => $dados['token'],
    "description" => "Doação Braquiterapia - Gi",
    "installments" => (int) $dados['installments'],
    "payment_method_id" => $dados['payment_method_id'],
    "issuer_id" => (int) $dados['issuer_id'],
    "payer" => array(
        "email" => $dados['payer']['email'],
        "identification" => array(
            "type" => $dados['payer']['identification']['type'] ?? 'CPF',
            "number" => $dados['payer']['identification']['number'] ?? ''
        )
    )
);

// Requisição para o Mercado Pago
$ch = curl_init("https://api.mercadopago.com/v1/payments");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer " . $access_token,
    "Content-Type: application/json",
    "X-Idempotency-Key: " . uniqid() 
));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// --- LÓGICA ATUALIZADA: MURAL TRANSPARENTE E VALOR LÍQUIDO ---
$resposta_mp = json_decode($response, true);

// Se o pagamento for aprovado
if (isset($resposta_mp['status']) && $resposta_mp['status'] === 'approved') {
    
    // Pega o valor que a pessoa digitou lá na tela
    $valor_doado = (float) $dados['transaction_amount'];
    
    // O Mercado Pago devolve o valor líquido em 'net_received_amount'
    // Se por acaso a API não retornar, o padrão será o valor doado.
    $valor_liquido = isset($resposta_mp['transaction_details']['net_received_amount']) 
                     ? (float) $resposta_mp['transaction_details']['net_received_amount'] 
                     : $valor_doado;
                     
    // A taxa é simplesmente o que foi doado menos o que você realmente recebeu
    $taxa_mp = $valor_doado - $valor_liquido;

    // Tratamento do Nome do Doador
    $nome_completo = isset($dados['donor_name']) && !empty(trim($dados['donor_name'])) ? trim($dados['donor_name']) : 'Anônimo';

    // Lógica para ocultar o sobrenome (Ex: João Silva -> João S.)
    if ($nome_completo !== 'Anônimo' && strtolower($nome_completo) !== 'um anjo anônimo') {
        $partes_nome = explode(' ', $nome_completo);
        $nome_oculto = $partes_nome[0]; // Pega o primeiro nome
        if(count($partes_nome) > 1) {
            $nome_oculto .= ' ' . strtoupper(substr($partes_nome[1], 0, 1)) . '.'; 
        }
    } else {
        $nome_oculto = 'Um anjo anônimo';
    }

    // Formatações de moeda para o texto do Mural
    $valor_formatado = number_format($valor_doado, 2, ',', '.');
    $taxa_formatada = number_format($taxa_mp, 2, ',', '.');
    $liquido_formatado = number_format($valor_liquido, 2, ',', '.');
    
    // Textos para o Mural
    $titulo_mural = "Nova doação recebida! ❤️";
    $descricao_mural = "{$nome_oculto} acabou de doar para a campanha!\n\n"
                     . "Transparência do repasse:\n"
                     . "• Valor Doado: R$ {$valor_formatado}\n"
                     . "• Taxa do Mercado Pago: R$ {$taxa_formatada}\n"
                     . "• Valor Real Arrecadado: R$ {$liquido_formatado}\n\n"
                     . "Cada centavo nos aproxima da cirurgia. Muito obrigado!";

    try {
        // 1. Atualiza a barra de progresso APENAS COM O VALOR LÍQUIDO
        $stmt_update = $pdo->prepare("UPDATE configuracoes SET valor_arrecadado = valor_arrecadado + :valor_liquido WHERE id = 1");
        $stmt_update->execute([':valor_liquido' => $valor_liquido]);

        // 2. Cria a postagem automática no mural
        $stmt_mural = $pdo->prepare("INSERT INTO atualizacoes (data_publicacao, titulo, descricao) VALUES (CURDATE(), :titulo, :descricao)");
        $stmt_mural->execute([
            ':titulo' => $titulo_mural,
            ':descricao' => $descricao_mural
        ]);
    } catch(PDOException $e) {
        // Ignora erro de banco silenciosamente para não quebrar o retorno do MP no frontend
    }
}

// Retorna a resposta do Mercado Pago para o nosso JavaScript concluir a tela
http_response_code($http_code);
echo $response;
?>