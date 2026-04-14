<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doação Segura - Ajude a Gi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="gi.css">
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .donation-container { max-width: 600px; margin: 50px auto; padding: 30px; background: white; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .amount-input-group { display: flex; align-items: center; margin-bottom: 20px; }
        .amount-input-group span { background: #F1F5F9; padding: 15px 20px; border: 1px solid #CBD5E1; border-right: none; border-radius: 8px 0 0 8px; font-weight: 600; color: #334155; font-size: 1.2rem; }
        .amount-input-group input { flex: 1; padding: 15px; border: 1px solid #CBD5E1; border-radius: 0 8px 8px 0; font-size: 1.2rem; font-weight: bold; outline: none; }
        .amount-input-group input:focus { border-color: #0EA5E9; }
        #paymentBrick_container { margin-top: 30px; display: none; } /* Escondido até confirmar o valor */
        .btn-confirm { width: 100%; background: #0EA5E9; color: white; padding: 15px; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-confirm:hover { background: #0284C7; }
        .header-back { display: block; text-align: center; margin-bottom: 20px; color: #64748B; text-decoration: none; }
        .header-back:hover { color: #0EA5E9; }
    </style>
</head>
<body style="background-color: #F8FAFC;">

    <div class="donation-container">
        <a href="index.php" class="header-back"><i class="fas fa-arrow-left"></i> Voltar para a página principal</a>
        
        <h2 style="text-align: center; color: #0F172A; margin-bottom: 10px;">Fazer Doação</h2>
        <p style="text-align: center; color: #64748B; margin-bottom: 30px;">Pagamento 100% seguro processado pelo Mercado Pago.</p>

        <div id="step-amount">
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">Seu Nome (Como quer aparecer no mural)</label>
                <input type="text" id="donorName" placeholder="Ex: João Silva (ou deixe em branco para Anônimo)" style="width: 100%; padding: 15px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 1.1rem; box-sizing: border-box;">
            </div>

            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">Qual valor você deseja doar?</label>
            <div class="amount-input-group">
                <span>R$</span>
                <input type="number" id="donationAmount" placeholder="50,00" min="5" step="0.01">
            </div>
            <button class="btn-confirm" id="btnConfirmAmount">Continuar para o Pagamento</button>
        </div>

        <div id="paymentBrick_container"></div>
    </div>

    <script>
        // Inicializa o Mercado Pago com a sua Public Key
        const mp = new MercadoPago('APP_USR-90bf1d94-0724-4a66-8353-8a3e5f59e1c5', {
            locale: 'pt-BR'
        });
        
        const bricksBuilder = mp.bricks();
        let paymentBrickController = null;

        document.getElementById('btnConfirmAmount').addEventListener('click', async function() {
            const amountInput = document.getElementById('donationAmount').value;
            const amount = parseFloat(amountInput.replace(',', '.'));

            if (isNaN(amount) || amount < 5) {
                Swal.fire('Atenção', 'Por favor, insira um valor válido (mínimo R$ 5,00).', 'warning');
                return;
            }

            // Esconde a etapa de valor e mostra a div do Brick
            document.getElementById('step-amount').style.display = 'none';
            document.getElementById('paymentBrick_container').style.display = 'block';

            // Configurações do Brick com o valor escolhido
            const settings = {
                initialization: {
                    amount: amount, // Valor que o usuário digitou
                },
                customization: {
                    visual: {
                        style: {
                            theme: "default",
                        }
                    },
                    paymentMethods: {
                        creditCard: "all",
                        pix: "all", // Permite PIX nativo do Mercado Pago
                        ticket: "all" // Permite Boleto
                    },
                },
                callbacks: {
                    onReady: () => {
                        // Brick carregado com sucesso
                    },
                    onSubmit: ({ selectedPaymentMethod, formData }) => {
                        return new Promise((resolve, reject) => {
                            // Pega o nome digitado ou define como Anônimo
                            let nomeDoador = document.getElementById('donorName').value.trim();
                            if(nomeDoador === "") nomeDoador = "Um anjo anônimo";
                            
                            // Adiciona o nome aos dados que vão para o PHP
                            formData.donor_name = nomeDoador;

                            fetch("processar_mp.php", {
                                method: "POST",
                                headers: { "Content-Type": "application/json" },
                                body: JSON.stringify(formData),
                            })
                            .then((response) => response.json())
                            .then((response) => {
                                resolve();
                                // Trata a resposta do Mercado Pago
                                if (response.status === "approved" || response.status === "pending") {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Muito Obrigado!',
                                        text: 'Sua doação foi registrada com sucesso.',
                                        confirmButtonText: 'Voltar ao Início'
                                    }).then(() => {
                                        window.location.href = 'index.php';
                                    });
                                } else {
                                    Swal.fire('Ops!', 'Ocorreu um problema com o pagamento. Status: ' + response.status, 'error');
                                }
                            })
                            .catch((error) => {
                                reject();
                                Swal.fire('Erro!', 'Falha ao processar o pagamento. Tente novamente.', 'error');
                            });
                        });
                    },
                    onError: (error) => {
                        console.error(error);
                        Swal.fire('Erro!', 'Ocorreu um erro ao carregar o pagamento.', 'error');
                    },
                },
            };

            // Renderiza o Brick
            paymentBrickController = await bricksBuilder.create(
                "payment",
                "paymentBrick_container",
                settings
            );
        });
    </script>
</body>
</html>