<?php
require_once 'config/config.php';
require_once 'classes/MySQL_DatabaseManager.php';
require_once 'classes/SecurityHelper.php';

$error = '';

// Check for timeout message
if (isset($_GET['timeout'])) {
    $error = 'Session expired. Please login again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        try {
            $db = new MySQL_DatabaseManager();

            // Cast VARBINARY to CHAR (same as old working version)
            $sql = "SELECT 
                        Login_ID,
                        CAST(UserName AS CHAR) AS username,
                        CAST(Password AS CHAR) AS password,
                        User_Type
                    FROM table_login_f1
                    WHERE TRIM(CAST(UserName AS CHAR)) = ?
                    LIMIT 1";

            // IMPORTANT: use support database
            $user = $db->fetchOne($sql, [$username], 'support');

            if ($user && trim($user['password']) === $password) {

                // Prevent session fixation
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $user['Login_ID'];
                $_SESSION['username'] = trim($user['username']);
                $_SESSION['user_type'] = $user['User_Type'];
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();

                // Optional audit log
                if (class_exists('SecurityHelper')) {
                    SecurityHelper::auditLog('LOGIN_SUCCESS', "User logged in: {$username}");
                }

                header('Location: dashboard.php');
                exit;

            } else {

                if (class_exists('SecurityHelper')) {
                    SecurityHelper::auditLog('LOGIN_FAILED', "Failed login attempt: {$username}");
                }

                $error = 'Invalid username or password.';
            }

        } catch (Exception $e) {

            if (class_exists('SecurityHelper')) {
                SecurityHelper::auditLog('LOGIN_ERROR', "Login error: " . $e->getMessage());
            }

            $error = 'Login failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/dark-theme.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="icon-wrapper">
                <i class="fas fa-user-shield"></i>
            </div>
            <h2><?php echo APP_NAME; ?></h2>
            <p>Vital Records</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               name="username" 
                               placeholder="Enter username"
                               required
                               autofocus
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                            
                        </span>
                        <input type="password" 
                               class="form-control" 
                               name="password" 
                               placeholder="Enter password"
                               required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>
            
            <div class="text-center mt-4">
                <small class="text-normal" STYLE="Color: #0fe244">
                    <i class="fas fa-shield-alt me-1"></i>
                    Secure Access                
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>