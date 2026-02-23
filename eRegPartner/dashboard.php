<?php
require_once 'config/config.php';
require_once 'classes/MySQL_DatabaseManager.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$db = new MySQL_DatabaseManager();

// Get statistics for all vital records
$stats = [
    'birth' => ['total' => 0, 'registered' => 0, 'unregistered' => 0, 'transmitted' => 0, 'pending' => 0],
    'death' => ['total' => 0, 'registered' => 0, 'unregistered' => 0, 'transmitted' => 0, 'pending' => 0],
    'marriage' => ['total' => 0, 'registered' => 0, 'unregistered' => 0, 'transmitted' => 0, 'pending' => 0]
];

// Birth statistics
$birthTotal = $db->fetchOne("SELECT COUNT(*) as count FROM " . TABLE_BIRTH);
$birthRegistered = $db->fetchOne("SELECT COUNT(*) as count FROM " . TABLE_BIRTH . " WHERE RegistryNum NOT LIKE '!%'");
$birthUnregistered = $db->fetchOne("SELECT COUNT(*) as count FROM " . TABLE_BIRTH . " WHERE RegistryNum LIKE '!%'");
$birthTransmitted = $db->fetchOne("SELECT COUNT(DISTINCT TRIM(RegistryNum)) as count FROM registry_birth_log WHERE RegistryNum LIKE '!%'", [], 'support');

$stats['birth']['total'] = $birthTotal['count'] ?? 0;
$stats['birth']['registered'] = $birthRegistered['count'] ?? 0;
$stats['birth']['unregistered'] = $birthUnregistered['count'] ?? 0;
$stats['birth']['transmitted'] = $birthTransmitted['count'] ?? 0;
$stats['birth']['pending'] = $stats['birth']['unregistered'] - $stats['birth']['transmitted'];

// Death statistics
$deathTotal = $db->fetchOne("SELECT COUNT(*) as count FROM " . TABLE_DEATH);
$deathRegistered = $db->fetchOne("SELECT COUNT(*) as count FROM " . TABLE_DEATH . " WHERE RegistryNum NOT LIKE '!%'");
$deathUnregistered = $db->fetchOne("SELECT COUNT(*) as count FROM " . TABLE_DEATH . " WHERE RegistryNum LIKE '!%'");
$deathTransmitted = $db->fetchOne("SELECT COUNT(DISTINCT TRIM(RegistryNum)) as count FROM registry_death_log WHERE RegistryNum LIKE '!%'", [], 'support');

$stats['death']['total'] = $deathTotal['count'] ?? 0;
$stats['death']['registered'] = $deathRegistered['count'] ?? 0;
$stats['death']['unregistered'] = $deathUnregistered['count'] ?? 0;
$stats['death']['transmitted'] = $deathTransmitted['count'] ?? 0;
$stats['death']['pending'] = $stats['death']['unregistered'] - $stats['death']['transmitted'];

// Marriage statistics
$marriageTotal = $db->fetchOne("SELECT COUNT(*) as count FROM " . TABLE_MARRIAGE);
$marriageRegistered = $db->fetchOne("SELECT COUNT(*) as count FROM " . TABLE_MARRIAGE . " WHERE RegistryNum NOT LIKE '!%'");
$marriageUnregistered = $db->fetchOne("SELECT COUNT(*) as count FROM " . TABLE_MARRIAGE . " WHERE RegistryNum LIKE '!%'");
$marriageTransmitted = $db->fetchOne("SELECT COUNT(DISTINCT TRIM(RegistryNum)) as count FROM registry_marriage_log WHERE RegistryNum LIKE '!%'", [], 'support');

$stats['marriage']['total'] = $marriageTotal['count'] ?? 0;
$stats['marriage']['registered'] = $marriageRegistered['count'] ?? 0;
$stats['marriage']['unregistered'] = $marriageUnregistered['count'] ?? 0;
$stats['marriage']['transmitted'] = $marriageTransmitted['count'] ?? 0;
$stats['marriage']['pending'] = $stats['marriage']['unregistered'] - $stats['marriage']['transmitted'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/dark-theme.css" rel="stylesheet">
    <style>
        /* Enhanced 2x2 Grid Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 20px 0;
        }
        
        .stat-box {
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .stat-box.highlight {
            background: rgba(102, 126, 234, 0.1);
            border-color: rgba(102, 126, 234, 0.3);
        }
        
        .stat-box small {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 6px;
            opacity: 0.9;
        }
        
        .stat-box strong {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-dark navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-home me-2"></i>STAKEHOLDER <?php echo APP_NAME; ?>
                 <span class="badge bg-success ms-2">
                <i class="fas fa-shield-alt me-1"></i>Secure
            </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="content-wrapper">
        <!-- Page Header -->
        <div class="page-header">
            <h2><i class="fas fa-chart-line me-2"></i>Dashboard</h2>
            <p class="text-muted mb-0 mt-2">
                <i class="fas fa-info-circle me-1"></i> 
                <span style="color: #ffffff;">Overview of vital records transmission status</span>
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 g-md-4">
            <!-- Birth Card -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="stat-card birth clickable" onclick="window.location='birth_transmission.php'">
                    <div class="icon-wrapper">
                        <i class="fas fa-baby"></i>
                    </div>
                    <h3>Birth Records</h3>
                    <div class="count"><?php echo number_format($stats['birth']['total']); ?></div>
                    
                    <!-- Enhanced 2x2 Grid -->
                    <div class="stats-grid">
                        <div class="stat-box">
                            <small style="color: #a8e6cf;">Registered</small>
                            <strong class="text-white"><?php echo number_format($stats['birth']['registered']); ?></strong>
                        </div>
                        <div class="stat-box highlight">
                            <small style="color: #ffd93d;">Unregistered</small>
                            <strong class="text-white"><?php echo number_format($stats['birth']['unregistered']); ?></strong>
                        </div>
                        <div class="stat-box">
                            <small style="color: #90ee90;">Transmitted</small>
                            <strong class="text-white"><?php echo number_format($stats['birth']['transmitted']); ?></strong>
                        </div>
                        <div class="stat-box">
                            <small style="color: #ff6b6b;">Pending</small>
                            <strong class="text-white"><?php echo number_format($stats['birth']['pending']); ?></strong>
                        </div>
                    </div>
                    
                    <div class="mt-3 d-grid gap-2" onclick="event.stopPropagation()">
                        <a href="birth_transmission.php" class="btn btn-success btn-sm">
                            <i class="fas fa-sparkles me-1"></i>UNREGISTERED
                        </a>
                        <a href="birth_registered.php" class="btn btn-success btn-sm">
                            <i class="fas fa-check-circle me-1"></i>LGU REGISTERED
                        </a>
                    </div>
                </div>
            </div>

            <!-- Death Card -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="stat-card death clickable" onclick="window.location='death_transmission.php'">
                    <div class="icon-wrapper">
                        <i class="fas fa-cross"></i>
                    </div>
                    <h3>Death Records</h3>
                    <div class="count"><?php echo number_format($stats['death']['total']); ?></div>
                    
                    <!-- Enhanced 2x2 Grid -->
                    <div class="stats-grid">
                        <div class="stat-box">
                            <small style="color: #ffb3ba;">Registered</small>
                            <strong class="text-white"><?php echo number_format($stats['death']['registered']); ?></strong>
                        </div>
                        <div class="stat-box highlight">
                            <small style="color: #ffd93d;">Unregistered</small>
                            <strong class="text-white"><?php echo number_format($stats['death']['unregistered']); ?></strong>
                        </div>
                        <div class="stat-box">
                            <small style="color: #90ee90;">Transmitted</small>
                            <strong class="text-white"><?php echo number_format($stats['death']['transmitted']); ?></strong>
                        </div>
                        <div class="stat-box">
                            <small style="color: #ff6b6b;">Pending</small>
                            <strong class="text-white"><?php echo number_format($stats['death']['pending']); ?></strong>
                        </div>
                    </div>
                    
                    <div class="mt-3 d-grid gap-2">
                        <a href="death_transmission.php" class="btn btn-danger btn-sm">
                            <i class="fas fa-sparkles me-1"></i>UNREGISTERED
                        </a>
                        <a href="#" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-check-circle me-1"></i>LGU REGISTERED
                        </a>
                    </div>
                </div>
            </div>

            <!-- Marriage Card -->
            <div class="col-12 col-lg-4">
                <div class="stat-card marriage clickable" onclick="window.location='marriage_transmission.php'">
                    <div class="icon-wrapper">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Marriage Records</h3>
                    <div class="count"><?php echo number_format($stats['marriage']['total']); ?></div>
                    
                    <!-- Enhanced 2x2 Grid -->
                    <div class="stats-grid">
                        <div class="stat-box">
                            <small style="color: #ffe5b4;">Registered</small>
                            <strong class="text-white"><?php echo number_format($stats['marriage']['registered']); ?></strong>
                        </div>
                        <div class="stat-box highlight">
                            <small style="color: #ffd93d;">Unregistered</small>
                            <strong class="text-white"><?php echo number_format($stats['marriage']['unregistered']); ?></strong>
                        </div>
                        <div class="stat-box">
                            <small style="color: #90ee90;">Transmitted</small>
                            <strong class="text-white"><?php echo number_format($stats['marriage']['transmitted']); ?></strong>
                        </div>
                        <div class="stat-box">
                            <small style="color: #ff6b6b;">Pending</small>
                            <strong class="text-white"><?php echo number_format($stats['marriage']['pending']); ?></strong>
                        </div>
                    </div>
                    
                    <div class="mt-3 d-grid gap-2">
                        <a href="marriage_transmission.php" class="btn btn-warning btn-sm">
                            <i class="fas fa-sparkles me-1"></i>UNREGISTERED
                        </a>
                        <a href="#" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-check-circle me-1"></i>LGU REGISTERED
                        </a>
                    </div>
                </div>
            </div>
        </div>

        
    </div>

    <!-- Auto-refresh script -->
    <script>
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>