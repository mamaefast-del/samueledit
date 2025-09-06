<?php
session_start();

// Verificar se é admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

require 'db.php';

// Buscar estatísticas gerais
try {
    // Total de usuários
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
    $total_usuarios = $stmt->fetchColumn();
    
    // Total de depósitos pendentes
    $stmt = $pdo->query("SELECT COUNT(*) FROM depositos WHERE status = 'pendente'");
    $depositos_pendentes = $stmt->fetchColumn();
    
    // Total de saques pendentes
    $stmt = $pdo->query("SELECT COUNT(*) FROM saques WHERE status = 'pendente'");
    $saques_pendentes = $stmt->fetchColumn();
    
    // Valor total em depósitos aprovados
    $stmt = $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM depositos WHERE status = 'aprovado'");
    $total_depositos = $stmt->fetchColumn();
    
    // Valor total em saques aprovados
    $stmt = $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM saques WHERE status = 'aprovado'");
    $total_saques = $stmt->fetchColumn();
    
    // Usuários ativos (com saldo > 0)
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE saldo > 0");
    $usuarios_ativos = $stmt->fetchColumn();
    
    // Saldo total dos usuários
    $stmt = $pdo->query("SELECT COALESCE(SUM(saldo), 0) FROM usuarios");
    $saldo_total_usuarios = $stmt->fetchColumn();
    
    // Depósitos hoje
    $stmt = $pdo->query("SELECT COUNT(*) FROM depositos WHERE DATE(data_solicitacao) = CURDATE()");
    $depositos_hoje = $stmt->fetchColumn();
    
    // Saques hoje
    $stmt = $pdo->query("SELECT COUNT(*) FROM saques WHERE DATE(data_solicitacao) = CURDATE()");
    $saques_hoje = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $total_usuarios = $depositos_pendentes = $saques_pendentes = 0;
    $total_depositos = $total_saques = $usuarios_ativos = 0;
    $saldo_total_usuarios = $depositos_hoje = $saques_hoje = 0;
}

// Buscar últimas atividades
try {
    // Últimos depósitos
    $stmt = $pdo->prepare("
        SELECT d.*, u.nome, u.email 
        FROM depositos d 
        LEFT JOIN usuarios u ON d.usuario_id = u.id 
        ORDER BY d.data_solicitacao DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $ultimos_depositos = $stmt->fetchAll();
    
    // Últimos saques
    $stmt = $pdo->prepare("
        SELECT s.*, u.nome, u.email 
        FROM saques s 
        LEFT JOIN usuarios u ON s.usuario_id = u.id 
        ORDER BY s.data_solicitacao DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $ultimos_saques = $stmt->fetchAll();
    
    // Últimos usuários cadastrados
    $stmt = $pdo->prepare("
        SELECT * FROM usuarios 
        ORDER BY data_cadastro DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $ultimos_usuarios = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $ultimos_depositos = $ultimos_saques = $ultimos_usuarios = [];
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
            --info-color: #3b82f6;
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .header h1 {
            background: linear-gradient(135deg, var(--primary-gold), var(--primary-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
            font-weight: 800;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
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
        .btn-secondary { 
            background: var(--bg-card); 
            color: var(--text-light); 
            border: 1px solid var(--border-color); 
        }
        .btn-secondary:hover {
            background: var(--border-color);
            transform: translateY(-1px);
        }
        .btn-danger { 
            background: var(--error-color); 
            color: white; 
        }
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
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
        .stat-icon.deposits { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
        .stat-icon.withdrawals { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .stat-icon.money { background: rgba(251, 206, 0, 0.1); color: var(--primary-gold); }
        .stat-icon.pending { background: rgba(245, 158, 11, 0.1); color: var(--warning-color); }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-light);
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 14px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .action-card {
            background: var(--bg-panel);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-light);
        }

        .action-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary-green);
            box-shadow: 0 8px 24px rgba(0, 212, 170, 0.1);
            color: var(--text-light);
        }

        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 24px;
            background: linear-gradient(135deg, var(--primary-gold), var(--primary-green));
            color: #000;
        }

        .action-card h3 {
            margin-bottom: 8px;
            font-size: 16px;
            font-weight: 600;
        }

        .action-card p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .recent-activity {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }

        .activity-card {
            background: var(--bg-panel);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .activity-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .activity-header h3 {
            color: var(--text-light);
            font-weight: 600;
            font-size: 16px;
        }

        .activity-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-info h4 {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .activity-info p {
            color: var(--text-muted);
            font-size: 12px;
        }

        .activity-value {
            font-weight: 700;
            font-size: 14px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 4px;
            display: inline-block;
        }

        .status-pendente { background: var(--warning-color); color: white; }
        .status-aprovado { background: var(--success-color); color: white; }
        .status-rejeitado { background: var(--error-color); color: white; }

        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid var(--info-color);
            color: var(--info-color);
        }

        .refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-panel);
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 12px;
            color: var(--text-muted);
            display: none;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .recent-activity {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }

            .header-actions {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <i class="fas fa-tachometer-alt"></i>
            Painel Administrativo
        </h1>
        <div class="header-actions">
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

    <div class="refresh-indicator" id="refreshIndicator">
        <i class="fas fa-sync-alt fa-spin"></i>
        Atualizando dados...
    </div>

    <div class="container">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            Bem-vindo ao painel administrativo! Os dados são atualizados automaticamente a cada 30 segundos.
        </div>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= number_format($total_usuarios) ?></div>
                        <div class="stat-label">Total de Usuários</div>
                    </div>
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= number_format($usuarios_ativos) ?></div>
                        <div class="stat-label">Usuários Ativos</div>
                    </div>
                    <div class="stat-icon users">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= number_format($depositos_pendentes) ?></div>
                        <div class="stat-label">Depósitos Pendentes</div>
                    </div>
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= number_format($saques_pendentes) ?></div>
                        <div class="stat-label">Saques Pendentes</div>
                    </div>
                    <div class="stat-icon pending">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value">R$ <?= number_format($total_depositos, 2, ',', '.') ?></div>
                        <div class="stat-label">Total Depositado</div>
                    </div>
                    <div class="stat-icon deposits">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value">R$ <?= number_format($total_saques, 2, ',', '.') ?></div>
                        <div class="stat-label">Total Sacado</div>
                    </div>
                    <div class="stat-icon withdrawals">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value">R$ <?= number_format($saldo_total_usuarios, 2, ',', '.') ?></div>
                        <div class="stat-label">Saldo Total Usuários</div>
                    </div>
                    <div class="stat-icon money">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= number_format($depositos_hoje + $saques_hoje) ?></div>
                        <div class="stat-label">Transações Hoje</div>
                    </div>
                    <div class="stat-icon money">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div class="quick-actions">
            <a href="admin_usuarios.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Gerenciar Usuários</h3>
                <p>Visualizar e gerenciar contas de usuários</p>
            </a>

            <a href="admin_depositos.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <h3>Gerenciar Depósitos</h3>
                <p>Aprovar ou rejeitar depósitos</p>
            </a>

            <a href="admin_saques.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <h3>Gerenciar Saques</h3>
                <p>Processar solicitações de saque</p>
            </a>

            <a href="admin_jogos.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-gamepad"></i>
                </div>
                <h3>Gerenciar Jogos</h3>
                <p>Configurar jogos e apostas</p>
            </a>

            <a href="admin_relatorios.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3>Relatórios</h3>
                <p>Visualizar relatórios financeiros</p>
            </a>

            <a href="configuracoes_admin.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <h3>Configurações</h3>
                <p>Configurações do sistema</p>
            </a>
        </div>

        <!-- Atividades Recentes -->
        <div class="recent-activity">
            <!-- Últimos Depósitos -->
            <div class="activity-card">
                <div class="activity-header">
                    <i class="fas fa-arrow-down" style="color: var(--success-color);"></i>
                    <h3>Últimos Depósitos</h3>
                </div>
                <div class="activity-list">
                    <?php if (empty($ultimos_depositos)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Nenhum depósito encontrado</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($ultimos_depositos as $deposito): ?>
                            <div class="activity-item">
                                <div class="activity-info">
                                    <h4><?= htmlspecialchars($deposito['nome'] ?? 'Usuário #' . $deposito['usuario_id']) ?></h4>
                                    <p><?= date('d/m/Y H:i', strtotime($deposito['data_solicitacao'])) ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <div class="activity-value" style="color: var(--success-color);">
                                        R$ <?= number_format($deposito['valor'], 2, ',', '.') ?>
                                    </div>
                                    <span class="status-badge status-<?= $deposito['status'] ?>">
                                        <?= ucfirst($deposito['status']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Últimos Saques -->
            <div class="activity-card">
                <div class="activity-header">
                    <i class="fas fa-arrow-up" style="color: var(--error-color);"></i>
                    <h3>Últimos Saques</h3>
                </div>
                <div class="activity-list">
                    <?php if (empty($ultimos_saques)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Nenhum saque encontrado</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($ultimos_saques as $saque): ?>
                            <div class="activity-item">
                                <div class="activity-info">
                                    <h4><?= htmlspecialchars($saque['nome'] ?? 'Usuário #' . $saque['usuario_id']) ?></h4>
                                    <p><?= date('d/m/Y H:i', strtotime($saque['data_solicitacao'])) ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <div class="activity-value" style="color: var(--error-color);">
                                        R$ <?= number_format($saque['valor'], 2, ',', '.') ?>
                                    </div>
                                    <span class="status-badge status-<?= $saque['status'] ?>">
                                        <?= ucfirst($saque['status']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Últimos Usuários -->
            <div class="activity-card">
                <div class="activity-header">
                    <i class="fas fa-user-plus" style="color: var(--primary-green);"></i>
                    <h3>Novos Usuários</h3>
                </div>
                <div class="activity-list">
                    <?php if (empty($ultimos_usuarios)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Nenhum usuário encontrado</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($ultimos_usuarios as $usuario): ?>
                            <div class="activity-item">
                                <div class="activity-info">
                                    <h4><?= htmlspecialchars($usuario['nome']) ?></h4>
                                    <p><?= htmlspecialchars($usuario['email']) ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <div class="activity-value" style="color: var(--primary-green);">
                                        R$ <?= number_format($usuario['saldo'], 2, ',', '.') ?>
                                    </div>
                                    <p style="font-size: 11px; color: var(--text-muted);">
                                        <?= date('d/m/Y', strtotime($usuario['data_cadastro'])) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Função para mostrar indicador de atualização
        function showRefreshIndicator() {
            document.getElementById('refreshIndicator').style.display = 'block';
            setTimeout(() => {
                document.getElementById('refreshIndicator').style.display = 'none';
            }, 2000);
        }

        // Atualizar dados a cada 30 segundos
        setInterval(function() {
            showRefreshIndicator();
            setTimeout(() => {
                location.reload();
            }, 1000);
        }, 30000);

        // Adicionar efeitos visuais
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Adicionar efeitos aos cards de estatísticas
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Animação de entrada dos elementos
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .action-card, .activity-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>