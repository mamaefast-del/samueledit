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
    <title>Dashboard Executivo - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0a0b0f;
            --bg-panel: #111318;
            --bg-card: #1a1d24;
            --primary-green: #00d4aa;
            --primary-gold: #fbce00;
            --text-light: #ffffff;
            --text-muted: #8b949e;
            --border-color: #21262d;
            --success-color: #22c55e;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --purple-color: #8b5cf6;
            --radius: 12px;
            --shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--bg-dark) 0%, #0d1117 100%);
            color: var(--text-light);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Header */
        .header {
            background: rgba(17, 19, 24, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 24px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 800;
            color: var(--primary-green);
            text-decoration: none;
            transition: var(--transition);
        }

        .logo:hover {
            transform: scale(1.05);
            filter: brightness(1.2);
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary-green), var(--primary-gold));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #000;
        }

        .header-stats {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            color: var(--text-light);
            position: relative;
        }

        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            border-color: var(--primary-green);
        }

        .stat-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .stat-dot.online { background: var(--success-color); }
        .stat-dot.deposito { background: var(--primary-green); }
        .stat-dot.saque { background: var(--warning-color); }
        .stat-dot.config { background: var(--purple-color); }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .stat-badge {
            background: var(--primary-green);
            color: #000;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }

        .user-menu {
            position: relative;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-green), var(--primary-gold));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #000;
            cursor: pointer;
            transition: var(--transition);
        }

        .user-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(0, 212, 170, 0.3);
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: var(--transition);
            z-index: 1001;
        }

        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-light);
            text-decoration: none;
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background: var(--bg-card);
            color: var(--primary-green);
        }

        /* Navigation */
        .nav-container {
            background: var(--bg-panel);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 0;
        }

        .nav-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .nav-item {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 20px;
            text-decoration: none;
            color: var(--text-light);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .nav-item:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
            border-color: var(--primary-green);
        }

        .nav-item.active {
            background: linear-gradient(135deg, var(--primary-green), var(--primary-gold));
            color: #000;
            box-shadow: 0 8px 32px rgba(0, 212, 170, 0.3);
        }

        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--primary-green), transparent);
            transition: var(--transition);
        }

        .nav-item:hover::before {
            left: 100%;
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

        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 24px;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-light);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .live-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: var(--success-color);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 24px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
            border-color: var(--primary-green);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--primary-green), transparent);
            transition: var(--transition);
        }

        .stat-card:hover::before {
            left: 100%;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #000;
        }

        .stat-icon.users { background: linear-gradient(135deg, var(--primary-green), #00b894); }
        .stat-icon.money { background: linear-gradient(135deg, var(--info-color), #2563eb); }
        .stat-icon.games { background: linear-gradient(135deg, var(--purple-color), #7c3aed); }
        .stat-icon.chart { background: linear-gradient(135deg, var(--warning-color), #f59e0b); }
        .stat-icon.coins { background: linear-gradient(135deg, var(--primary-gold), #f4c430); }
        .stat-icon.check { background: linear-gradient(135deg, var(--success-color), #16a34a); }
        .stat-icon.hand { background: linear-gradient(135deg, var(--info-color), #0ea5e9); }
        .stat-icon.dice { background: linear-gradient(135deg, var(--purple-color), #a855f7); }
        .stat-icon.minus { background: linear-gradient(135deg, var(--error-color), #dc2626); }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            font-weight: 600;
            color: var(--success-color);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-light);
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-detail {
            color: var(--text-muted);
            font-size: 12px;
        }

        /* Buttons */
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green), #00b894);
            color: #000;
            box-shadow: 0 4px 16px rgba(0, 212, 170, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 212, 170, 0.4);
        }

        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-light);
            border: 1px solid var(--border-color);
        }

        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 40px;
        }

        .chart-card {
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 24px;
            transition: var(--transition);
        }

        .chart-card:hover {
            border-color: var(--primary-green);
            box-shadow: var(--shadow);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-light);
        }

        .chart-actions {
            display: flex;
            gap: 8px;
        }

        .chart-btn {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            transition: var(--transition);
        }

        .chart-btn:hover {
            color: var(--primary-green);
            border-color: var(--primary-green);
        }

        /* Bar Chart */
        .bar-chart {
            display: flex;
            align-items: center;
            gap: 12px;
            height: 200px;
            padding: 20px 0;
        }

        .bar-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .bar {
            width: 100%;
            background: linear-gradient(180deg, var(--primary-green), #00b894);
            border-radius: 4px 4px 0 0;
            position: relative;
            transition: var(--transition);
            cursor: pointer;
        }

        .bar:hover {
            filter: brightness(1.2);
            transform: scaleY(1.05);
        }

        .bar-value {
            position: absolute;
            top: -24px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 11px;
            font-weight: 600;
            color: var(--primary-green);
            white-space: nowrap;
        }

        .bar-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .admin-forms {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-top: 40px;
        }

        .form-card {
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 24px;
            transition: var(--transition);
        }

        .form-card:hover {
            border-color: var(--primary-green);
            box-shadow: var(--shadow);
        }

        .form-card h3 {
            color: var(--primary-green);
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
            border-radius: var(--radius);
            color: var(--text-light);
            font-size: 14px;
            transition: var(--transition);
        }

        .form-card input[type="file"]:focus,
        .form-card select:focus {
            border-color: var(--primary-gold);
            outline: none;
            box-shadow: 0 0 0 3px rgba(251, 206, 0, 0.15);
        }

        .form-card button {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, var(--primary-green), #00b894);
            color: #000;
            border: none;
            border-radius: var(--radius);
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 16px rgba(0, 212, 170, 0.3);
        }

        .form-card button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 212, 170, 0.4);
        }

        .form-card button:active {
            transform: translateY(0);
        }

        .preview-section {
            margin: 16px 0;
            padding: 16px;
            background: var(--bg-dark);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
        }

        .preview-section strong {
            color: var(--primary-green);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .preview-section img {
            max-width: 100%;
            border: 2px solid var(--primary-gold);
            border-radius: var(--radius);
            margin-top: 8px;
            box-shadow: 0 4px 16px rgba(251, 206, 0, 0.3);
        }

        /* Messages */
        .message {
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideInDown 0.4s ease;
        }

        .message.success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .message.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                padding: 0 16px;
            }

            .header-stats {
                gap: 8px;
            }

            .stat-item {
                padding: 6px 12px;
                font-size: 12px;
            }

            .nav-content {
                padding: 0 16px;
            }

            .nav-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .nav-item {
                padding: 16px;
            }

            .main-content {
                padding: 24px 16px;
            }

            .page-title {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .admin-forms {
                grid-template-columns: 1fr;
            }

            .bar-chart {
                height: 150px;
            }
        }

        @media (max-width: 480px) {
            .header-stats {
                display: none;
            }

            .nav-grid {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 20px;
            }

            .page-subtitle {
                font-size: 14px;
            }
        }

        /* Canvas Chart Styles */
        .chart-container {
            background: var(--bg-dark);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 20px;
            position: relative;
            height: 350px;
        }

        .chart-container h3 {
            color: var(--primary-green);
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

        @media (max-width: 768px) {
            .chart-container {
                height: 300px;
            }

            .charts-section {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="painel_admin.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <span>Admin Panel</span>
            </a>

            <div class="header-stats">
                <div class="stat-item" onclick="showOnlineUsers()">
                    <div class="stat-dot online"></div>
                    <span>Online:</span>
                    <span id="online-count">0</span>
                    <i class="fas fa-users"></i>
                </div>

                <a href="pix_admin.php" class="stat-item">
                    <div class="stat-dot deposito"></div>
                    <span>Depósito</span>
                    <div class="stat-badge" id="deposito-count">0</div>
                </a>

                <a href="saques_admin.php" class="stat-item">
                    <div class="stat-dot saque"></div>
                    <span>Saque</span>
                    <div class="stat-badge" id="saque-count">0</div>
                </a>

                <a href="configuracoes_admin.php" class="stat-item">
                    <div class="stat-dot config"></div>
                    <span>Config</span>
                    <i class="fas fa-cog"></i>
                </a>
            </div>

            <div class="user-menu">
                <div class="user-avatar" onclick="toggleUserMenu()">
                    <i class="fas fa-user-crown"></i>
                </div>
                <div class="user-dropdown" id="userDropdown">
                    <a href="configuracoes_admin.php" class="dropdown-item">
                        <i class="fas fa-cog"></i>
                        <span>Configurações</span>
                    </a>
                    <a href="usuarios_admin.php" class="dropdown-item">
                        <i class="fas fa-users"></i>
                        <span>Usuários</span>
                    </a>
                    <a href="logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Sair</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <div class="nav-container">
        <div class="nav-content">
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
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i>
                Dashboard Executivo
            </h1>
            <div class="page-subtitle">
                <div class="live-indicator">
                    <div class="live-dot"></div>
                    <span>Dados atualizados em tempo real</span>
                </div>
                <span>•</span>
                <span>Última atualização: <span id="last-update"><?= date('H:i:s') ?></span></span>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>+12%</span>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($usuarios) ?></div>
                <div class="stat-label">Total de Usuários</div>
                <div class="stat-detail">+<?= $cadastrosHoje ?> cadastros hoje</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon money">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>+8%</span>
                    </div>
                </div>
                <div class="stat-value">R$ <?= number_format($total_depositos_gerados_raw, 0, ',', '.') ?></div>
                <div class="stat-label">Depósitos Gerados</div>
                <div class="stat-detail">Total de depósitos</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon games">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>+15%</span>
                    </div>
                </div>
                <div class="stat-value">R$ <?= $total_ganho ?></div>
                <div class="stat-label">Total Ganhos</div>
                <div class="stat-detail">Prêmios pagos aos usuários</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon chart">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>+22%</span>
                    </div>
                </div>
                <div class="stat-value">R$ <?= $lucro_bruto ?></div>
                <div class="stat-label">Lucro Bruto</div>
                <div class="stat-detail">Receita líquida da plataforma</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon minus">
                        <i class="fas fa-minus-circle"></i>
                    </div>
                    <div class="stat-trend" style="color: var(--error-color);">
                        <i class="fas fa-arrow-down"></i>
                        <span>-5%</span>
                    </div>
                </div>
                <div class="stat-value">R$ <?= $total_perdido ?></div>
                <div class="stat-label">Total Perdas</div>
                <div class="stat-detail">Apostas sem prêmios</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon check">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>+18%</span>
                    </div>
                </div>
                <div class="stat-value">R$ <?= $total_depositos_pagos ?></div>
                <div class="stat-label">Depósitos Pagos</div>
                <div class="stat-detail">Depósitos aprovados</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon hand">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>+10%</span>
                    </div>
                </div>
                <div class="stat-value">R$ <?= $total_saques_pagos ?></div>
                <div class="stat-label">Saques Pagos</div>
                <div class="stat-detail">Saques processados</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon dice">
                        <i class="fas fa-dice"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>+25%</span>
                    </div>
                </div>
                <div class="stat-value">R$ <?= $total_apostado ?></div>
                <div class="stat-label">Total Apostado</div>
                <div class="stat-detail">Volume total de apostas</div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <!-- Revenue Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Receita dos Últimos 7 Dias</h3>
                    <div class="chart-actions">
                        <div class="chart-btn">
                            <i class="fas fa-download"></i>
                        </div>
                        <div class="chart-btn">
                            <i class="fas fa-expand"></i>
                        </div>
                        <div class="chart-btn">
                            <i class="fas fa-ellipsis-v"></i>
                        </div>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="depositosChart"></canvas>
                </div>
            </div>

            <!-- Cadastros Chart -->
            <div class="stat-card">
                <div class="chart-header">
                    <h3 class="chart-title">Cadastros dos Últimos 7 Dias</h3>
                </div>
                <div class="chart-container">
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
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Menu do usuário
        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        // Fechar menus ao clicar fora
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.user-menu');
            
            if (!userMenu.contains(event.target)) {
                document.getElementById('userDropdown').classList.remove('show');
            }
        });

        // Tecla ESC para fechar menus
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('userDropdown').classList.remove('show');
            }
        });

        // Atualizar timestamp
        function updateTimestamp() {
            document.getElementById('last-update').textContent = new Date().toLocaleTimeString('pt-BR');
        }

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

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            setInterval(updateTimestamp, 30000); // Atualizar a cada 30 segundos
            if (document.getElementById('alvo')) mostrarPreview();
            if (document.getElementById('alvo_menu')) mostrarPreviewMenu();
        });

    </script>
</body>
</html>