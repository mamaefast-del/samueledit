<?php
session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

require 'db.php';

// Processar requisições AJAX
if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    header('Content-Type: application/json');
    
    try {
        // Criar tabelas se não existirem
        $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            senha VARCHAR(255) NOT NULL,
            saldo DECIMAL(10,2) DEFAULT 0.00,
            data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS jogos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            categoria VARCHAR(50),
            ativo BOOLEAN DEFAULT TRUE,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS apostas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT,
            jogo_id INT,
            valor DECIMAL(10,2) NOT NULL,
            resultado ENUM('ganhou', 'perdeu', 'pendente') DEFAULT 'pendente',
            data_aposta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
            FOREIGN KEY (jogo_id) REFERENCES jogos(id)
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS depositos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            metodo VARCHAR(50) DEFAULT 'PIX',
            status ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente',
            data_solicitacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        )");
        
        // Inserir dados de exemplo se não existirem
        $count_usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
        if ($count_usuarios == 0) {
            $pdo->exec("INSERT INTO usuarios (nome, email, senha, saldo) VALUES 
                ('João Silva', 'joao@email.com', '" . password_hash('123456', PASSWORD_DEFAULT) . "', 150.00),
                ('Maria Santos', 'maria@email.com', '" . password_hash('123456', PASSWORD_DEFAULT) . "', 200.00),
                ('Pedro Costa', 'pedro@email.com', '" . password_hash('123456', PASSWORD_DEFAULT) . "', 75.50)");
        }
        
        $count_jogos = $pdo->query("SELECT COUNT(*) FROM jogos")->fetchColumn();
        if ($count_jogos == 0) {
            $pdo->exec("INSERT INTO jogos (nome, categoria, ativo) VALUES 
                ('Aviator', 'Crash', 1),
                ('Mines', 'Estratégia', 1),
                ('Plinko', 'Arcade', 1),
                ('Dice', 'Clássico', 1),
                ('Blackjack', 'Cartas', 1)");
        }
        
        // Buscar estatísticas
        $total_usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
        $novos_usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
        $total_depositos = $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM depositos WHERE status = 'aprovado'")->fetchColumn();
        $depositos_pendentes = $pdo->query("SELECT COUNT(*) FROM depositos WHERE status = 'pendente'")->fetchColumn();
        $total_apostas = $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM apostas")->fetchColumn();
        $apostas_hoje = $pdo->query("SELECT COUNT(*) FROM apostas WHERE DATE(data_aposta) = CURDATE()")->fetchColumn();
        
        $stats = [
            'total_usuarios' => (int)$total_usuarios,
            'novos_usuarios' => (int)$novos_usuarios,
            'total_depositos' => (float)$total_depositos,
            'depositos_pendentes' => (int)$depositos_pendentes,
            'total_apostas' => (float)$total_apostas,
            'apostas_hoje' => (int)$apostas_hoje
        ];
        
        echo json_encode($stats);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    exit;
}

// Buscar estatísticas gerais
try {
    // Total de usuários
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
    $total_usuarios = $stmt->fetchColumn();

    // Usuários novos (últimos 30 dias)
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $usuarios_novos = $stmt->fetchColumn();

    // Total de depósitos aprovados
    $stmt = $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM transacoes_pix WHERE status = 'aprovado'");
    $total_depositos = $stmt->fetchColumn();

    // Depósitos hoje
    $stmt = $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM transacoes_pix WHERE status = 'aprovado' AND DATE(data_transacao) = CURDATE()");
    $depositos_hoje = $stmt->fetchColumn();

    // Total de jogadas
    $stmt = $pdo->query("SELECT COUNT(*) FROM historico_jogos");
    $total_jogadas = $stmt->fetchColumn();

    // Jogadas hoje
    $stmt = $pdo->query("SELECT COUNT(*) FROM historico_jogos WHERE DATE(data_jogo) = CURDATE()");
    $jogadas_hoje = $stmt->fetchColumn();

    // RTP médio (simulado baseado nas configurações)
    $stmt = $pdo->query("SELECT AVG(chance_ganho) FROM raspadinhas_config");
    $rtp_medio = $stmt->fetchColumn() ?: 85.0;

    // Receita dos últimos 7 dias
    $receita_7_dias = [];
    for ($i = 6; $i >= 0; $i--) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(valor), 0) 
            FROM transacoes_pix 
            WHERE status = 'aprovado' 
            AND DATE(data_transacao) = DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ");
        $stmt->execute([$i]);
        $receita_7_dias[] = $stmt->fetchColumn();
    }

    // Top Jogos (últimos 30 dias)
    $stmt = $pdo->query("
        SELECT 
            rc.nome,
            rc.valor,
            COUNT(hj.id) as total_jogadas,
            COALESCE(SUM(CASE WHEN hj.premio_ganho > 0 THEN hj.premio_ganho ELSE 0 END), 0) as total_premios
        FROM raspadinhas_config rc
        LEFT JOIN historico_jogos hj ON rc.id = hj.jogo_id 
            AND hj.data_jogo >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY rc.id, rc.nome, rc.valor
        ORDER BY total_jogadas DESC
        LIMIT 4
    ");
    $top_jogos = $stmt->fetchAll();

    // Atividades recentes
    $stmt = $pdo->query("
        SELECT 
            hj.*,
            u.nome as usuario_nome,
            u.email as usuario_email,
            rc.nome as jogo_nome
        FROM historico_jogos hj
        LEFT JOIN usuarios u ON hj.usuario_id = u.id
        LEFT JOIN raspadinhas_config rc ON hj.jogo_id = rc.id
        WHERE hj.premio_ganho > 0
        ORDER BY hj.data_jogo DESC
        LIMIT 5
    ");
    $atividades_recentes = $stmt->fetchAll();

} catch (PDOException $e) {
    // Valores padrão em caso de erro
    $total_usuarios = $usuarios_novos = $total_depositos = $depositos_hoje = 0;
    $total_jogadas = $jogadas_hoje = 0;
    $rtp_medio = 85.0;
    $receita_7_dias = [0, 0, 0, 0, 0, 0, 0];
    $top_jogos = [];
    $atividades_recentes = [];
}

// Calcular percentuais de crescimento (simulado)
$crescimento_usuarios = $usuarios_novos > 0 ? '+' . number_format(($usuarios_novos / max($total_usuarios - $usuarios_novos, 1)) * 100, 1) : '0';
$crescimento_depositos = $depositos_hoje > 0 ? '+' . number_format(($depositos_hoje / max($total_depositos - $depositos_hoje, 1)) * 100, 1) : '0';
$crescimento_jogadas = $jogadas_hoje > 0 ? '+' . number_format(($jogadas_hoje / max($total_jogadas - $jogadas_hoje, 1)) * 100, 1) : '0';

// Dias da semana para o gráfico
$dias_semana = ['SAT', 'SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI'];
$max_receita = max($receita_7_dias) ?: 1;

// Ícones para os jogos
$icones_jogos = ['🎯', '🎲', '🎰', '🎪', '🎭', '🎨', '🎸', '⚽'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Executivo - Admin</title>
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

        .stat-icon.users { background: linear-gradient(135deg, var(--primary-green), #00b894); }
        .stat-icon.money { background: linear-gradient(135deg, var(--info-color), #2563eb); }
        .stat-icon.games { background: linear-gradient(135deg, var(--purple-color), #7c3aed); }
        .stat-icon.chart { background: linear-gradient(135deg, var(--warning-color), #f59e0b); }

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

        /* Charts Section */
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
            align-items: end;
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

        /* Top Games */
        .top-games {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .game-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .game-item:hover {
            border-color: var(--primary-green);
            transform: translateX(4px);
        }

        .game-rank {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--bg-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            color: var(--text-light);
        }

        .game-rank.first { background: linear-gradient(135deg, var(--primary-gold), #f4c430); color: #000; }
        .game-rank.second { background: linear-gradient(135deg, #c0c0c0, #a8a8a8); color: #000; }
        .game-rank.third { background: linear-gradient(135deg, #cd7f32, #b8860b); color: #000; }

        .game-icon {
            font-size: 24px;
        }

        .game-info {
            flex: 1;
        }

        .game-name {
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 4px;
        }

        .game-stats {
            font-size: 12px;
            color: var(--text-muted);
        }

        .game-value {
            text-align: right;
        }

        .game-plays {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary-green);
        }

        .game-subtitle {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* Activity Section */
        .activity-section {
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 24px;
            transition: var(--transition);
        }

        .activity-section:hover {
            border-color: var(--primary-green);
            box-shadow: var(--shadow);
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .activity-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-light);
        }

        .view-all-btn {
            color: var(--primary-green);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
        }

        .view-all-btn:hover {
            color: var(--primary-gold);
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .activity-item:hover {
            border-color: var(--primary-green);
            transform: translateX(4px);
        }

        .activity-dot {
            width: 8px;
            height: 8px;
            background: var(--success-color);
            border-radius: 50%;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 4px;
        }

        .activity-time {
            color: var(--text-muted);
            font-size: 12px;
        }

        .activity-value {
            font-weight: 700;
            color: var(--primary-green);
            font-size: 14px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-light);
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: var(--bg-card);
            border-radius: 8px;
            color: var(--text-muted);
            cursor: pointer;
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--error-color);
            background: rgba(239, 68, 68, 0.1);
        }

        .modal-body {
            padding: 24px;
            max-height: 400px;
            overflow-y: auto;
        }

        .user-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--bg-card);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .user-avatar-small {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
            color: #000;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 500;
            color: var(--text-light);
            font-size: 14px;
        }

        .user-email {
            color: var(--text-muted);
            font-size: 12px;
        }

        .user-status {
            font-size: 11px;
            color: var(--success-color);
            font-weight: 500;
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

        /* Upload Section Styles */
        .upload-section {
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 40px;
            transition: var(--transition);
        }

        .upload-section:hover {
            border-color: var(--primary-green);
            box-shadow: var(--shadow);
        }

        .upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .upload-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 20px;
            transition: var(--transition);
        }

        .upload-card:hover {
            border-color: var(--primary-green);
            transform: translateY(-2px);
        }

        .upload-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .upload-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #000;
        }

        .upload-icon.logo { background: linear-gradient(135deg, var(--primary-green), #00b894); }
        .upload-icon.banner { background: linear-gradient(135deg, var(--info-color), #2563eb); }
        .upload-icon.menu { background: linear-gradient(135deg, var(--purple-color), #7c3aed); }

        .upload-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-light);
        }

        .upload-desc {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        .upload-preview {
            width: 100%;
            height: 120px;
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .upload-preview:hover {
            border-color: var(--primary-green);
            background: rgba(0, 212, 170, 0.05);
        }

        .upload-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .upload-placeholder {
            text-align: center;
            color: var(--text-muted);
        }

        .upload-placeholder i {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
        }

        .file-input {
            display: none;
        }

        .upload-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary-green), #00b894);
            color: #000;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .upload-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 212, 170, 0.3);
        }

        .upload-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .upload-status {
            margin-top: 12px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            display: none;
        }

        .upload-status.success {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success-color);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .upload-status.error {
            background: rgba(239, 68, 68, 0.15);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .upload-status.loading {
            background: rgba(251, 206, 0, 0.15);
            color: var(--primary-gold);
            border: 1px solid rgba(251, 206, 0, 0.3);
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
                    <div class="nav-title">Produtos</div>
                    <div class="nav-desc">Biblioteca de prêmios</div>
                </a>

                <a href="pix_admin.php" class="nav-item">
                    <i class="fas fa-exchange-alt nav-icon"></i>
                    <div class="nav-title">Jogadas</div>
                    <div class="nav-desc">Últimas jogadas</div>
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
                        <span><?= $crescimento_usuarios ?>%</span>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($total_usuarios) ?></div>
                <div class="stat-label">Total de Usuários</div>
                <div class="stat-detail">+<?= $usuarios_novos ?> novos usuários 30 dias</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon money">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span><?= $crescimento_depositos ?>%</span>
                    </div>
                </div>
                <div class="stat-value">R$ <?= number_format($total_depositos, 0, ',', '.') ?></div>
                <div class="stat-label">Receita via Depósitos</div>
                <div class="stat-detail">R$ <?= number_format($depositos_hoje, 2, ',', '.') ?> hoje</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon games">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span><?= $crescimento_jogadas ?>%</span>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($total_jogadas) ?></div>
                <div class="stat-label">Total de Jogadas</div>
                <div class="stat-detail"><?= number_format($jogadas_hoje) ?> hoje</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon chart">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="stat-trend" style="color: var(--warning-color);">
                        <i class="fas fa-target"></i>
                        <span>Meta: 85%</span>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($rtp_medio, 1) ?>%</div>
                <div class="stat-label">RTP Médio</div>
                <div class="stat-detail">Jogadas com vitórias: <?= number_format($rtp_medio, 1) ?>%</div>
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
                <div class="bar-chart">
                    <?php foreach ($receita_7_dias as $index => $receita): ?>
                        <div class="bar-item">
                            <div class="bar" style="height: <?= ($receita / $max_receita) * 100 ?>%;">
                                <div class="bar-value">R$ <?= number_format($receita, 0, ',', '.') ?></div>
                            </div>
                            <div class="bar-label"><?= $dias_semana[$index] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Top Games -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Top Jogos</h3>
                </div>
                <div class="top-games">
                    <?php if (empty($top_jogos)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                            <i class="fas fa-gamepad" style="font-size: 32px; margin-bottom: 12px; opacity: 0.5;"></i>
                            <p>Nenhum jogo encontrado</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($top_jogos as $index => $jogo): ?>
                            <div class="game-item">
                                <div class="game-rank <?= $index === 0 ? 'first' : ($index === 1 ? 'second' : ($index === 2 ? 'third' : '')) ?>">
                                    <?= $index + 1 ?>
                                </div>
                                <div class="game-icon">
                                    <?= $icones_jogos[$index % count($icones_jogos)] ?>
                                </div>
                                <div class="game-info">
                                    <div class="game-name"><?= htmlspecialchars($jogo['nome']) ?></div>
                                    <div class="game-stats"><?= number_format($jogo['total_jogadas']) ?> jogadas</div>
                                </div>
                                <div class="game-value">
                                    <div class="game-plays"><?= number_format($jogo['total_premios'], 0) ?> vitórias</div>
                                    <div class="game-subtitle">R$ <?= number_format($jogo['total_premios'], 2, ',', '.') ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Activity Section -->
        <div class="activity-section">
            <div class="activity-header">
                <h3 class="activity-title">Atividade Recente</h3>
                <a href="#" class="view-all-btn">Ver todos</a>
            </div>
            <div class="activity-list">
                <?php if (empty($atividades_recentes)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class="fas fa-clock" style="font-size: 32px; margin-bottom: 12px; opacity: 0.5;"></i>
                        <p>Nenhuma atividade recente</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($atividades_recentes as $atividade): ?>
                        <div class="activity-item">
                            <div class="activity-dot"></div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <strong><?= htmlspecialchars($atividade['usuario_nome'] ?: $atividade['usuario_email']) ?></strong> 
                                    ganhou R$ <?= number_format($atividade['premio_ganho'], 2, ',', '.') ?> 
                                    no <?= htmlspecialchars($atividade['jogo_nome']) ?>
                                </div>
                                <div class="activity-time"><?= date('d/m/Y H:i', strtotime($atividade['data_jogo'])) ?></div>
                            </div>
                            <div class="activity-value">R$ <?= number_format($atividade['premio_ganho'], 2, ',', '.') ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Usuários Online -->
    <div id="onlineUsersModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-users"></i>
                    Usuários Online
                </h3>
                <button class="modal-close" onclick="closeOnlineModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="online-users-list" class="user-list">
                    <div style="text-align: center; padding: 20px; color: var(--text-muted);">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Carregando usuários online...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Atualizar estatísticas do header
        async function updateHeaderStats() {
            try {
                const response = await fetch(window.location.pathname + '?action=get_stats');
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const text = await response.text();
                if (!text) {
                    throw new Error('Empty response');
                }
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response text:', text);
                    throw new Error('Invalid JSON response');
                }
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                document.getElementById('online-count').textContent = data.online || 0;
                document.getElementById('deposito-count').textContent = data.depositos_pendentes || 0;
                document.getElementById('saque-count').textContent = data.saques_pendentes || 0;
                document.getElementById('last-update').textContent = new Date().toLocaleTimeString('pt-BR');
            } catch (error) {
                console.error('Erro ao atualizar estatísticas:', error);
                // Mostrar valores padrão em caso de erro
                document.getElementById('online-count').textContent = '0';
                document.getElementById('deposito-count').textContent = '0';
                document.getElementById('saque-count').textContent = '0';
            }
        }

        // Menu do usuário
        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        // Modal usuários online
        function showOnlineUsers() {
            document.getElementById('onlineUsersModal').classList.add('show');
            loadOnlineUsers();
        }

        function closeOnlineModal() {
            document.getElementById('onlineUsersModal').classList.remove('show');
        }

        async function loadOnlineUsers() {
            try {
                const response = await fetch('get_online_users.php');
                const users = await response.json();
                
                const container = document.getElementById('online-users-list');
                
                if (users.length === 0) {
                    container.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: var(--text-muted);">
                            <i class="fas fa-user-slash" style="font-size: 32px; margin-bottom: 12px; opacity: 0.5;"></i>
                            <p>Nenhum usuário online no momento</p>
                        </div>
                    `;
                    return;
                }
                
                container.innerHTML = users.map(user => `
                    <div class="user-item">
                        <div class="user-avatar-small">
                            ${(user.nome || user.email).charAt(0).toUpperCase()}
                        </div>
                        <div class="user-info">
                            <div class="user-name">${user.nome || 'Usuário'}</div>
                            <div class="user-email">${user.email}</div>
                        </div>
                        <div class="user-status">Online</div>
                    </div>
                `).join('');
            } catch (error) {
                console.error('Erro ao carregar usuários online:', error);
                document.getElementById('online-users-list').innerHTML = `
                    <div style="text-align: center; padding: 20px; color: var(--error-color);">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Erro ao carregar usuários</p>
                    </div>
                `;
            }
        }

        // Fechar menus ao clicar fora
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.user-menu');
            const modal = document.getElementById('onlineUsersModal');
            
            if (!userMenu.contains(event.target)) {
                document.getElementById('userDropdown').classList.remove('show');
            }
            
            if (event.target === modal) {
                closeOnlineModal();
            }
        });

        // Tecla ESC para fechar modais
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeOnlineModal();
                document.getElementById('userDropdown').classList.remove('show');
            }
        });

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            updateHeaderStats();
            setInterval(updateHeaderStats, 30000); // Atualizar a cada 30 segundos
        });

        // Upload de imagens
        async function uploadImage(type, input) {
            const file = input.files[0];
            if (!file) return;

            const statusElement = document.getElementById(type + '-status');
            const previewElement = input.parentElement.querySelector('.upload-preview');
            
            // Validar tipo de arquivo
            if (!file.type.startsWith('image/')) {
                showUploadStatus(statusElement, 'error', 'Por favor, selecione apenas arquivos de imagem.');
                return;
            }

            // Validar tamanho (máximo 5MB)
            if (file.size > 5 * 1024 * 1024) {
                showUploadStatus(statusElement, 'error', 'Arquivo muito grande. Máximo 5MB.');
                return;
            }

            // Mostrar loading
            showUploadStatus(statusElement, 'loading', 'Enviando imagem...');

            // Criar FormData
            const formData = new FormData();
            formData.append('image', file);
            formData.append('type', type);

            try {
                const response = await fetch('upload_image.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showUploadStatus(statusElement, 'success', result.message);
                    
                    // Atualizar preview
                    setTimeout(() => {
                        const img = document.createElement('img');
                        img.src = result.url + '?v=' + Date.now();
                        img.alt = type + ' atual';
                        previewElement.innerHTML = '';
                        previewElement.appendChild(img);
                    }, 500);
                } else {
                    showUploadStatus(statusElement, 'error', result.message || 'Erro ao enviar imagem.');
                }
            } catch (error) {
                console.error('Erro no upload:', error);
                showUploadStatus(statusElement, 'error', 'Erro de conexão. Tente novamente.');
            }

            // Limpar input
            input.value = '';
        }

        function showUploadStatus(element, type, message) {
            element.className = 'upload-status ' + type;
            element.textContent = message;
            element.style.display = 'block';

            // Auto-hide após 5 segundos (exceto loading)
            if (type !== 'loading') {
                setTimeout(() => {
                    element.style.display = 'none';
                }, 5000);
            }
        }
    </script>
</body>
</html>