<?php
session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            try {
                $query = "SELECT id, username, password_hash, full_name, role, is_active 
                         FROM users WHERE username = :username AND is_active = 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                
                echo "<!-- Debug: Query executed, row count: " . $stmt->rowCount() . " -->";
                
                if ($stmt->rowCount() == 1) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Debug output
                    echo "<!-- Debug: User found -->";
                    echo "<!-- Debug: Input password: " . $password . " -->";
                    echo "<!-- Debug: Stored hash: " . $user['password_hash'] . " -->";
                    echo "<!-- Debug: Password verify result: " . (password_verify($password, $user['password_hash']) ? 'TRUE' : 'FALSE') . " -->";
                    
                    // Verify password
                    if (password_verify($password, $user['password_hash'])) {
                        // Password is correct, start session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Update last login
                        $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :id";
                        $updateStmt = $db->prepare($updateQuery);
                        $updateStmt->bindParam(':id', $user['id']);
                        $updateStmt->execute();
                        
                        // Redirect to dashboard
                        header("Location: index.php");
                        exit;
                    } else {
                        $error = "Invalid username or password. (Password mismatch)";
                    }
                } else {
                    $error = "Invalid username or password. (User not found)";
                }
            } catch (Exception $e) {
                $error = "Login error. Please try again. Error: " . $e->getMessage();
            }
        } else {
            $error = "Database connection error.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .login-body {
            padding: 30px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            font-weight: 600;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            color: white;
        }
        .test-accounts {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.9em;
        }
        .debug-info {
            background: #e9ecef;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.8em;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <h2><i class="fas fa-warehouse"></i></h2>
                    <h3>Inventory Management</h3>
                    <p class="mb-0">Sign in to your account</p>
                </div>
                
                <div class="login-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <i class="fas fa-user"></i> Username
                            </label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                   required autofocus>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock"></i> Password
                            </label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-login btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Sign In
                            </button>
                        </div>
                    </form>
                    
                    <div class="test-accounts">
                        <h6><i class="fas fa-info-circle"></i> Test Accounts:</h6>
                        <div class="row">
                            <div class="col-12 mb-2">
                                <strong>Admin:</strong> admin / admin123
                            </div>
                            <div class="col-12 mb-2">
                                <strong>Manager:</strong> manager1 / admin123
                            </div>
                            <div class="col-12">
                                <strong>Supervisor:</strong> supervisor1 / admin123
                            </div>
                        </div>
                    </div>
                    
                    <!-- Debug info will appear here -->
                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <div class="debug-info">
                        <strong>Debug Info:</strong><br>
                        View page source to see detailed debug information.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>