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

<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; padding: 40px 20px; color: #333; }
    .container { max-width: 600px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); box-sizing: border-box; }
    h4 { text-align: center; color: #2c3e50; margin-top: 0; margin-bottom: 30px; font-weight: 600; font-size: 24px; border-bottom: 2px solid #ecf0f1; padding-bottom: 15px; }  
   
    .form-group { margin-bottom: 20px; text-align: left; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; font-size: 14px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; font-family: inherit; }
    .form-control:focus { border-color: #3498db; outline: none; }
    
    .text-muted { display: block; margin-top: 5px; font-size: 12px; color: #7f8c8d; font-style: italic; }
    .text-danger { color: #e74c3c; font-weight: bold; }
      
    .btn { padding: 12px 20px; cursor: pointer; border: none; border-radius: 4px; font-weight: 600; font-size: 15px; display: block; text-decoration: none; text-align: center; box-sizing: border-box; }
    .btn-add { background: #2ecc71; color: white; width: 100%; }
    .btn-add:hover { background: #27ae60; }
    .btn-load { background: #3498db; color: white; width: 100%; margin-top: 15px; }
    .btn-load:hover { background: #2980b9; }
     
    .link-wrapper { text-align: center; margin-top: 20px; }
    .link-wrapper a { color: #3498db; text-decoration: none; font-size: 14px; font-weight: 600; }
    .link-wrapper a:hover { text-decoration: underline; }
  
    .alert { padding: 12px 20px; margin-bottom: 20px; border-radius: 4px; font-weight: 600; font-size: 14px; }
    .alert-danger { background-color: #fde8e8; color: #e74c3c; border-left: 4px solid #e74c3c; }
    .alert-success { background-color: #eafaf1; color: #2ecc71; border-left: 4px solid #2ecc71; }
</style>

<div class="container">
    <h4>📝 Register New Account</h4>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <div style="margin-top: 20px;">
            <a href="login.php" class="btn btn-load">Go to Login</a>
        </div>
    <?php else: ?>
        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required placeholder="John Doe">
            </div>
            
            <div class="form-group">
                <label>Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" required placeholder="john@example.com">
            </div>
            
            <div class="form-group">
                <label>Password <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" required placeholder="••••••••">
                <small class="text-muted">Minimum 6 characters</small>
            </div>
            
            <div class="form-group">
                <label>Confirm Password <span class="text-danger">*</span></label>
                <input type="password" name="confirm_password" class="form-control" required placeholder="••••••••">
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" class="form-control" placeholder="+123456789">
            </div>
            
            <div class="form-group">
                <label>Register As <span class="text-danger">*</span></label>
                <select name="role" class="form-control" required>
                    <option value="">Select User Type</option>
                    <option value="Lawyer">Lawyer</option>
                    <option value="Clerk">Clerk</option>
                    <option value="Judge">Judge</option>
                </select>
                <small class="text-muted">Note: Admin accounts can only be created by existing Admins</small>
            </div>
            
            <button type="submit" class="btn btn-add">Register</button>
        </form>
        
        <div class="link-wrapper">
            <a href="login.php">Already have an account? Login here</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
