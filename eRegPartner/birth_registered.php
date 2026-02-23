<?php
/**
 * Birth Transmission - NO AUTO-REFRESH, NO SEND/RESEND BUTTONS
 * Just clickable rows that open birth_viewer.php
 */
require_once 'config/config.php';
require_once 'classes/SecurityHelper.php';

SecurityHelper::requireLogin();

$csrfToken = SecurityHelper::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Birth Records - Partner Registered System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
      <style>
        :root {
            --blue-dark: #000040;
            --blue-header: #003366;
            --blue-primary: #667eea;
            --blue-light: #8b9cff;
            --bg-primary: #1a1a2e;
            --bg-card: #0f1419;
            --border-color: #2d3748;
            --text-primary: #ffffff;
            --bg-hover: rgba(102, 126, 234, 0.1);
        }
        
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        
        .navbar {
            background: var(--blue-dark) !important;
            position: sticky;
            top: 0;
            z-index: 1030;
            flex-shrink: 0;
            font-size: 1.0rem;
        }
        
        .app-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 15px;
        }
        
        .controls-section {
            flex-shrink: 0;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 10px 10px 0 0;
            padding: 15px;
        }
        
        .table-scroll-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: auto;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-top: none;
            border-radius: 0 0 10px 10px;
        }
        
        table {
            width: 100%;
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .band-header {
            background: var(--blue-dark);
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            font-size: 0.9rem;
            border: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        thead th {
            background: var(--blue-header);
            color: white !important;
            font-weight: 600;
            font-size: 0.8rem;
            padding: 8px 6px;
            border: 1px solid var(--border-color);
            position: sticky;
            top: 41px;
            z-index: 5;
            text-align: center;
        }
        
        tbody td {
            padding: 8px 6px;
            font-size: 0.8rem;
            border: 1px solid var(--border-color);
            background: #746a6a07;
            color: var(--text-primary);
        }
        
        tbody tr:hover { 
            background: var(--bg-hover);
            cursor: pointer;
        }
        
        .pagination .page-link {
            background: #2d3748;
            color: white;
            border-color: var(--border-color);
            padding: 4px 10px;
            font-size: 0.8rem;
        }
        
        .pagination .page-link:hover {
            background: var(--blue-primary);
            color: white;
        }
        
        .pagination .page-item.active .page-link {
            background: var(--blue-primary);
            border-color: var(--blue-primary);
        }
        
        .pagination .page-item.disabled .page-link {
            background: #1a1a2e;
            color: #6c757d;
        }
        
        .loading {
            text-align: center;
            padding: 50px;
            color: #ccc;
        }
        
        .security-badge {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            padding: 4px 8px;
            border-radius: 10px;
            border: 1px solid #28a745;
            font-size: 0.8rem;
            margin-left: 10px;
        }
        
        .stats-badge {
            background: rgba(102, 126, 234, 0.2);
            color: var(--blue-light);
            padding: 6px 12px;
            border-radius: 15px;
            border: 1px solid var(--blue-primary);
            font-size: 0.9rem;
        }
        
        .pdf-icon { color: #dc3545; font-size: 1.5rem; }
    </style>
</head>
<body>
   <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <i class="fas fa-home me-2"></i>Partner Transmission System
            <span class="badge bg-success ms-2">
                <i class="fas fa-shield-alt me-1"></i>Secure
            </span>
        </a>

        <!-- Toggle Button (for mobile) -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar Content -->
        <div class="collapse navbar-collapse" id="navbarContent">
            
           <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <!-- Statistics Reports Dropdown -->
                  <li class="nav-item">
            <a class="nav-link" href="birth_statistics_export.php">
                <i class="fas fa-cross me-1"></i> Statistics Reports
            </a>
        </li>

        <!-- Death Main Menu -->
        <li class="nav-item">
            <a class="nav-link" href="death_transmission.php">
                <i class="fas fa-cross me-1"></i> Death
            </a>
        </li>

        <!-- Marriage Main Menu -->
        <li class="nav-item">
            <a class="nav-link" href="marriage_transmission.php">
                <i class="fas fa-ring me-1"></i> Marriage
            </a>
        </li>

        <!-- Main Menu -->
        <li class="nav-item">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-ring me-1"></i> Home
            </a>
        </li>
    </ul>



            <!-- Right Side Menu -->
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <span class="nav-link text-white">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                </li>

                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </li>
            </ul>

        </div>
    </div>
</nav>



    <div class="app-container">
        <div class="controls-section">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-baby" style="font-size: 2.5rem; color: var(--blue-light);"></i>
                    <h5 class="mb-0" style="font-size: 1.2rem;">Birth Records - Registered</h5>
                </div>
                
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="stats-badge">
                        <i class="fas fa-list me-1"></i>Total: <strong id="total-count">0</strong>
                    </span>
                    <span class="stats-badge">
                        <i class="fas fa-file me-1"></i>Page <span id="current-page-display">1</span>/<span id="total-pages-display">1</span>
                    </span>
                    
                    <input type="text" id="search-input" class="form-control form-control-sm" 
                           placeholder="Search..." style="width: 200px; background: #2d3748; color: white; border-color: #4a5568;">
                    
                    <button class="btn btn-danger btn-sm" onclick="loadRecords()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item" id="first-page-btn">
                                <a class="page-link" href="javascript:void(0)" onclick="goToPage(1)">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item" id="prev-page-btn">
                                <a class="page-link" href="javascript:void(0)" onclick="goToPage(currentPage - 1)">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <li class="page-item active">
                                <a class="page-link" href="javascript:void(0)" id="current-page-number">1</a>
                            </li>
                            <li class="page-item" id="next-page-btn">
                                <a class="page-link" href="javascript:void(0)" onclick="goToPage(currentPage + 1)">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <li class="page-item" id="last-page-btn">
                                <a class="page-link" href="javascript:void(0)" onclick="goToPage(totalPages)">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb" style="background: transparent; padding: 0; margin: 0; font-size: 0.85rem;">
                    <li class="breadcrumb-item"><a href="dashboard.php" style="color: var(--blue-light);">Dashboard</a></li>
                    <li class="breadcrumb-item active" style="color: #ccc;">Birth Transmission</li>
                </ol>
            </nav>
        </div>

        <div class="table-scroll-container">
            <table>
                <thead>
                    <tr>
                        <th colspan="6" class="band-header">Child's Details</th>
                        <th colspan="2" class="band-header">Parent's Information</th>
                    </tr>
                    <tr>
                        <th>Registry #</th>
                        <th>First Name</th>
                        <th>Middle Name</th>
                        <th>Last Name</th>
                        <th>Birth Date</th>
                        <th>Gender</th>
                        <th>Mother's Name</th>
                        <th>Father's Name</th>
                    </tr>
                </thead>
                <tbody id="records-tbody">
                    <tr>
                        <td colspan="10" class="loading">
                            <i class="fas fa-spinner fa-spin fa-2x"></i><br>
                            Loading records...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
    let currentPage = 1;
    let totalPages = 1;
    
    document.addEventListener('DOMContentLoaded', function() {
        loadRecords();
        loadStats();
        
        document.getElementById('search-input').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                currentPage = 1;
                loadRecords();
            }
        });
        
        // NO AUTO-REFRESH - user clicks refresh button if needed
    });
    
    async function loadRecords() {
        const tbody = document.getElementById('records-tbody');
        const search = document.getElementById('search-input').value;
        
        tbody.innerHTML = '<tr><td colspan="11" class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
        
        try {
            const response = await fetch(`registry_api.php?action=list_registered_records&type=birth&page=${currentPage}&per_page=50&search=${encodeURIComponent(search)}`, {
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN
                }
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message);
            }
            
            const records = result.data.records;
            const pagination = result.data.pagination;
            
            currentPage = pagination.current_page;
            totalPages = pagination.total_pages;
            
            document.getElementById('current-page-display').textContent = currentPage;
            document.getElementById('total-pages-display').textContent = totalPages;
            document.getElementById('current-page-number').textContent = currentPage;
            
            updatePaginationButtons();
            
            if (records.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center">No records found</td></tr>';
                return;
            }
            
            tbody.innerHTML = records.map(r => `
                <tr class="clickable-row" onclick="viewRecord('${escapeHtml(r.registry_num)}')" style="cursor: pointer;" title="Click to view/send">
                    <td><strong>${escapeHtml(r.registry_num)}</strong></td>
                    <td>${escapeHtml(r.first_name)}</td>
                    <td>${escapeHtml(r.middle_name)}</td>
                    <td>${escapeHtml(r.last_name)}</td>
                    <td>${escapeHtml(r.birth_date)}</td>
                    <td>
                        ${r.sex === 'MALE' 
                        ? '<i class="fas fa-mars text-primary me-1"></i>Male' 
                        : '<i class="fas fa-venus text-danger me-1"></i>Female'}
                    </td>
                    <td>${escapeHtml(r.mother_name)}</td>
                    <td>${escapeHtml(r.father_name)}</td>
                </tr>
            `).join('');
            
        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error: ${escapeHtml(error.message)}</td></tr>`;
        }
    }
    
    function updatePaginationButtons() {
        document.getElementById('first-page-btn').classList.toggle('disabled', currentPage === 1);
        document.getElementById('prev-page-btn').classList.toggle('disabled', currentPage === 1);
        document.getElementById('next-page-btn').classList.toggle('disabled', currentPage === totalPages);
        document.getElementById('last-page-btn').classList.toggle('disabled', currentPage === totalPages);
    }
    
    function goToPage(page) {
        if (page < 1 || page > totalPages || page === currentPage) {
            return;
        }
        currentPage = page;
        loadRecords();
    }
    
    async function loadStats() {
        try {
            const response = await fetch('registry_api.php?action=get_registered_stats&type=birth', {
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('total-count').textContent = result.data.total.toLocaleString();
            }
        } catch (error) {
            console.error('Stats error:', error);
        }
    }
    
    function viewRecord(registryNum) {
        window.location.href = `birth_viewer.php?id=${encodeURIComponent(registryNum)}`;
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const year = d.getFullYear();
        return `${month}/${day}/${year}`;
    }
    </script>
</body>
</html>