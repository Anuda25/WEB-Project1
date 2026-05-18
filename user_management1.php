<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
requireRole('Admin');

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';


if (isset($_POST['approve_user'])) {
    $uid = $_POST['user_id'];
    $stmt = $pdo->prepare('UPDATE users SET Status = "Active" WHERE UserID = ?');
    if ($stmt->execute([$uid])) {
        $success = "User approved successfully.";
        
       
        $user_stmt = $pdo->prepare('SELECT Email, Name FROM users WHERE UserID = ?');
        $user_stmt->execute([$uid]);
        $user_data = $user_stmt->fetch();
        
        
        $notify_stmt = $pdo->prepare("INSERT INTO notifications (UserID, Message) VALUES (?, ?)");
        $notify_stmt->execute([$uid, "Your account has been approved by Admin. You can now login."]);
    } else {
        $error = "Failed to approve user.";
    }
}


if (isset($_POST['delete_user'])) {
    $uid = $_POST['user_id'];
    if ($uid == $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        $stmt = $pdo->prepare('DELETE FROM users WHERE UserID = ?');
        if ($stmt->execute([$uid])) {
            $success = "User deleted successfully.";
        } else {
            $error = "Failed to delete user.";
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $uid = $_POST['user_id'] ?? '';
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $phone = trim($_POST['phone']);
    $status = $_POST['status'];
    $password = $_POST['password'] ?? '';

    if ($uid) {
        
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET Name=?, Email=?, Password=?, Role=?, Phone=?, Status=? WHERE UserID=?');
            $exec = $stmt->execute([$name, $email, $hash, $role, $phone, $status, $uid]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET Name=?, Email=?, Role=?, Phone=?, Status=? WHERE UserID=?');
            $exec = $stmt->execute([$name, $email, $role, $phone, $status, $uid]);
        }
        
        if ($exec) {
            $success = "User updated successfully.";
            $action = 'list';
        } else {
            $error = "Failed to update user. Email might be in use.";
        }
    } else {
       
        if (empty($password)) {
            $error = "Password is required for new users.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (Name, Email, Password, Role, Phone, Status) VALUES (?, ?, ?, ?, ?, ?)');
            try {
                if ($stmt->execute([$name, $email, $hash, $role, $phone, $status])) {
                    $success = "User added successfully.";
                    $action = 'list';
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Email already exists.";
                } else {
                    $error = "Failed to add user.";
                }
            }
        }
    }
}

require_once 'includes/header.php';
?>

<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; color: #333; }
    h2, h3, h5 { color: #2c3e50; font-weight: 600; }
    
    
    .form-control, .form-select { padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; color: #333; }
    .form-control:focus, .form-select:focus { border-color: #3498db; box-shadow: none; outline: none; }
    .form-label { font-weight: bold ; color: #555; margin-bottom: 8px; }
    
   
    .btn-primary { background: #2ecc71; border: none; padding: 10px 20px; border-radius: 4px; font-weight: 600; font-size: 14px; color: white; }
    .btn-primary:hover { background: #27ae60; }
    .btn-secondary { background: #3498db; border: none; padding: 10px 20px; border-radius: 4px; font-weight: 600; font-size: 14px; color: white; }
    .btn-secondary:hover { background: #2980b9; }
    .btn-outline-primary { border: 1px solid #3498db; color: #3498db; background: transparent; }
    .btn-outline-primary:hover { background: #3498db; color: white; }
    
    
    .btn-danger, .btn-outline-danger { background: #e74c3c; border: none; color: white; padding: 5px 10px; font-size: 12px; border-radius: 3px; }
    .btn-danger:hover, .btn-outline-danger:hover { background: #c0392b; color: white; }
    .btn-success { background: #2ecc71; border: none; color: white; padding: 5px 10px; font-size: 12px; border-radius: 3px; }
    .btn-success:hover { background: #27ae60 ; }

   
    table { border-collapse: collapse ; background: white; }
    th { background-color: #2c3e50; color: white; font-weight: 600; border: none; }
    td { border-bottom: 1px solid #e0e0e0; color: #333; }
    tr:hover { background-color: #f9f9f9; }
    
   
    .badge { padding: 0.25em 0.4em; font-size: 75%; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 10px; }
    .bg-success { background-color: #2ecc71; color: white; }
    .bg-warning { background-color: #f1c40f; color: #2c3e50; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>User Management</h2>
    <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">Add New User</a>
    <?php else: ?>
        <a href="?action=list" class="btn btn-secondary">Back to List</a>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($action === 'list'): 
    
    $pending_stmt = $pdo->prepare("SELECT UserID, Name, Email, Role, Phone, CreatedAt 
                                  FROM users 
                                  WHERE Status = 'Inactive' 
                                  ORDER BY CreatedAt DESC");
    $pending_stmt->execute();
    $pending_users = $pending_stmt->fetchAll();
    
   
    $users_stmt = $pdo->prepare("SELECT UserID, Name, Email, Role, Status, CreatedAt 
                                FROM users 
                                WHERE Status = 'Active' 
                                ORDER BY Name ASC");
    $users_stmt->execute();
    $users = $users_stmt->fetchAll();
?>

<?php if (count($pending_users) > 0): ?>
<div class="card shadow-sm mb-4 border-warning">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0">⏳ Pending Approvals (<?= count($pending_users) ?>)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_users as $pu): ?>
                    <tr>
                        <td><?= htmlspecialchars($pu['Name']) ?></td>
                        <td><?= htmlspecialchars($pu['Email']) ?></td>
                        <td><?= htmlspecialchars($pu['Role']) ?></td>
                        <td><?= htmlspecialchars($pu['Phone'] ?? 'N/A') ?></td>
                        <td><?= date('M j, Y', strtotime($pu['CreatedAt'])) ?></td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="user_id" value="<?= $pu['UserID'] ?>">
                                <button type="submit" name="approve_user" class="btn btn-sm btn-success">Approve</button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="user_id" value="<?= $pu['UserID'] ?>">
                                <button type="submit" name="delete_user" class="btn btn-sm btn-danger" onclick="return confirm('Reject this registration?')">Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Active Users</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['Name']) ?></td>
                            <td><?= htmlspecialchars($u['Email']) ?></td>
                            <td><?= htmlspecialchars($u['Role']) ?></td>
                            <td><?= htmlspecialchars($u['Phone'] ?? 'N/A') ?></td>
                            <td>
                                <span class="badge <?= $u['Status'] === 'Active' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= htmlspecialchars($u['Status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="?action=edit&id=<?= $u['UserID'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <?php if ($u['UserID'] != $_SESSION['user_id']): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="user_id" value="<?= $u['UserID'] ?>">
                                    <button type="submit" name="delete_user" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                                <?php endif; ?>
                             </td>
                         </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6" class="text-center text-muted">No active users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($action === 'add' || $action === 'edit'): 
    $user = null;
    if ($action === 'edit' && isset($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE UserID = ?');
        $stmt->execute([$_GET['id']]);
        $user = $stmt->fetch();
        if (!$user) {
            die("User not found.");
        }
    }
?>
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><?= $user ? 'Edit User' : 'Add New User' ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <?php if ($user): ?>
                    <input type="hidden" name="user_id" value="<?= $user['UserID'] ?>">
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?= $user ? htmlspecialchars($user['Name']) : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required value="<?= $user ? htmlspecialchars($user['Email']) : '' ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Password <?= $user ? '<small class="text-muted">(Leave blank to keep current)</small>' : '<span class="text-danger">*</span>' ?></label>
                        <input type="password" name="password" class="form-control" <?= $user ? '' : 'required' ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?= $user ? htmlspecialchars($user['Phone'] ?? '') : '' ?>">
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <?php 
                            $roles = ['Admin', 'Clerk', 'Judge', 'Lawyer'];
                            foreach ($roles as $r) {
                                $selected = ($user && $user['Role'] === $r) ? 'selected' : '';
                                echo "<option value=\"$r\" $selected>$r</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="Active" <?= ($user && $user['Status'] === 'Active') ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= ($user && $user['Status'] === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" name="save_user" class="btn btn-primary px-4"><?= $user ? 'Update User' : 'Add User' ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>