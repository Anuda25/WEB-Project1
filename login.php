<?php
require_once 'config/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT UserID, Name, Password, Role, Status FROM users WHERE Email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['Password'])) {
            if ($user['Status'] === 'Inactive') {
                $error = 'Your account is inactive. Please contact the administrator.';
            } else {
                $_SESSION['user_id'] = $user['UserID'];
                $_SESSION['user_name'] = $user['Name'];
                $_SESSION['role'] = $user['Role'];
                
                header("Location: dashboard.php");
                exit;
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

require_once 'includes/header.php';
?>
<div class="row justify-content-center align-items-center" style="min-height: 70vh;">
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0">Court System Login</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Login</button>
                    <div class="text-center mt-3">
                        <a href="register.php" class="text-decoration-none">Don't have an account? Register here</a>
                    </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
