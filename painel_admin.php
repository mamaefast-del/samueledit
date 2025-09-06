<?php
session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

require 'db.php';

// Função para buscar estatísticas gerais
function getStats($pdo) {
    $stats = [];
    
    try {
        // Total de usuários
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE status != 'banido'");
        $stats['total_usuarios'] = $stmt->fetch()['total'] ?? 0;
        
        // Novos usuários nos últimos 30 dias
        $stmt = $pdo->query("SELECT COUNT(*) as novos FROM usuarios WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status != 'banido'");
        $stats['novos_usuarios'] = $stmt->fetch()['novos'] ?? 0;
        
        // Total de depósitos
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM depositos WHERE status = 'aprovado'");
        $stats['total_depositos'] = $stmt->fetch()['total'] ?? 0;
        
        // Valor total depositado
        $stmt = $pdo->query("SELECT SUM(valor) as total FROM depositos WHERE status = 'aprovado'");
        $stats['valor_depositos'] = $stmt->fetch()['total'] ?? 0;
        
        // Total de apostas
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM apostas");
        $stats['total_apostas'] = $stmt->fetch()['total'] ?? 0;
        
        // Valor total apostado
        $stmt = $pdo->query("SELECT SUM(valor) as total FROM apostas");
        $stats['valor_apostas'] = $stmt->fetch()['total'] ?? 0;
        
        // Jogos ativos
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM jogos WHERE status = 'ativo'");
        $stats['jogos_ativos'] = $stmt->fetch()['total'] ?? 0;
        
        // Depósitos pendentes
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM depositos WHERE status = 'pendente'");
        $stats['depositos_pendentes'] = $stmt->fetch()['total'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Erro ao buscar estatísticas: " . $e->getMessage());
        // Valores padrão em caso de erro
        $stats = [
            'total_usuarios' => 0,
            'novos_usuarios' => 0,
            'total_depositos' => 0,
            'valor_depositos' => 0,
            'total_apostas' => 0,
            'valor_apostas' => 0,
            'jogos_ativos' => 0,
            'depositos_pendentes' => 0
        ];
    }
    
    return $stats;
}

// Função para buscar top jogos
function getTopJogos($pdo, $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT j.*, 
                   COUNT(a.id) as total_apostas,
                   SUM(a.valor) as total_apostado
            FROM jogos j 
            LEFT JOIN apostas a ON j.id = a.jogo_id 
            WHERE j.status = 'ativo'
            GROUP BY j.id 
            ORDER BY total_apostas DESC, j.popularidade DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erro ao buscar top jogos: " . $e->getMessage());
        return [];
    }
}

// Função para buscar usuários recentes
function getUsuariosRecentes($pdo, $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT nome, email, saldo, data_cadastro 
            FROM usuarios 
            WHERE status != 'banido'
            ORDER BY data_cadastro DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erro ao buscar usuários recentes: " . $e->getMessage());
        return [];
    }
}

// Buscar dados
$stats = getStats($pdo);
$topJogos = getTopJogos($pdo);
$usuariosRecentes = getUsuariosRecentes($pdo);

// Inserir alguns jogos de exemplo se não existirem
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM jogos");
    $totalJogos = $stmt->fetchColumn();
    
    if ($totalJogos == 0) {
        $jogosExemplo = [
            ['Aviator', 'Jogo do aviãozinho', 'https://via.placeholder.com/300x200?text=Aviator', 'Crash', 'Spribe'],
            ['Mines', 'Encontre os diamantes', 'https://via.placeholder.com/300x200?text=Mines', 'Mines', 'Spribe'],
            ['Plinko', 'Jogo da bolinha', 'https://via.placeholder.com/300x200?text=Plinko', 'Arcade', 'Spribe'],
            ['Dice', 'Jogo de dados', 'https://via.placeholder.com/300x200?text=Dice', 'Dice', 'Spribe'],
            ['Blackjack', 'Jogo de cartas clássico', 'https://via.placeholder.com/300x200?text=Blackjack', 'Cards', 'Evolution']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO jogos (nome, descricao, imagem, categoria, provider, popularidade) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($jogosExemplo as $index => $jogo) {
            $stmt->execute([$jogo[0], $jogo[1], $jogo[2], $jogo[3], $jogo[4], (5 - $index) * 10]);
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao inserir jogos de exemplo: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo</title>
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
            padding: 12px 24px;
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
        .btn-danger { background: var(--error-color); color: white; }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-panel);
            padding: 24px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(135deg, var(--primary-gold), var(--primary-green));
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        }

        .stat-icon.users { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .stat-icon.money { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
        .stat-icon.games { background: rgba(168, 85, 247, 0.1); color: #a855f7; }
        .stat-icon.pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-light);
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-change {
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stat-change.positive { color: var(--success-color); }
        .stat-change.neutral { color: var(--text-muted); }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .panel {
            background: var(--bg-panel);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .panel-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-content {
            padding: 20px;
        }

        .game-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .game-item:last-child {
            border-bottom: none;
        }

        .game-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: var(--bg-card);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--primary-green);
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

        .user-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .user-item:last-child {
            border-bottom: none;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 4px;
        }

        .user-email {
            font-size: 12px;
            color: var(--text-muted);
        }

        .user-balance {
            font-weight: 700;
            color: var(--primary-green);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .action-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary-green);
        }

        .action-icon {
            font-size: 32px;
            margin-bottom: 12px;
            color: var(--primary-green);
        }

        .action-title {
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 8px;
        }

        .action-desc {
            font-size: 12px;
            color: var(--text-muted);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <i class="fas fa-tachometer-alt"></i>
            Painel Administrativo
        </h1>
        <div style="display: flex; gap: 12px;">
            <a href="configuracoes_admin.php" class="btn btn-secondary">
                <i class="fas fa-cog"></i>
                Configurações
            </a>
            <a href="logout.php" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i>
                Sair
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['total_usuarios']) ?></div>
                <div class="stat-label">Total de Usuários</div>
                <div class="stat-change <?= $stats['novos_usuarios'] > 0 ? 'positive' : 'neutral' ?>">
                    <i class="fas fa-arrow-up"></i>
                    +<?= $stats['novos_usuarios'] ?> novos usuários 30 dias
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon money">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <div class="stat-value">R$ <?= number_format($stats['valor_depositos'], 2, ',', '.') ?></div>
                <div class="stat-label">Total Depositado</div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <?= $stats['total_depositos'] ?> depósitos aprovados
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon games">
                        <i class="fas fa-gamepad"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['total_apostas']) ?></div>
                <div class="stat-label">Total de Apostas</div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    R$ <?= number_format($stats['valor_apostas'], 2, ',', '.') ?> apostado
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['depositos_pendentes']) ?></div>
                <div class="stat-label">Depósitos Pendentes</div>
                <div class="stat-change neutral">
                    <i class="fas fa-exclamation-triangle"></i>
                    Requer atenção
                </div>
            </div>
        </div>

        <!-- Conteúdo Principal -->
        <div class="content-grid">
            <!-- Top Jogos -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-trophy"></i>
                        Top Jogos
                    </div>
                    <span style="color: var(--text-muted); font-size: 12px;">
                        <?= count($topJogos) ?> jogos ativos
                    </span>
                </div>
                <div class="panel-content">
                    <?php if (empty($topJogos)): ?>
                        <div class="empty-state">
                            <i class="fas fa-gamepad"></i>
                            <p>Nenhum jogo encontrado</p>
                            <small>Adicione jogos para ver as estatísticas</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($topJogos as $jogo): ?>
                            <div class="game-item">
                                <div class="game-image">
                                    <i class="fas fa-dice"></i>
                                </div>
                                <div class="game-info">
                                    <div class="game-name"><?= htmlspecialchars($jogo['nome']) ?></div>
                                    <div class="game-stats">
                                        <?= $jogo['total_apostas'] ?? 0 ?> apostas • 
                                        R$ <?= number_format($jogo['total_apostado'] ?? 0, 2, ',', '.') ?> apostado
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Usuários Recentes -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-user-plus"></i>
                        Usuários Recentes
                    </div>
                </div>
                <div class="panel-content">
                    <?php if (empty($usuariosRecentes)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>Nenhum usuário encontrado</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($usuariosRecentes as $usuario): ?>
                            <div class="user-item">
                                <div class="user-info">
                                    <div class="user-name"><?= htmlspecialchars($usuario['nome']) ?></div>
                                    <div class="user-email"><?= htmlspecialchars($usuario['email']) ?></div>
                                </div>
                                <div class="user-balance">
                                    R$ <?= number_format($usuario['saldo'], 2, ',', '.') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-bolt"></i>
                    Ações Rápidas
                </div>
            </div>
            <div class="panel-content">
                <div class="quick-actions">
                    <a href="admin_depositos.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="action-title">Gerenciar Depósitos</div>
                        <div class="action-desc">Aprovar ou rejeitar depósitos</div>
                    </a>

                    <a href="admin_usuarios.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="action-title">Gerenciar Usuários</div>
                        <div class="action-desc">Visualizar e editar usuários</div>
                    </a>

                    <a href="admin_jogos.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-gamepad"></i>
                        </div>
                        <div class="action-title">Gerenciar Jogos</div>
                        <div class="action-desc">Adicionar e configurar jogos</div>
                    </a>

                    <a href="admin_relatorios.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="action-title">Relatórios</div>
                        <div class="action-desc">Visualizar estatísticas detalhadas</div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Atualizar estatísticas a cada 30 segundos
        setInterval(function() {
            location.reload();
        }, 30000);

        // Adicionar efeitos visuais
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
                this.style.boxShadow = '0 8px 32px rgba(0, 212, 170, 0.1)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });
    </script>
</body>
</html>