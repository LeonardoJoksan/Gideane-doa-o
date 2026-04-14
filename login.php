<?php
// login.php
session_start();
require 'conexao.php';

// Se já estiver logado, manda direto pro admin
if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true) {
    header("Location: admin.php");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if (!empty($usuario) && !empty($senha)) {
        $stmt = $pdo->prepare("SELECT id, senha FROM usuarios_admin WHERE usuario = :usuario LIMIT 1");
        $stmt->execute(['usuario' => $usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verifica a senha criptografada
        if ($user && password_verify($senha, $user['senha'])) {
            $_SESSION['admin_logado'] = true;
            $_SESSION['admin_id'] = $user['id'];
            header("Location: admin.php");
            exit;
        } else {
            $erro = "Usuário ou senha incorretos!";
        }
    } else {
        $erro = "Preencha todos os campos!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Administração da Campanha</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { background-color: #0F172A; color: #fff; font-family: 'Poppins', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .login-box { background: #1E293B; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); width: 100%; max-width: 400px; text-align: center; }
        h2 { margin-top: 0; color: #0EA5E9; }
        input { width: 100%; padding: 12px; margin: 10px 0 20px; border: 1px solid #334155; border-radius: 6px; background: #0F172A; color: white; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #0EA5E9; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.3s; }
        button:hover { background: #0284C7; }
        .erro { background: #EF4444; color: white; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Painel de Controle</h2>
        <p>Campanha Ajude a Gi</p>
        
        <?php if ($erro): ?>
            <div class="erro"><?php echo $erro; ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="text" name="usuario" placeholder="Usuário" required>
            <input type="password" name="senha" placeholder="Senha" required>
            <button type="submit">Entrar no Sistema</button>
        </form>
    </div>
</body>
</html>