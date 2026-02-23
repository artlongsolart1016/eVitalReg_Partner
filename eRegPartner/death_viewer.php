<?php
/**
 * Death Viewer - Compact for 15" Monitors
 * Exact match to screenshot layout
 */
require_once 'config/config.php';
require_once 'classes/SecurityHelper.php';

SecurityHelper::requireLogin();

$registryNum = $_GET['id'] ?? '';

if (empty($registryNum)) {
    header('Location: death_transmission.php');
    exit;
}

$csrfToken = SecurityHelper::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Death Record - <?php echo htmlspecialchars($registryNum); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #0a0e27;
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        .navbar {
            background: #000040 !important;
            padding: 8px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar-left h4 {
            margin: 0;
            font-size: 1rem;
            color: white;
        }
        
        .navbar-left small {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.8rem;
        }
        
        .navbar-right {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            border: 2px solid;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-sent {
            background: transparent;
            color: #00d9ff;
            border-color: #00d9ff;
        }
        
        .btn-nav {
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-send-nav {
            background: #667eea;
            color: white;
        }
        
        .btn-back-nav {
            background: #1e293b;
            color: white;
        }
        
        .main-container {
            padding: 15px 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .info-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .card-header {
            background: #0d47a1;
            color: white;
            padding: 10px 15px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .card-body {
            padding: 15px;
        }
        
        .info-row {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 10px;
            padding: 8px 0;
            font-size: 0.85rem;
            border-bottom: 1px solid #334155;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
        }
        
        .info-value {
            color: #ffffff;
            font-weight: 500;
        }
        
        .send-history-box {
            background: #1e293b;
            border: 1px solid #00d9ff;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 15px;
            display: none;
        }
        
        .send-history-box.show {
            display: block;
        }
        
        .history-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #334155;
            font-size: 0.85rem;
        }
        
        .history-subtitle {
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        
        .history-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            font-size: 0.8rem;
            margin-bottom: 10px;
        }
        
        .history-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .history-item-label {
            color: #64748b;
            font-size: 0.7rem;
        }
        
        .history-item-value {
            color: #ffffff;
            font-weight: 500;
        }
        
        .history-item-value.mobile {
            color: #00ff00;
            font-weight: 700;
        }
        
        .history-tip {
            padding: 8px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 5px;
            font-size: 0.75rem;
            color: #94a3b8;
        }
        
        .no-history {
            text-align: center;
            padding: 40px 20px;
            display: none;
        }
        
        .no-history.show {
            display: block;
        }
        
        .no-history h3 {
            color: #ef4444;
            font-size: 1.2rem;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-label {
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
            text-transform: uppercase;
            font-size: 0.75rem;
        }
        
        .form-control {
            background: #0f172a;
            border: 1px solid #334155;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            width: 100%;
            font-size: 0.85rem;
        }
        
        .form-control:focus {
            background: #1e293b;
            border-color: #667eea;
            color: white;
            outline: none;
        }
        
        .file-input-wrapper {
            position: relative;
        }
        
        .file-input-wrapper input[type="file"] {
            opacity: 0;
            position: absolute;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-display {
            background: #0f172a;
            border: 1px solid #334155;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .file-button {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div style="text-align: center; color: white;">
            <i class="fas fa-spinner fa-spin" style="font-size: 3rem;"></i>
            <h4 class="mt-3">Loading...</h4>
        </div>
    </div>

    <nav class="navbar">
        <div class="navbar-left">
            <h4><i class="fas fa-cross me-2"></i>DEATH RECORD</h4>
            <small>Registry: <span id="navbar-registry-num">Loading...</span></small>
        </div>
        <div class="navbar-right">
            <span class="status-badge status-sent" id="navbar-status" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span>Sent 0 time(s)</span>
            </span>
            <button type="button" class="btn-nav btn-send-nav" onclick="submitSendFromNav()">
                <i class="fas fa-paper-plane me-1"></i>Send Record
            </button>
            <button type="button" class="btn-nav btn-back-nav" onclick="goBack()">
                <i class="fas fa-arrow-left me-1"></i>Back
            </button>
        </div>
    </nav>

    <div class="main-container">
        <div class="two-column">
            <div>
                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-user me-2"></i>DECEASED INFORMATION
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div class="info-label">Registry Number:</div>
                            <div class="info-value" id="registry-num">-</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">First Name:</div>
                            <div class="info-value" id="first-name">-</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Middle Name:</div>
                            <div class="info-value" id="middle-name">-</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Last Name:</div>
                            <div class="info-value" id="last-name">-</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Sex:</div>
                            <div class="info-value" id="sex">-</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Date of Birth:</div>
                            <div class="info-value" id="birth-date">-</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Date of Death:</div>
                            <div class="info-value" id="death-date">-</div>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-female me-2"></i>MOTHER'S INFORMATION
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div class="info-label">Full Name:</div>
                            <div class="info-value" id="mother-name">-</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Citizenship:</div>
                            <div class="info-value" id="mother-citizenship">-</div>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-male me-2"></i>FATHER'S INFORMATION
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div class="info-label">Full Name:</div>
                            <div class="info-value" id="father-name">-</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Citizenship:</div>
                            <div class="info-value" id="father-citizenship">-</div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-paper-plane me-2"></i>SEND TO PARTNER
                    </div>
                    <div class="card-body">
                        <div id="send-history" class="send-history-box">
                            <div class="history-title">
                                <i class="fas fa-info-circle" style="color: #00d9ff;"></i>
                                <strong>This record has been sent <span id="sent-count">0</span> time(s)</strong>
                            </div>
                            <div class="history-subtitle">
                                <i class="fas fa-list me-1"></i>PREVIOUS SEND DETAILS:
                            </div>
                            <div class="history-grid">
                                <div class="history-item">
                                    <div class="history-item-label">Last Sent:</div>
                                    <div class="history-item-value" id="last-sent-date">-</div>
                                </div>
                                <div class="history-item">
                                    <div class="history-item-label">Sent By:</div>
                                    <div class="history-item-value" id="last-sent-by">-</div>
                                </div>
                                <div class="history-item">
                                    <div class="history-item-label">Mobile Number:</div>
                                    <div class="history-item-value mobile">
                                        <i class="fas fa-phone me-1"></i>
                                        <span id="last-mobile-number">-</span>
                                    </div>
                                </div>
                                <div class="history-item">
                                    <div class="history-item-label">Attachment:</div>
                                    <div class="history-item-value" id="last-attachment-info">-</div>
                                </div>
                            </div>
                            <div class="history-tip">
                                <i class="fas fa-lightbulb me-1"></i>
                                <strong>Tip:</strong> You can use the same mobile number or update it below
                            </div>
                        </div>

                        <div id="no-history" class="no-history">
                            <h3>No Record's History</h3>
                        </div>

                        <form id="send-form">
                            <input type="hidden" name="registry_num" id="form-registry-num">
                            <input type="hidden" name="send_action" id="form-action" value="SEND">
                            <input type="hidden" name="type" value="death">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            
                            <div class="form-group">
                                <label class="form-label">Mobile Number *</label>
                                <input type="tel" class="form-control" name="mobile_number" 
                                       pattern="[0-9]{11}" maxlength="11" required
                                       placeholder="09123456789">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Date *</label>
                                <input type="date" class="form-control" name="send_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">PDF Attachment (Optional)</label>
                                <div class="file-input-wrapper">
                                    <input type="file" name="pdf_file" id="pdf-file" accept=".pdf" onchange="updateFileName(this)">
                                    <div class="file-input-display">
                                        <span id="file-name-display">No file chosen</span>
                                        <span class="file-button">Choose File</span>
                                    </div>
                                </div>
                                <small style="color: #64748b; font-size: 0.75rem;">Max: 128MB</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Remarks</label>
                                <textarea class="form-control" name="remarks" rows="3" 
                                          placeholder="Add any remarks or notes..."></textarea>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
    const REGISTRY_NUM = '<?php echo htmlspecialchars($registryNum); ?>';
    let recordData = null;
    
    document.addEventListener('DOMContentLoaded', function() {
        loadRecord();
    });
    
    function updateFileName(input) {
        const fileName = input.files[0] ? input.files[0].name : 'No file chosen';
        document.getElementById('file-name-display').textContent = fileName;
    }
    
    async function loadRecord() {
        document.getElementById('loadingOverlay').style.display = 'flex';
        
        try {
            const response = await fetch(`registry_api.php?action=get_record&type=death&registry_num=${encodeURIComponent(REGISTRY_NUM)}`, {
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN
                }
            });
            
            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Server Response:', text);
                throw new Error('Server returned invalid response.');
            }
            
            if (!result.success) {
                throw new Error(result.message);
            }
            
            recordData = result.data;
            displayRecord(recordData);
            
        } catch (error) {
            alert('Error loading record: ' + error.message);
            goBack();
        } finally {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
    }
    
    function displayRecord(data) {
        document.getElementById('navbar-registry-num').textContent = data.RegistryNum;
        
        const statusBadge = document.getElementById('navbar-status');
        const sendHistory = document.getElementById('send-history');
        const noHistory = document.getElementById('no-history');
        
        if (data.sent_count > 0) {
            statusBadge.style.display = 'inline-flex';
            statusBadge.innerHTML = '<i class="fas fa-check-circle"></i><span>Sent ' + data.sent_count + ' time(s)</span>';
            
            sendHistory.classList.add('show');
            noHistory.classList.remove('show');
            document.getElementById('sent-count').textContent = data.sent_count;
            
            if (data.log) {
                document.getElementById('last-sent-date').textContent = 
                    data.log.ActionDate ? formatDateTime(data.log.ActionDate) : '-';
                document.getElementById('last-sent-by').textContent = 
                    data.log.Performed_By || 'arthur';
                document.getElementById('last-mobile-number').textContent = 
                    data.log.ContactNumber || '-';
                
                let attachmentInfo = 'None';
                if (data.log.AttachmentType && data.log.AttachmentType !== 'NO') {
                    const sizeMB = (data.log.AttachmentSize / (1024 * 1024)).toFixed(2);
                    attachmentInfo = `📎 PDF (${sizeMB} MB)`;
                }
                document.getElementById('last-attachment-info').textContent = attachmentInfo;
            }
            
            document.getElementById('form-action').value = 'RESEND';
        } else {
            statusBadge.style.display = 'none';
            sendHistory.classList.remove('show');
            noHistory.classList.add('show');
        }
        
        document.getElementById('registry-num').textContent = data.RegistryNum || '-';
        document.getElementById('first-name').textContent = data.CFirstName || '-';
        document.getElementById('middle-name').textContent = data.CMiddleName || '-';
        document.getElementById('last-name').textContent = data.CLastName || '-';
        
        let sexValue = 'N/A';
        if (data.CSexId) {
            if (data.CSexId === '1' || data.CSexId === 1) sexValue = 'Male';
            else if (data.CSexId === '2' || data.CSexId === 2) sexValue = 'Female';
        } else if (data.CSex) {
            sexValue = data.CSex;
        }
        document.getElementById('sex').textContent = sexValue;
        
        document.getElementById('birth-date').textContent = data.CBirthDate ? formatDate(data.CBirthDate) : '-';
        document.getElementById('death-date').textContent = data.CDeathDate ? formatDate(data.CDeathDate) : '-';
        
        const motherName = [data.MFirstName, data.MMiddleName, data.MLastName].filter(Boolean).join(' ');
        document.getElementById('mother-name').textContent = motherName || '-';
        document.getElementById('mother-citizenship').textContent = data.MCitizenship || '-';
        
        const fatherName = [data.FFirstName, data.FMiddleName, data.FLastName].filter(Boolean).join(' ');
        document.getElementById('father-name').textContent = fatherName || '-';
        document.getElementById('father-citizenship').textContent = data.FCitizenship || '-';
        
        document.getElementById('form-registry-num').value = data.RegistryNum;
    }
    
    function formatDateTime(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const year = d.getFullYear();
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        return `${month}/${day}/${year} ${hours}:${minutes}`;
    }
    
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const year = d.getFullYear();
        return `${month}/${day}/${year}`;
    }
    
    function submitSendFromNav() {
        const form = document.getElementById('send-form');
        
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        submitSend();
    }
    
    async function submitSend() {
        const form = document.getElementById('send-form');
        const formData = new FormData(form);
        formData.append('action', 'send_record');
        
        const sendBtn = document.querySelector('.btn-send-nav');
        const originalHtml = sendBtn.innerHTML;
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';
        
        try {
            const response = await fetch('registry_api.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: formData
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message);
            }
            
            alert('✅ ' + result.message);
            loadRecord();
            
            form.reset();
            document.getElementById('form-registry-num').value = recordData.RegistryNum;
            document.getElementById('file-name-display').textContent = 'No file chosen';
            
        } catch (error) {
            alert('❌ Error: ' + error.message);
        } finally {
            sendBtn.disabled = false;
            sendBtn.innerHTML = originalHtml;
        }
    }
    
    function goBack() {
        window.location.href = 'death_transmission.php';
    }
    </script>
</body>
</html>