<?php
session_start();
require 'db.php';
date_default_timezone_set('America/Sao_Paulo');

$mensagem = '';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
  header('Location: login.php');
  exit;
}

// Filtro status (opcional)
$status_filter = $_GET['status'] ?? '';
$data_inicial = $_GET['data_inicial'] ?? '';
$data_final = $_GET['data_final'] ?? '';

// Paginação simples
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$limite = 20;
$offset = ($pagina - 1) * $limite;

// Query base
// Filtra para listagem e estatísticas
$where = [];
$params = [];

if ($status_filter && in_array($status_filter, ['pendente', 'aprovado', 'cancelado'])) {
  $where[] = "status = ?";
  $params[] = $status_filter;
}

if ($data_inicial) {
  $where[] = "DATE(criado_em) >= ?";
  $params[] = $data_inicial;
}

if ($data_final) {
  $where[] = "DATE(criado_em) <= ?";
  $params[] = $data_final;
}

$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Paginação
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM transacoes_pix $where_sql");
$stmtTotal->execute($params);
$total = $stmtTotal->fetchColumn();

$totalPaginas = ceil($total / $limite);

$stmt = $pdo->prepare("SELECT * FROM transacoes_pix $where_sql ORDER BY criado_em DESC LIMIT $limite OFFSET $offset");
$stmt->execute($params);
$transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas
$stmtStats = $pdo->prepare("SELECT status, COUNT(*) as total FROM transacoes_pix $where_sql GROUP BY status");
$stmtStats->execute($params);

$stats = ['aprovado' => 0, 'pendente' => 0, 'cancelado' => 0];
$totalGeral = 0;

while ($row = $stmtStats->fetch(PDO::FETCH_ASSOC)) {
  $status = strtolower($row['status']);
  $stats[$status] = (int)$row['total'];
  $totalGeral += (int)$row['total'];
}

// Calcular porcentagem de aprovação
$percentualAprovado = $totalGeral > 0 ? round(($stats['aprovado'] / $totalGeral) * 100, 2) : 0;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transações PIX - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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

        .stat-icon.total { background: linear-gradient(135deg, var(--info-color), #2563eb); }
        .stat-icon.aprovado { background: linear-gradient(135deg, var(--success-color), #16a34a); }
        .stat-icon.pendente { background: linear-gradient(135deg, var(--warning-color), #f59e0b); }
        .stat-icon.cancelado { background: linear-gradient(135deg, var(--error-color), #dc2626); }
        .stat-icon.taxa { background: linear-gradient(135deg, var(--purple-color), #7c3aed); }

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

        /* Cards */
        .card {
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            transition: var(--transition);
        }

        .card:hover {
            border-color: var(--primary-green);
            box-shadow: var(--shadow);
        }

        .card h3 {
            color: var(--text-light);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            font-weight: 700;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        label {
            font-weight: 600;
            color: var(--text-light);
            font-size: 14px;
        }

        input, select {
            padding: 12px 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            color: var(--text-light);
            font-size: 14px;
            transition: var(--transition);
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0, 212, 170, 0.1);
        }

        input:hover, select:hover {
            border-color: var(--primary-green);
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

        /* Tables */
        .table-container {
            overflow-x: auto;
            border-radius: var(--radius);
            background: var(--bg-panel);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
            background: transparent;
        }

        thead tr th {
            padding: 14px;
            color: var(--primary-green);
            font-weight: 700;
            text-align: left;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            user-select: none;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        thead tr th:first-child {
            border-radius: var(--radius) 0 0 var(--radius);
        }

        thead tr th:last-child {
            border-radius: 0 var(--radius) var(--radius) 0;
        }

        tbody tr {
            background: var(--bg-panel);
            transition: var(--transition);
        }

        tbody tr:hover {
            background: rgba(0, 212, 170, 0.05);
            transform: translateY(-1px);
        }

        tbody tr td {
            padding: 14px;
            color: var(--text-light);
            vertical-align: middle;
            border: 1px solid var(--border-color);
            border-top: none;
            font-size: 13px;
        }

        tbody tr td:first-child {
            border-radius: var(--radius) 0 0 var(--radius);
            border-left: 1px solid var(--border-color);
        }

        tbody tr td:last-child {
            border-radius: 0 var(--radius) var(--radius) 0;
            border-right: 1px solid var(--border-color);
        }

        /* Status Colors */
        .status-aprovado {
            color: var(--success-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-pendente {
            color: var(--warning-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-cancelado {
            color: var(--error-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Messages */
        .message {
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideInDown 0.4s ease;
        }

        .message.success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: var(--success-color);
        }

        .message.error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
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

        /* Debug Section */
        .debug-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
        }

        .debug-buttons {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .debug-content {
            background: var(--bg-dark);
            padding: 20px;
            border-radius: var(--radius);
            max-height: 400px;
            overflow-y: auto;
        }

        .debug-content pre {
            color: var(--text-light);
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
        }

        .debug-content .error { color: var(--error-color); }
        .debug-content .success { color: var(--success-color); }
        .debug-content .warning { color: var(--warning-color); }

        /* Pagination */
        .pagination {
            margin-top: 25px;
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
        }

        .pagination a {
            color: var(--primary-green);
            text-decoration: none;
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            background: var(--bg-panel);
            font-weight: 600;
            transition: var(--transition);
        }

        .pagination a:hover {
            background: var(--primary-green);
            color: #000;
            transform: translateY(-1px);
        }

        .pagination span {
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 8px;
            color: var(--text-light);
        }

        .empty-state p {
            font-size: 14px;
            line-height: 1.5;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .nav-grid {
                grid-template-columns: repeat(3, 1fr);
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .debug-buttons {
                flex-direction: column;
            }

            table {
                font-size: 11px;
            }

            .btn {
                padding: 8px 12px;
                font-size: 12px;
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
                    <div class="stat-badge" id="deposito-count"><?= $stats['pendente'] ?></div>
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
                <a href="painel_admin.php" class="nav-item">
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
                    <div class="nav-title">Produtos</div>
                    <div class="nav-desc">Biblioteca de prêmios</div>
                </a>

                <a href="pix_admin.php" class="nav-item active">
                    <i class="fas fa-exchange-alt nav-icon"></i>
                    <div class="nav-title">Transações</div>
                    <div class="nav-desc">Depósitos PIX</div>
                </a>

                <a href="afiliados_admin.php" class="nav-item">
                    <i class="fas fa-handshake nav-icon"></i>
                    <div class="nav-title">Indicações</div>
                    <div class="nav-desc">Sistema de afiliados</div>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-exchange-alt"></i>
                Transações PIX
            </h1>
            <div class="page-subtitle">
                <div class="live-indicator">
                    <div class="live-dot"></div>
                    <span>Monitoramento em tempo real</span>
                </div>
                <span>•</span>
                <span>Última atualização: <span id="last-update"><?= date('H:i:s') ?></span></span>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <?= $mensagem ?>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon total">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i>
                        <span>Total</span>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($totalGeral) ?></div>
                <div class="stat-label">Total de Transações</div>
                <div class="stat-detail">Todas as transações registradas</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon aprovado">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>Processadas</span>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['aprovado']) ?></div>
                <div class="stat-label">Transações Aprovadas</div>
                <div class="stat-detail">Pagamentos confirmados</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon pendente">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-trend" style="color: var(--warning-color);">
                        <i class="fas fa-hourglass-half"></i>
                        <span>Aguardando</span>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['pendente']) ?></div>
                <div class="stat-label">Transações Pendentes</div>
                <div class="stat-detail">Aguardando confirmação</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon cancelado">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-trend" style="color: var(--error-color);">
                        <i class="fas fa-ban"></i>
                        <span>Rejeitadas</span>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['cancelado']) ?></div>
                <div class="stat-label">Transações Canceladas</div>
                <div class="stat-detail">Pagamentos rejeitados</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon taxa">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-trend" style="color: <?= $percentualAprovado >= 80 ? 'var(--success-color)' : ($percentualAprovado >= 50 ? 'var(--warning-color)' : 'var(--error-color)') ?>;">
                        <i class="fas fa-chart-pie"></i>
                        <span>Taxa</span>
                    </div>
                </div>
                <div class="stat-value" style="color: <?= $percentualAprovado >= 80 ? 'var(--success-color)' : ($percentualAprovado >= 50 ? 'var(--warning-color)' : 'var(--error-color)') ?>;"><?= $percentualAprovado ?>%</div>
                <div class="stat-label">Taxa de Aprovação</div>
                <div class="stat-detail">Percentual de sucesso</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card">
            <h3>
                <i class="fas fa-filter"></i> Filtros de Transações
            </h3>
            
            <div class="form-grid">
                <form method="GET" action="">
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status" onchange="this.form.submit()">
                            <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>Todos</option>
                            <option value="pendente" <?= $status_filter === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="aprovado" <?= $status_filter === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                            <option value="cancelado" <?= $status_filter === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                </form>

                <form method="GET" action="">
                    <div class="form-group">
                        <label for="data_inicial">Data Inicial:</label>
                        <input type="date" name="data_inicial" id="data_inicial" value="<?= htmlspecialchars($_GET['data_inicial'] ?? '') ?>" onchange="this.form.submit()">
                    </div>
                </form>

                <form method="GET" action="">
                    <div class="form-group">
                        <label for="data_final">Data Final:</label>
                        <input type="date" name="data_final" id="data_final" value="<?= htmlspecialchars($_GET['data_final'] ?? '') ?>" onchange="this.form.submit()">
                    </div>
                </form>
            </div>
        </div>

        <!-- Debug Webhook -->
        <div class="card">
            <h3>
                <i class="fas fa-bug"></i> Debug Webhook
            </h3>
            
            <div class="debug-buttons">
                <a href="?action=view_logs" class="btn btn-primary">
                    <i class="fas fa-file-alt"></i> Ver Logs do Webhook
                </a>
                <a href="?action=test_webhook" class="btn btn-primary">
                    <i class="fas fa-play"></i> Testar Webhook
                </a>
                <a href="?action=check_pending" class="btn btn-primary">
                    <i class="fas fa-clock"></i> Verificar Pendentes
                </a>
                <a href="?action=check_user_balance" class="btn btn-primary">
                    <i class="fas fa-user-check"></i> Verificar Saldo Usuário
                </a>
                <a href="debug_webhook.php" class="btn btn-primary">
                    <i class="fas fa-cogs"></i> Debug Completo
                </a>
            </div>

            <?php
            $action = $_GET['action'] ?? '';
            
            if ($action === 'view_logs') {
                echo '<div class="debug-content">';
                echo '<h4 style="color: var(--primary-green); margin-bottom: 15px;">Últimos Logs do Webhook:</h4>';
                
                if (file_exists('log_webhook_expfypay.txt')) {
                    $logs = file_get_contents('log_webhook_expfypay.txt');
                    $lines = explode("\n", $logs);
                    $recentLines = array_slice($lines, -50); // Últimas 50 linhas
                    
                    echo '<pre>';
                    foreach ($recentLines as $line) {
                        if (trim($line)) {
                            if (strpos($line, 'ERRO') !== false) {
                                echo '<span class="error">' . htmlspecialchars($line) . '</span>' . "\n";
                            } elseif (strpos($line, 'SUCESSO') !== false) {
                                echo '<span class="success">' . htmlspecialchars($line) . '</span>' . "\n";
                            } elseif (strpos($line, 'AVISO') !== false) {
                                echo '<span class="warning">' . htmlspecialchars($line) . '</span>' . "\n";
                            } else {
                                echo htmlspecialchars($line) . "\n";
                            }
                        }
                    }
                    echo '</pre>';
                } else {
                    echo '<p style="color: var(--text-muted);">Nenhum log encontrado.</p>';
                }
                echo '</div>';
            }
            
            if ($action === 'check_pending') {
                echo '<div class="debug-content">';
                echo '<h4 style="color: var(--primary-green); margin-bottom: 15px;">Transações Pendentes:</h4>';
                
                $stmtPending = $pdo->query("SELECT * FROM transacoes_pix WHERE LOWER(status) IN ('pendente', 'pending', 'aguardando') ORDER BY criado_em DESC LIMIT 10");
                $pending = $stmtPending->fetchAll();
                
                if (count($pending) > 0) {
                    echo '<div class="table-container">';
                    echo '<table>';
                    echo '<thead><tr>';
                    echo '<th>ID</th>';
                    echo '<th>Usuário</th>';
                    echo '<th>Valor</th>';
                    echo '<th>External ID</th>';
                    echo '<th>Criado em</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';
                    
                    foreach ($pending as $p) {
                        echo '<tr>';
                        echo '<td>' . $p['id'] . '</td>';
                        echo '<td>' . $p['usuario_id'] . '</td>';
                        echo '<td>R$ ' . number_format($p['valor'], 2, ',', '.') . '</td>';
                        echo '<td style="font-family: monospace;">' . $p['external_id'] . '</td>';
                        echo '<td>' . date('d/m/Y H:i:s', strtotime($p['criado_em'])) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '</div>';
                } else {
                    echo '<p style="color: var(--success-color);">✓ Nenhuma transação pendente encontrada.</p>';
                }
                echo '</div>';
            }
            
            if ($action === 'test_webhook') {
                echo '<div class="debug-content">';
                echo '<h4 style="color: var(--primary-green); margin-bottom: 15px;">Teste de Webhook:</h4>';
                echo '<p style="color: var(--text-muted); margin-bottom: 15px;">Use este formulário para testar o webhook com dados simulados:</p>';
                
                // Mostrar mensagem se houver
                if (isset($_GET['msg']) && isset($_GET['type'])) {
                    $msg = htmlspecialchars($_GET['msg']);
                    $type = $_GET['type'];
                    $color = $type === 'success' ? 'var(--success-color)' : 'var(--error-color)';
                    echo '<div class="message ' . $type . '">';
                    echo '<i class="fas fa-' . ($type === 'success' ? 'check-circle' : 'exclamation-circle') . '"></i> ' . $msg;
                    echo '</div>';
                }
                
                echo '<form method="POST" action="test_webhook.php" style="display: grid; gap: 12px; max-width: 400px;">';
                echo '<div class="form-group">';
                echo '<label>Transaction ID:</label>';
                echo '<input type="text" name="transaction_id" value="TEST_' . time() . '" required>';
                echo '</div>';
                echo '<div class="form-group">';
                echo '<label>External ID:</label>';
                echo '<input type="text" name="external_id" value="EXT_' . time() . '" required>';
                echo '</div>';
                echo '<div class="form-group">';
                echo '<label>Valor:</label>';
                echo '<input type="number" name="amount" value="10.00" step="0.01" required>';
                echo '</div>';
                echo '<button type="submit" class="btn btn-primary">';
                echo '<i class="fas fa-paper-plane"></i> Enviar Teste';
                echo '</button>';
                echo '</form>';
                echo '</div>';
            }
            
            if ($action === 'check_user_balance') {
                echo '<div class="debug-content">';
                echo '<h4 style="color: var(--primary-green); margin-bottom: 15px;">Verificar Saldo de Usuário:</h4>';
                
                if ($_POST['user_id'] ?? false) {
                    $user_id = intval($_POST['user_id']);
                    
                    $stmtUser = $pdo->prepare("SELECT id, nome, email, saldo, comissao FROM usuarios WHERE id = ?");
                    $stmtUser->execute([$user_id]);
                    $user = $stmtUser->fetch();
                    
                    if ($user) {
                        echo '<div style="background: var(--bg-panel); padding: 15px; border-radius: 8px; margin-bottom: 15px;">';
                        echo '<h5 style="color: var(--primary-green); margin-bottom: 10px;">Dados do Usuário:</h5>';
                        echo '<p><strong>ID:</strong> ' . $user['id'] . '</p>';
                        echo '<p><strong>Nome:</strong> ' . htmlspecialchars($user['nome']) . '</p>';
                        echo '<p><strong>Email:</strong> ' . htmlspecialchars($user['email']) . '</p>';
                        echo '<p><strong>Saldo:</strong> <span style="color: var(--success-color); font-weight: bold;">R$ ' . number_format($user['saldo'], 2, ',', '.') . '</span></p>';
                        echo '<p><strong>Comissão:</strong> R$ ' . number_format($user['comissao'], 2, ',', '.') . '</p>';
                        echo '</div>';
                        
                        // Últimas transações do usuário
                        $stmtTrans = $pdo->prepare("SELECT * FROM transacoes_pix WHERE usuario_id = ? ORDER BY criado_em DESC LIMIT 5");
                        $stmtTrans->execute([$user_id]);
                        $transacoes_user = $stmtTrans->fetchAll();
                        
                        if (count($transacoes_user) > 0) {
                            echo '<h5 style="color: var(--primary-green); margin-bottom: 10px;">Últimas 5 Transações:</h5>';
                            echo '<div class="table-container">';
                            echo '<table>';
                            echo '<thead><tr>';
                            echo '<th>ID</th>';
                            echo '<th>Valor</th>';
                            echo '<th>Status</th>';
                            echo '<th>Criado em</th>';
                            echo '</tr></thead>';
                            echo '<tbody>';
                            
                            foreach ($transacoes_user as $t) {
                                $statusColor = $t['status'] === 'aprovado' ? 'var(--success-color)' : ($t['status'] === 'pendente' ? 'var(--warning-color)' : 'var(--error-color)');
                                echo '<tr>';
                                echo '<td>' . $t['id'] . '</td>';
                                echo '<td>R$ ' . number_format($t['valor'], 2, ',', '.') . '</td>';
                                echo '<td style="color: ' . $statusColor . ';">' . ucfirst($t['status']) . '</td>';
                                echo '<td>' . date('d/m/Y H:i:s', strtotime($t['criado_em'])) . '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';
                            echo '</div>';
                        }
                    } else {
                        echo '<p style="color: var(--error-color);">Usuário não encontrado.</p>';
                    }
                }
                
                echo '<form method="POST" style="display: flex; gap: 12px; align-items: end; margin-top: 15px;">';
                echo '<div class="form-group" style="margin: 0;">';
                echo '<label>ID do Usuário:</label>';
                echo '<input type="number" name="user_id" placeholder="Digite o ID do usuário" required>';
                echo '</div>';
                echo '<button type="submit" class="btn btn-primary">';
                echo '<i class="fas fa-search"></i> Verificar';
                echo '</button>';
                echo '</form>';
                echo '</div>';
            }
            ?>
        </div>

        <!-- Tabela de Transações -->
        <div class="card">
            <h3>
                <i class="fas fa-table"></i> Lista de Transações
                <?php if ($total > 0): ?>
                    <span style="font-size: 14px; color: var(--text-muted); font-weight: 400;">(<?= $total ?> resultados)</span>
                <?php endif; ?>
            </h3>
            
            <?php if (count($transacoes) === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-exchange-alt"></i>
                    <h3>Nenhuma transação encontrada</h3>
                    <p>Ainda não há transações PIX registradas ou nenhuma transação corresponde aos filtros aplicados.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuário</th>
                                <th>Telefone</th>
                                <th>Valor (R$)</th>
                                <th>External ID</th>
                                <th>Status</th>
                                <th>Criado em</th>
                                <th>Transaction ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transacoes as $t): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--primary-green);">#<?= htmlspecialchars($t['id']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($t['usuario_id']) ?></td>
                                    <td><?= htmlspecialchars($t['telefone']) ?></td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--primary-green); font-size: 14px;">
                                            R$ <?= number_format($t['valor'], 2, ',', '.') ?>
                                        </div>
                                    </td>
                                    <td style="font-family: monospace; font-size: 11px;"><?= htmlspecialchars($t['external_id']) ?></td>
                                    <?php
                                        $status = htmlspecialchars($t['status']);
                                        $classeStatus = 'status-' . strtolower($status);
                                        $iconStatus = $status === 'aprovado' ? 'check-circle' : ($status === 'pendente' ? 'clock' : 'times-circle');
                                    ?>
                                    <td class="<?= $classeStatus ?>" style="text-transform: capitalize;">
                                        <i class="fas fa-<?= $iconStatus ?>"></i>
                                        <?= $status ?>
                                    </td>
                                    <?php
                                        $dt = new DateTime($t['criado_em'], new DateTimeZone('UTC'));
                                        $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                                    ?>
                                    <td>
                                        <div style="font-size: 12px;">
                                            <?= $dt->format('d/m/Y H:i:s') ?>
                                        </div>
                                    </td>
                                    <td style="font-family: monospace; font-size: 11px;"><?= htmlspecialchars($t['transaction_id']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação -->
                <?php if ($totalPaginas > 1): ?>
                    <div class="pagination">
                        <?php
                        $queryString = http_build_query([
                            'status' => $status_filter,
                            'data_inicial' => $data_inicial,
                            'data_final' => $data_final
                        ]);
                        ?>

                        <?php if ($pagina > 1): ?>
                            <a href="?<?= $queryString ?>&pagina=<?= $pagina - 1 ?>">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>

                        <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>

                        <?php if ($pagina < $totalPaginas): ?>
                            <a href="?<?= $queryString ?>&pagina=<?= $pagina + 1 ?>">
                                Próximo <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

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

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            setInterval(updateTimestamp, 30000); // Atualizar a cada 30 segundos
        });
    </script>
</body>
</html>