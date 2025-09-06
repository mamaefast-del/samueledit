<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}
require 'db.php';
    $stmt = $pdo->query("
        SELECT 
            COUNT(CASE WHEN conta_demo != 1 OR conta_demo IS NULL THEN 1 END) as total_usuarios,
            SUM(CASE WHEN conta_demo != 1 OR conta_demo IS NULL THEN saldo ELSE 0 END) as saldo_total,
            COUNT(CASE WHEN (conta_demo != 1 OR conta_demo IS NULL) AND saldo > 0 THEN 1 END) as usuarios_com_saldo
        FROM usuarios
    ");
    $stats_usuarios = $stmt->fetch(PDO::FETCH_ASSOC);
    $usuarios = $stats_usuarios['total_usuarios'];

// Soma o valor de todos os PIX com status 'pendente'
$total_pix_pendente = $pdo->query("
    SELECT SUM(t.valor) 
    FROM transacoes_pix t
    JOIN usuarios u ON u.id = t.usuario_id
    WHERE t.status = 'pendente'
    AND (u.conta_demo != 1 OR u.conta_demo IS NULL)
")->fetchColumn();

// Formata como moeda
$total_pix_pendente_raw = $total_pix_pendente ?? 0;
$total_depositos_pagos_raw = $pdo->query("
    SELECT SUM(t.valor) 
    FROM transacoes_pix t
    JOIN usuarios u ON u.id = t.usuario_id
    WHERE t.status = 'aprovado'
    AND (u.conta_demo != 1 OR u.conta_demo IS NULL)
")->fetchColumn() ?? 0;

// Soma ambos para obter o total geral (pendente + aprovado)
$total_depositos_gerados_raw = $total_pix_pendente_raw + $total_depositos_pagos_raw;

// Formata os valores
$total_pix_pendente = number_format($total_pix_pendente_raw, 2, ',', '.');
$total_depositos_pagos = number_format($total_depositos_pagos_raw, 2, ',', '.');
$total_depositos_gerados = number_format($total_depositos_gerados_raw, 2, ',', '.');


// Soma o valor de todos os saques com status 'recusado' ou 'pendente'
$total_saques_recusados = $pdo->query("
    SELECT SUM(s.valor) 
    FROM saques s
    JOIN usuarios u ON u.id = s.usuario_id
    WHERE s.status IN ('recusado', 'pendente')
    AND (u.conta_demo != 1 OR u.conta_demo IS NULL)
")->fetchColumn();

// Formata como moeda
$total_saques_recusados = number_format($total_saques_recusados ?? 0, 2, ',', '.');

// Soma o valor de todos os saques com status 'aprovado'
$total_saques_pagos = $pdo->query("
    SELECT SUM(s.valor) 
    FROM saques s
    JOIN usuarios u ON u.id = s.usuario_id
    WHERE s.status = 'aprovado'
    AND (u.conta_demo != 1 OR u.conta_demo IS NULL)
")->fetchColumn();

// Formata como moeda
$total_saques_pagos = number_format($total_saques_pagos ?? 0, 2, ',', '.');

// Soma o valor de todos os depósitos com status 'aprovado'
$total_depositos_pagos = $pdo->query("
    SELECT SUM(t.valor) 
    FROM transacoes_pix t
    JOIN usuarios u ON u.id = t.usuario_id
    WHERE t.status = 'aprovado'
    AND (u.conta_demo != 1 OR u.conta_demo IS NULL)
")->fetchColumn();

// Formata como moeda
$total_depositos_pagos = number_format($total_depositos_pagos ?? 0, 2, ',', '.');



// Upload dos banners (usando JSON dinâmico)
// Caminhos e variáveis comuns
$jsonFile = __DIR__ . '/imagens_menu.json';
$diretorio = __DIR__ . '/images';
$formatosPermitidos = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

if (!is_dir($diretorio)) {
    mkdir($diretorio, 0755, true);
}

$dadosJson = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

// Função para salvar imagem e atualizar JSON
function salvarImagem($alvo, $arquivo, &$mensagem, &$dadosJson, $jsonFile, $diretorio, $formatosPermitidos) {
    if ($arquivo['error'] === 0) {
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));

        if (in_array($extensao, $formatosPermitidos)) {
            $nomeFinal = "$alvo.$extensao";
            $destino = "$diretorio/$nomeFinal";

            if (move_uploaded_file($arquivo['tmp_name'], $destino)) {
                $dadosJson[$alvo] = $nomeFinal;
                file_put_contents($jsonFile, json_encode($dadosJson, JSON_PRETTY_PRINT));
                $mensagem = "<p style='color:#fbce00;'><strong>✅ Imagem atualizada com sucesso!</strong></p>";
            } else {
                $mensagem = "<p style='color:#ff6b6b;'><strong>❌ Erro ao mover o arquivo!</strong></p>";
            }
        } else {
            $mensagem = "<p style='color:#ff6b6b;'><strong>❌ Formato não permitido! (png, jpg, jpeg, webp, gif)</strong></p>";
        }
    } else {
        $mensagem = "<p style='color:#ff6b6b;'><strong>❌ Erro no envio do arquivo.</strong></p>";
    }
}

// Processamento do upload de banner
$msgBanner = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alvo'], $_FILES['imagem'])) {
    $alvo = $_POST['alvo'];
    if (in_array($alvo, ['banner1', 'banner2'])) {
        salvarImagem($alvo, $_FILES['imagem'], $msgBanner, $dadosJson, $jsonFile, $diretorio, $formatosPermitidos);
    } else {
        $msgBanner = "<p style='color:#ff6b6b;'><strong>❌ Alvo de banner inválido.</strong></p>";
    }
}

// Processamento do upload de imagem do menu
$msgMenu = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alvo_menu'], $_FILES['imagem_menu'], $_POST['enviar_menu'])) {
    $alvoMenu = $_POST['alvo_menu'];
    if (in_array($alvoMenu, ['menu1', 'menu2', 'menu3', 'menu4', 'menu5'])) {
        salvarImagem($alvoMenu, $_FILES['imagem_menu'], $msgMenu, $dadosJson, $jsonFile, $diretorio, $formatosPermitidos);
    } else {
        $msgMenu = "<p style='color:#ff6b6b;'><strong>❌ Alvo do menu inválido.</strong></p>";
    }
}

// Upload da logo
$msgLogo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alvo_logo'], $_FILES['imagem_logo'], $_POST['enviar_logo'])) {
    $alvoLogo = $_POST['alvo_logo'];
    if ($alvoLogo === 'logo') {
        salvarImagem($alvoLogo, $_FILES['imagem_logo'], $msgLogo, $dadosJson, $jsonFile, $diretorio, $formatosPermitidos);
    } else {
        $msgLogo = "<p style='color:#ff6b6b;'><strong>❌ Alvo de logo inválido.</strong></p>";
    }
}


// Upload das imagens da raspadinha

// Carrega imagens da raspadinha (se existirem)
$imgJson = __DIR__ . '/images/imagens_raspadinha.json';
$imagensRaspadinha = file_exists($imgJson) ? json_decode(file_get_contents($imgJson), true) : [];

$imgRaspeAqui = isset($imagensRaspadinha['raspe_aqui']) ? $imagensRaspadinha['raspe_aqui'] : 'RASPE-AQUI.png';
$imgBannerGame = isset($imagensRaspadinha['banner_game']) ? $imagensRaspadinha['banner_game'] : 'banner-game.png';


$msgRaspadinha = '';
$formatosPermitidos = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alvo_raspadinha']) && isset($_FILES['imagem_raspadinha'])) {
    $alvo = $_POST['alvo_raspadinha'];
    $imagem = $_FILES['imagem_raspadinha'];

    if (in_array($alvo, ['raspe_aqui', 'banner_game']) && $imagem['error'] === 0) {
        $extensao = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));

        if (in_array($extensao, $formatosPermitidos)) {
            $nomeArquivo = "$alvo.$extensao";
            $diretorio = __DIR__ . "/images";

            if (!is_dir($diretorio)) mkdir($diretorio, 0755, true);

            if (move_uploaded_file($imagem['tmp_name'], "$diretorio/$nomeArquivo")) {
                $jsonFile = __DIR__ . '/images/imagens_raspadinha.json';
                $dados = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
                $dados[$alvo] = $nomeArquivo;
                file_put_contents($jsonFile, json_encode($dados, JSON_PRETTY_PRINT));

                $msgRaspadinha = "<p style='color:#fbce00;'>✅ Imagem atualizada com sucesso!</p>";
            } else {
                $msgRaspadinha = "<p style='color:#ff6b6b;'>❌ Erro ao mover o arquivo!</p>";
            }
        } else {
            $msgRaspadinha = "<p style='color:#ff6b6b;'>❌ Formato inválido!</p>";
        }
    }
}

// Total apostado
$total_apostado = $pdo->query("
    SELECT SUM(h.valor_apostado) 
    FROM historico_jogos h
    JOIN usuarios u ON u.id = h.usuario_id
    WHERE (u.conta_demo != 1 OR u.conta_demo IS NULL)
")->fetchColumn();
$total_apostado = number_format($total_apostado ?? 0, 2, ',', '.');

// Total ganho pelos usuários
$total_ganho = $pdo->query("
    SELECT SUM(h.valor_premiado) 
    FROM historico_jogos h
    JOIN usuarios u ON u.id = h.usuario_id
    WHERE (u.conta_demo != 1 OR u.conta_demo IS NULL)
")->fetchColumn();
$total_ganho = number_format($total_ganho ?? 0, 2, ',', '.');

// Total perdido (apostas - prêmios)
$total_perdido = $pdo->query("
    SELECT SUM(h.valor_apostado - h.valor_premiado) 
    FROM historico_jogos h
    JOIN usuarios u ON u.id = h.usuario_id
    WHERE (u.conta_demo != 1 OR u.conta_demo IS NULL)
")->fetchColumn();
$total_perdido = number_format($total_perdido ?? 0, 2, ',', '.');

// Lucro bruto da plataforma (mesmo cálculo que perda)
$lucro_bruto = $total_perdido;

// Pega cadastros de hoje
$hoje = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM usuarios 
    WHERE DATE(data_cadastro) = ? 
    AND (conta_demo != 1 OR conta_demo IS NULL)
");
$stmt->execute([$hoje]);
$cadastrosHoje = $stmt->fetchColumn();

$depositosPorDia = [];
for ($i = 6; $i >= 0; $i--) {
    $data = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("
        SELECT SUM(t.valor) 
        FROM transacoes_pix t
        JOIN usuarios u ON u.id = t.usuario_id
        WHERE t.status = 'aprovado' 
        AND DATE(t.criado_em) = ?
        AND (u.conta_demo != 1 OR u.conta_demo IS NULL)
    ");
    $stmt->execute([$data]);
    $totalDia = $stmt->fetchColumn() ?? 0;
    $depositosPorDia[$data] = (float)$totalDia;
}

$datas = array_keys($depositosPorDia);
$valores = array_values($depositosPorDia);

$cadastrosPorDia = [];
for ($i = 6; $i >= 0; $i--) {
    $data = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM usuarios 
        WHERE DATE(data_cadastro) = ?
        AND (conta_demo != 1 OR conta_demo IS NULL)
    ");
    $stmt->execute([$data]);
    $totalCadastros = $stmt->fetchColumn() ?? 0;
    $cadastrosPorDia[$data] = (int)$totalCadastros;
}

$datasCadastros = array_keys($cadastrosPorDia);
$valoresCadastros = array_values($cadastrosPorDia);


?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Controle - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0a0b0f;
            --bg-panel: #111318;
            --bg-card: #1a1d24;
            --primary-gold: #fbce00;
            --primary-green: #00d4aa;
            --text-light: #ffffff;
            --text-muted: #8b949e;
            --border-color: #21262d;
            --success-color: #22c55e;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-dark);
            color: var(--text-light);
            min-height: 100vh;
        }

        .header {
            background: var(--bg-panel);
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            background: linear-gradient(135deg, var(--primary-gold), var(--primary-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary { 
            background: linear-gradient(135deg, var(--primary-gold), var(--primary-green)); 
            color: #000; 
            box-shadow: 0 4px 16px rgba(251, 206, 0, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(251, 206, 0, 0.4);
        }
        .btn-secondary { background: var(--bg-card); color: var(--text-light); border: 1px solid var(--border-color); }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .nav-item {
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-decoration: none;
            color: var(--text-light);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .nav-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            border-color: var(--primary-green);
        }

        .nav-item.active {
            background: linear-gradient(135deg, var(--primary-green), var(--primary-gold));
            color: #000;
            box-shadow: 0 8px 32px rgba(0, 212, 170, 0.3);
        }

        .nav-icon {
            font-size: 24px;
            margin-bottom: 12px;
            display: block;
        }

        .nav-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .nav-desc {
            font-size: 12px;
            opacity: 0.7;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-panel);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        }

        .stat-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary-green);
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 14px;
        }

        .charts-section {
            margin: 40px 0;
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
        }

        .charts-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .charts-header h2 {
            color: var(--primary-gold);
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .charts-header p {
            color: var(--text-muted);
            font-size: 16px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }

        .chart-container {
            background: var(--bg-dark);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            position: relative;
            height: 350px;
        }

        .chart-container h3 {
            color: var(--primary-gold);
            font-size: 18px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .chart-container canvas {
            max-height: 280px !important;
        }

        .admin-forms {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-top: 30px;
        }

        .form-card {
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s ease;
        }

        .form-card:hover {
            border-color: var(--primary-green);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }

        .form-card h3 {
            color: var(--primary-gold);
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-card label {
            color: var(--text-light);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
        }

        .form-card input[type="file"],
        .form-card select {
            width: 100%;
            padding: 14px 16px;
            margin-bottom: 16px;
            background: var(--bg-dark);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-light);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-card input[type="file"]:focus,
        .form-card select:focus {
            border-color: var(--primary-green);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 212, 170, 0.15);
        }

        .form-card button {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, var(--primary-gold), var(--primary-green));
            color: #000;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 16px rgba(251, 206, 0, 0.3);
        }

        .form-card button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(251, 206, 0, 0.4);
        }

        .form-card button:active {
            transform: translateY(0);
        }

        .preview-section {
            margin: 16px 0;
            padding: 16px;
            background: var(--bg-dark);
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }

        .preview-section strong {
            color: var(--primary-gold);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .preview-section img {
            max-width: 100%;
            border: 2px solid var(--primary-green);
            border-radius: 8px;
            margin-top: 8px;
            box-shadow: 0 4px 16px rgba(0, 212, 170, 0.3);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .admin-forms {
                grid-template-columns: 1fr;
            }

            .chart-container {
                height: 300px;
            }

            .nav-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .nav-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>
            <i class="fas fa-tachometer-alt"></i>
            Painel de Controle
        </h1>
        <a href="configuracoes_admin.php" class="btn btn-secondary">
            <i class="fas fa-cog"></i>
            Configurações
        </a>
    </div>

    <div class="container">
        <!-- Navigation -->
        <div class="nav-grid">
            <a href="painel_admin.php" class="nav-item active">
                <i class="fas fa-chart-line nav-icon"></i>
                <div class="nav-title">Dashboard</div>
                <div class="nav-desc">Visão geral e métricas</div>
            </a>

            <a href="usuarios_admin.php" class="nav-item">
                <i class="fas fa-users nav-icon"></i>
                <div class="nav-title">Usuários</div>
                <div class="nav-desc">Gerenciamento de contas</div>
            </a>

            <a href="premios_admin.php" class="nav-item">
                <i class="fas fa-gift nav-icon"></i>
                <div class="nav-title">Prêmios</div>
                <div class="nav-desc">Biblioteca de prêmios</div>
            </a>

            <a href="saques_admin.php" class="nav-item">
                <i class="fas fa-money-bill-wave nav-icon"></i>
                <div class="nav-title">Saques</div>
                <div class="nav-desc">Solicitações de saque</div>
            </a>

            <a href="pix_admin.php" class="nav-item">
                <i class="fas fa-exchange-alt nav-icon"></i>
                <div class="nav-title">Transações</div>
                <div class="nav-desc">PIX e depósitos</div>
            </a>

            <a href="afiliados_admin.php" class="nav-item">
                <i class="fas fa-handshake nav-icon"></i>
                <div class="nav-title">Afiliados</div>
                <div class="nav-desc">Sistema de indicações</div>
            </a>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($usuarios, 0, ',', '.'); ?></div>
                <div class="stat-label"><i class="fas fa-users"></i> Total Usuários</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $cadastrosHoje ?></div>
                <div class="stat-label"><i class="fas fa-user-plus"></i> Cadastros Hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ <?= $total_ganho ?></div>
                <div class="stat-label"><i class="fas fa-trophy"></i> Total Ganhos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ <?= $total_depositos_gerados ?></div>
                <div class="stat-label"><i class="fas fa-coins"></i> Depósitos Gerados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ <?= $lucro_bruto ?></div>
                <div class="stat-label"><i class="fas fa-chart-line"></i> Lucro Bruto</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ <?= $total_perdido ?></div>
                <div class="stat-label"><i class="fas fa-minus-circle"></i> Total Perdas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ <?= $total_depositos_pagos ?></div>
                <div class="stat-label"><i class="fas fa-check-circle"></i> Depósitos Pagos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ <?= $total_saques_pagos ?></div>
                <div class="stat-label"><i class="fas fa-hand-holding-usd"></i> Saques Pagos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ <?= $total_apostado ?></div>
                <div class="stat-label"><i class="fas fa-dice"></i> Total Apostado</div>
            </div>
        </div>

        <!-- Seção de Gráficos -->
        <div class="charts-section">
            <div class="charts-header">
                <h2><i class="fas fa-chart-line"></i> Análise dos Últimos 7 Dias</h2>
                <p>Acompanhe o desempenho da plataforma em tempo real</p>
            </div>
            <div class="charts-grid">
                <div class="chart-container">
                    <h3><i class="fas fa-money-bill"></i> Depósitos Aprovados</h3>
                    <canvas id="depositosChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3><i class="fas fa-user-plus"></i> Novos Cadastros</h3>
                    <canvas id="cadastrosChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Formulários de Administração -->
        <div class="admin-forms">
            <!-- Upload de Logo -->
            <div class="form-card">
                <h3><i class="fas fa-image"></i> Logo da Plataforma</h3>
                <?= $msgLogo ?? '' ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="alvo_logo" value="logo">

                    <div id="preview-logo" class="preview-section">
                        <?php
                        $logo = isset($dadosJson['logo']) ? $dadosJson['logo'] : 'logo.png';
                        echo '<strong>Logo atual:</strong><br><img src="images/' . $logo . '?' . time() . '">';
                        ?>
                    </div>

                    <label for="imagem_logo">Nova Logo (600x200):</label>
                    <input type="file" name="imagem_logo" accept="image/*" required>

                    <button type="submit" name="enviar_logo">
                        <i class="fas fa-upload"></i>
                        Atualizar Logo
                    </button>
                </form>
            </div>

            <!-- Upload de Imagens do Menu -->
            <div class="form-card">
                <h3><i class="fas fa-boxes"></i> Imagens do Menu</h3>
                <?= $msgMenu ?? '' ?>
                <form method="post" enctype="multipart/form-data">
                    <label for="alvo_menu">Selecionar imagem:</label>
                    <select name="alvo_menu" id="alvo_menu" required onchange="mostrarPreviewMenu()">
                        <option value="menu1" selected>Caixas R$1</option>
                        <option value="menu2">Caixas R$2</option>
                        <option value="menu3">Caixas R$5</option>
                        <option value="menu4">Caixas R$25</option>
                        <option value="menu5">Caixas R$50</option>
                    </select>

                    <div id="preview-menu" class="preview-section"></div>

                    <label for="imagem_menu">Nova Imagem:</label>
                    <input type="file" name="imagem_menu" accept="image/*" required>

                    <button type="submit" name="enviar_menu">
                        <i class="fas fa-upload"></i>
                        Atualizar Imagem
                    </button>
                </form>
            </div>

            <!-- Upload de Banner -->
            <div class="form-card">
                <h3><i class="fas fa-panorama"></i> Banners da Plataforma</h3>
                <?= $msgBanner ?>
                <form method="POST" enctype="multipart/form-data">
                    <label for="alvo">Selecionar banner:</label>
                    <select name="alvo" id="alvo" required onchange="mostrarPreview()">
                        <option value="banner1" selected>Banner Principal (Topo)</option>
                    </select>

                    <div id="preview-area" class="preview-section"></div>

                    <label for="imagem">Nova Imagem:</label>
                    <input type="file" name="imagem" accept="image/*" required>

                    <button type="submit">
                        <i class="fas fa-upload"></i>
                        Atualizar Banner
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Gráfico de Depósitos
        const ctx = document.getElementById('depositosChart').getContext('2d');
        const labels = <?= json_encode($datas) ?>;
        const dataValores = <?= json_encode($valores) ?>;

        const depositosChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels.map(date => {
                    const d = new Date(date);
                    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                }),
                datasets: [{
                    label: 'Depósitos Aprovados (R$)',
                    data: dataValores,
                    borderColor: '#00d4aa',
                    backgroundColor: 'rgba(0, 212, 170, 0.15)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    borderWidth: 2,
                    pointBackgroundColor: '#00d4aa',
                    pointBorderColor: '#0a0b0f',
                    pointBorderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2.5,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(17, 19, 24, 0.95)',
                        titleColor: '#00d4aa',
                        bodyColor: '#ffffff',
                        borderColor: '#00d4aa',
                        borderWidth: 1,
                        cornerRadius: 8,
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: '#8b949e',
                            font: { size: 12 }
                        },
                        grid: {
                            color: 'rgba(139, 148, 158, 0.1)',
                        }
                    },
                    y: {
                        ticks: {
                            color: '#8b949e',
                            font: { size: 12 },
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                            }
                        },
                        grid: {
                            color: 'rgba(139, 148, 158, 0.1)',
                        },
                        beginAtZero: true,
                    }
                }
            }
        });

        // Gráfico de Cadastros
        const ctxCadastros = document.getElementById('cadastrosChart').getContext('2d');
        const labelsCadastros = <?= json_encode($datasCadastros) ?>;
        const dataCadastros = <?= json_encode($valoresCadastros) ?>;

        const cadastrosChart = new Chart(ctxCadastros, {
            type: 'bar',
            data: {
                labels: labelsCadastros.map(date => {
                    const d = new Date(date);
                    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                }),
                datasets: [{
                    label: 'Cadastros por Dia',
                    data: dataCadastros,
                    backgroundColor: 'rgba(0, 212, 170, 0.8)',
                    borderColor: '#00d4aa',
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2.5,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(17, 19, 24, 0.95)',
                        titleColor: '#00d4aa',
                        bodyColor: '#ffffff',
                        borderColor: '#00d4aa',
                        borderWidth: 1,
                        cornerRadius: 8,
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: '#8b949e',
                            font: { size: 12 }
                        },
                        grid: {
                            color: 'rgba(139, 148, 158, 0.1)',
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#8b949e',
                            font: { size: 12 },
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(139, 148, 158, 0.1)',
                        }
                    }
                }
            }
        });

        // Funções de Preview
        function mostrarPreview() {
            const alvo = document.getElementById('alvo').value;
            const preview = document.getElementById('preview-area');

            if (!alvo) {
                preview.innerHTML = '';
                return;
            }

            fetch('imagens_menu.json')
                .then(res => res.json())
                .then(data => {
                    const nome = data[alvo] || `${alvo}.png`;
                    preview.innerHTML = `
                        <strong>Banner atual:</strong><br>
                        <img src="images/${nome}?v=${Date.now()}">
                    `;
                })
                .catch(() => {
                    preview.innerHTML = `
                        <strong>Banner atual:</strong><br>
                        <img src="images/${alvo}.png?v=${Date.now()}">
                    `;
                });
        }

        function mostrarPreviewMenu() {
            const alvo = document.getElementById('alvo_menu').value;
            const preview = document.getElementById('preview-menu');

            if (!alvo) {
                preview.innerHTML = '';
                return;
            }

            fetch('imagens_menu.json')
                .then(res => res.json())
                .then(data => {
                    const nome = data[alvo] || `${alvo}.png`;
                    preview.innerHTML = `
                        <strong>Imagem atual:</strong><br>
                        <img src="images/${nome}?v=${Date.now()}">
                    `;
                })
                .catch(() => {
                    preview.innerHTML = `
                        <strong>Imagem atual:</strong><br>
                        <img src="images/${alvo}.png?v=${Date.now()}">
                    `;
                });
        }

        // Inicializar previews ao carregar a página
        document.addEventListener("DOMContentLoaded", () => {
            if (document.getElementById('alvo')) mostrarPreview();
            if (document.getElementById('alvo_menu')) mostrarPreviewMenu();
        });

        // Auto-atualizar gráficos a cada 30 segundos
        setInterval(() => {
            depositosChart.update();
            cadastrosChart.update();
        }, 30000);
    </script>
</body>
</html>