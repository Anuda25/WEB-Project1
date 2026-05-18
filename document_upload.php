<?php
require_once 'config/db.php'; //connect the database
require_once 'includes/auth.php';//check login function
requireLogin();//check login 

//check Id and user Role
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$error = '';
$success = '';

//automatically genarate folder to store data
$upload_directory = __DIR__ . '/uploads/';
if (!is_dir($upload_directory)) {
    @mkdir($upload_directory, 0755, true);
}

//Access the file Delete (Admin/Clerk)
if (isset($_POST['delete_doc']) && hasRole(['Admin', 'Clerk'])) {
    $doc_id = $_POST['doc_id'];
    $stmt = $pdo->prepare('SELECT FilePath FROM documents WHERE DocumentID = ?');
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();
    
    if ($doc) {
        $file_path = $upload_dir . $doc['FilePath'];
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
        $pdo->prepare('DELETE FROM documents WHERE DocumentID = ?')->execute([$doc_id]);
        $success = "Document deleted successfully.";
    }
}

// Handle Status Update (Admin/Clerk only)
if (isset($_POST['update_status']) && hasRole(['Admin', 'Clerk'])) {
    $doc_id = $_POST['doc_id'];
    $status = $_POST['status'];
    $pdo->prepare('UPDATE documents SET DocumentStatus = ? WHERE DocumentID = ?')->execute([$status, $doc_id]);
    $success = "Document status updated.";
}

// Handle Document Download
if (isset($_GET['download'])) {
    $doc_id = $_GET['download'];
    $stmt = $pdo->prepare('SELECT FileName, FilePath FROM documents WHERE DocumentID = ?');
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();
    
    if ($doc) {
        $file_path = $upload_dir . $doc['FilePath'];
        if (file_exists($file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($doc['FileName']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            $error = "File not found on server.";
        }
    }
}

// Handle Upload
if (isset($_POST['upload_doc'])) {
    $case_id = $_POST['case_id'];
    $file = $_FILES['document'];
    
    $allowed_exts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed_exts)) {
        $error = "Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload failed. Error code: " . $file['error'];
    } else {
        // Prevent path traversal issues
        $safe_filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($file['name']));
        $new_filename = uniqid() . '_' . $safe_filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_filename)) {
            $stmt = $pdo->prepare('INSERT INTO documents (CaseID, FileName, FilePath, FileType, UploadedBy, DocumentStatus) VALUES (?, ?, ?, ?, ?, ?)');
            if ($stmt->execute([$case_id, $file['name'], $new_filename, $ext, $user_id, 'Pending'])) {
                $success = "Document uploaded successfully.";
                
                // Add notification for Admin/Clerk if uploaded by Lawyer
                if ($role === 'Lawyer') {
                    $pdo->query("INSERT INTO notifications (UserID, Message) 
                                 SELECT UserID, 'New document uploaded for case ID $case_id' 
                                 FROM users WHERE Role IN ('Admin', 'Clerk')");
                }
            } else {
                $error = "Failed to save document record.";
            }
        } else {
            $error = "Failed to move uploaded file. Check directory permissions.";
        }
    }
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Document Management</h2>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row">
    <!-- Upload Form -->
    <?php if (hasRole(['Admin', 'Clerk', 'Lawyer'])): ?>
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Upload Document</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Select Case <span class="text-danger">*</span></label>
                        <select name="case_id" class="form-select" required>
                            <option value="">-- Select Case --</option>
                            <?php
                            // Lawyers only see assigned cases
                            if ($role === 'Lawyer') {
                                $stmt = $pdo->prepare('SELECT c.CaseID, c.CaseNumber, c.CaseTitle FROM cases c JOIN case_assignments ca ON c.CaseID = ca.CaseID WHERE ca.UserID = ?');
                                $stmt->execute([$user_id]);
                            } else {
                                $stmt = $pdo->query('SELECT CaseID, CaseNumber, CaseTitle FROM cases');
                            }
                            $cases = $stmt->fetchAll();
                            foreach ($cases as $c) {
                                echo "<option value=\"{$c['CaseID']}\">{$c['CaseNumber']} - {$c['CaseTitle']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Document File <span class="text-danger">*</span></label>
                        <input type="file" name="document" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        <div class="form-text">Max size varies by server. Allowed: PDF, DOC, DOCX, JPG, PNG</div>
                    </div>
                    <button type="submit" name="upload_doc" class="btn btn-primary w-100">Upload File</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Document List -->
    <div class="col-md-<?= hasRole(['Admin', 'Clerk', 'Lawyer']) ? '8' : '12' ?>">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Uploaded Documents</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>File Name</th>
                                <th>Case</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT d.*, c.CaseNumber, u.Name as UploaderName 
                                      FROM documents d 
                                      JOIN cases c ON d.CaseID = c.CaseID 
                                      LEFT JOIN users u ON d.UploadedBy = u.UserID";
                            
                            // If Judge or Lawyer, only show documents related to their assigned cases
                            if ($role === 'Judge' || $role === 'Lawyer') {
                                $query .= " JOIN case_assignments ca ON c.CaseID = ca.CaseID WHERE ca.UserID = ?";
                                $stmt = $pdo->prepare($query . " ORDER BY d.UploadDate DESC");
                                $stmt->execute([$user_id]);
                            } else {
                                $stmt = $pdo->query($query . " ORDER BY d.UploadDate DESC");
                            }
                            
                            $docs = $stmt->fetchAll();
                            foreach ($docs as $d): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($d['FileName']) ?></strong>
                                        <br><small class="text-muted">By: <?= htmlspecialchars($d['UploaderName'] ?? 'Unknown') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($d['CaseNumber']) ?></td>
                                    <td><?= date('M j, Y', strtotime($d['UploadDate'])) ?></td>
                                    <td>
                                        <?php if (hasRole(['Admin', 'Clerk'])): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="doc_id" value="<?= $d['DocumentID'] ?>">
                                            <select name="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                <option value="Pending" <?= $d['DocumentStatus'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="Verified" <?= $d['DocumentStatus'] === 'Verified' ? 'selected' : '' ?>>Verified</option>
                                                <option value="Rejected" <?= $d['DocumentStatus'] === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                        <?php else: ?>
                                            <?php
                                            $bClass = 'bg-warning text-dark';
                                            if ($d['DocumentStatus'] === 'Verified') $bClass = 'bg-success';
                                            if ($d['DocumentStatus'] === 'Rejected') $bClass = 'bg-danger';
                                            ?>
                                            <span class="badge <?= $bClass ?>"><?= htmlspecialchars($d['DocumentStatus']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?download=<?= $d['DocumentID'] ?>" class="btn btn-sm btn-outline-info">Download</a>
                                        <?php if (hasRole(['Admin', 'Clerk'])): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this document permanently?');">
                                            <input type="hidden" name="doc_id" value="<?= $d['DocumentID'] ?>">
                                            <button type="submit" name="delete_doc" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($docs)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No documents found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
