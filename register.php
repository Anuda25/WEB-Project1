<?php
require_once 'config/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    
    if (empty($name) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $stmt = $pdo->prepare('SELECT UserID FROM users WHERE Email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already registered. Please login.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (Name, Email, Password, Role, Phone, Status) VALUES (?, ?, ?, ?, ?, ?)');
            
            if ($stmt->execute([$name, $email, $hash, $role, $phone, 'Inactive'])) {
                $success = "Registration successful! Please wait for Admin approval before logging in.";
                
                $pdo->prepare("INSERT INTO notifications (UserID, Message) 
                              SELECT UserID, 'New user registration pending approval: $name ($role)' 
                              FROM users WHERE Role = 'Admin'")->execute();
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0">Register New Account</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Register As <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="">Select User Type</option>
                                <option value="Lawyer">Lawyer</option>
                                <option value="Clerk">Clerk</option>
                                <option value="Judge">Judge</option>
                            </select>
                            <small class="text-muted">Note: Admin accounts can only be created by existing Admins</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Register</button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="login.php">Already have an account? Login here</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>