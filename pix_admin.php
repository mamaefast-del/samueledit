<?php
session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

require 'db.php';

// Buscar transações PIX
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = '';
$params = [];

if ($status_filter) {
    $where = "WHERE status = ?";
    $params = [$status_filter];
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transacoes_pix $where");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT t.*, u.nome, u.email 
        FROM transacoes_pix t 
        LEFT JOIN usuarios u ON t.usuario_id = u.id 
        $where 
        ORDER BY t.data_transacao DESC 
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $transacoes = $stmt->fetchAll();
} catch (PDOException $e) {
    $transacoes = [];
    $total = 0;
}

$total_pages = ceil($total / $limit);

// Buscar estatísticas
try {
    $pendentes = $pdo->query("SELECT COUNT(*) FROM transacoes_pix WHERE status = 'pendente'")->fetchColumn();
    $aprovadas = $pdo->query("SELECT COUNT(*) FROM transacoes_pix WHERE status = 'aprovado'")->fetchColumn();
    $rejeitadas = $pdo->query("SELECT COUNT(*) FROM transacoes_pix WHERE status = 'rejeitado'")->fetchColumn();
    $total_valor = $pdo->query("SELECT SUM(valor) FROM transacoes_pix WHERE status = 'aprovado'")->fetchColumn() ?: 0;
    $valor_pendente = $pdo->query("SELECT SUM(valor) FROM transacoes_pix WHERE status = 'pendente'")->fetchColumn() ?: 0;
    $hoje = $pdo->query("SELECT COUNT(*) FROM transacoes_pix WHERE DATE(data_transacao) = CURDATE()")->fetchColumn();
} catch (PDOException $e) {
    $pendentes = $aprovadas = $rejeitadas = $total_valor = $valor_pendente = $hoje = 0;
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $transacao_id = intval($_POST['transacao_id'] ?? 0);
    
    try {
        switch ($action) {
            case 'aprovar':
                // Buscar dados da transação
                $stmt = $pdo->prepare("SELECT * FROM transacoes_pix WHERE id = ?");
                $stmt->execute([$transacao_id]);
                $transacao = $stmt->fetch();
                
                if ($transacao && $transacao['status'] === 'pendente') {
                    // Aprovar transação
                    $stmt = $pdo->prepare("UPDATE transacoes_pix SET status = 'aprovado' WHERE id = ?");
                    $stmt->execute([$transacao_id]);
                    
                    // Adicionar saldo ao usuário
                    $stmt = $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
                    $stmt->execute([$transacao['valor'], $transacao['usuario_id']]);
                    
                    $_SESSION['success'] = 'Transação aprovada e saldo creditado!';
                }
                break;
                
            case 'rejeitar':
                $stmt = $pdo->prepare("UPDATE transacoes_pix SET status = 'rejeitado' WHERE id = ?");
                $stmt->execute([$transacao_id]);
                $_SESSION['success'] = 'Transação rejeitada!';
                break;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao processar ação: ' . $e->getMessage();
    }
    
    header('Location: pix_admin.php');
    exit;
}
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

        .stat-icon.pendente { background: linear-gradient(135deg, var(--warning-color), #f59e0b); }
        .stat-icon.aprovado { background: linear-gradient(135deg, var(--success-color), #16a34a); }
        .stat-icon.rejeitado { background: linear-gradient(135deg, var(--error-color), #dc2626); }
        .stat-icon.valor { background: linear-gradient(135deg, var(--primary-green), #00b894); }
        .stat-icon.hoje { background: linear-gradient(135deg, var(--purple-color), #7c3aed); }

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

        /* Filters */
        .filters {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .select-input {
            padding: 12px 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            color: var(--text-light);
            font-size: 14px;
            transition: var(--transition);
        }

        .select-input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0, 212, 170, 0.1);
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

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #16a34a);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--error-color), #dc2626);
            color: white;
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

        /* Status badges */
        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
        }

        .status-pendente {
            background: rgba(251, 206, 0, 0.15);
            color: var(--warning-color);
        }

        .status-aprovado {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success-color);
        }

        .status-rejeitado {
            background: rgba(239, 68, 68, 0.15);
            color: var(--error-color);
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
                    <div class="stat-badge" id="deposito-count"><?= $pendentes ?></div>
                </a>

                <a href="admin_saques.php" class="stat-item">
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

                <a href="admin_afiliados.php" class="nav-item">
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

        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <?= $_SESSION['success'] ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $_SESSION['error'] ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
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
                <div class="stat-value"><?= number_format($pendentes) ?></div>
                <div class="stat-label">Transações Pendentes</div>
                <div class="stat-detail">R$ <?= number_format($valor_pendente, 2, ',', '.') ?> em análise</div>
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
                <div class="stat-value"><?= number_format($aprovadas) ?></div>
                <div class="stat-label">Transações Aprovadas</div>
                <div class="stat-detail">R$ <?= number_format($total_valor, 2, ',', '.') ?> creditado</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon rejeitado">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-trend" style="color: var(--error-color);">
                        <i class="fas fa-ban"></i>
                        <span>Rejeitadas</span>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($rejeitadas) ?></div>
                <div class="stat-label">Transações Rejeitadas</div>
                <div class="stat-detail">Problemas identificados</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon valor">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i>
                        <span>Total</span>
                    </div>
                </div>
                <div class="stat-value">R$ <?= number_format($total_valor, 0, ',', '.') ?></div>
                <div class="stat-label">Volume Total Aprovado</div>
                <div class="stat-detail">Receita confirmada</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon hoje">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-trend" style="color: var(--purple-color);">
                        <i class="fas fa-clock"></i>
                        <span>24h</span>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($hoje) ?></div>
                <div class="stat-label">Transações Hoje</div>
                <div class="stat-detail">Atividade do dia</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <h3>
                <i class="fas fa-filter"></i> Filtros de Transações
            </h3>
            
            <form method="GET" class="filters">
                <select name="status" class="select-input">
                    <option value="">Todos os Status</option>
                    <option value="pendente" <?= $status_filter === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                    <option value="aprovado" <?= $status_filter === 'aprovado' ? 'selected' : '' ?>>Aprovadas</option>
                    <option value="rejeitado" <?= $status_filter === 'rejeitado' ? 'selected' : '' ?>>Rejeitadas</option>
                </select>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i>
                    Filtrar
                </button>
                
                <?php if ($status_filter): ?>
                    <a href="pix_admin.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Limpar
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Transactions Table -->
        <div class="card">
            <h3>
                <i class="fas fa-table"></i> Lista de Transações PIX
                <?php if ($status_filter): ?>
                    <span style="font-size: 14px; color: var(--text-muted); font-weight: 400;">(<?= $total ?> resultados)</span>
                <?php endif; ?>
            </h3>
            
            <?php if (empty($transacoes)): ?>
                <div class="empty-state">
                    <i class="fas fa-exchange-alt"></i>
                    <h3>Nenhuma transação encontrada</h3>
                    <p><?= $status_filter ? 'Nenhuma transação encontrada para este filtro.' : 'Ainda não há transações PIX registradas.' ?></p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuário</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Gateway</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transacoes as $transacao): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--primary-green);">#<?= $transacao['id'] ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong style="color: var(--text-light);"><?= htmlspecialchars($transacao['nome'] ?? 'Usuário #' . $transacao['usuario_id']) ?></strong>
                                            <br>
                                            <small style="color: var(--text-muted);"><?= htmlspecialchars($transacao['email'] ?? '') ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--primary-green); font-size: 14px;">
                                            R$ <?= number_format($transacao['valor'], 2, ',', '.') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                            $status = $transacao['status'];
                                            $icon = $status === 'aprovado' ? 'check-circle' : ($status === 'pendente' ? 'clock' : 'times-circle');
                                        ?>
                                        <span class="status-badge status-<?= $status ?>">
                                            <i class="fas fa-<?= $icon ?>"></i>
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px; color: var(--text-muted);">
                                            <?= htmlspecialchars($transacao['gateway'] ?? 'PIX') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px;">
                                            <?= date('d/m/Y H:i', strtotime($transacao['data_transacao'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($transacao['status'] === 'pendente'): ?>
                                            <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="aprovar">
                                                    <input type="hidden" name="transacao_id" value="<?= $transacao['id'] ?>">
                                                    <button type="submit" 
                                                            class="btn btn-success" 
                                                            onclick="return confirm('Aprovar transação e creditar saldo?')"
                                                            style="padding: 6px 12px; font-size: 11px;">
                                                        <i class="fas fa-check"></i>
                                                        Aprovar
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="rejeitar">
                                                    <input type="hidden" name="transacao_id" value="<?= $transacao['id'] ?>">
                                                    <button type="submit" 
                                                            class="btn btn-danger" 
                                                            onclick="return confirm('Rejeitar transação?')"
                                                            style="padding: 6px 12px; font-size: 11px;">
                                                        <i class="fas fa-times"></i>
                                                        Rejeitar
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style: italic; font-size: 12px;">
                                                <?= $transacao['status'] === 'aprovado' ? 'Processada' : 'Rejeitada' ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php
                        $query_params = ['status' => $status_filter];
                        $query_string = http_build_query(array_filter($query_params));
                        ?>

                        <?php if ($page > 1): ?>
                            <a href="?<?= $query_string ?>&page=<?= $page - 1 ?>">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>

                        <span>Página <?= $page ?> de <?= $total_pages ?></span>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?= $query_string ?>&page=<?= $page + 1 ?>">
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