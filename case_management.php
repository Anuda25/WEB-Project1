<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
requireRole(['Admin', 'Clerk']);

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle Delete
if (isset($_POST['delete_case'])) {
    $case_id = $_POST['case_id'];
    $stmt = $pdo->prepare('DELETE FROM cases WHERE CaseID = ?');
    if ($stmt->execute([$case_id])) {
        $success = "Case deleted successfully.";
    } else {
        $error = "Failed to delete case.";
    }
}

// Handle Add/Edit Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_case'])) {
    $case_id = $_POST['case_id'] ?? '';
    $title = trim($_POST['title']);
    $type = trim($_POST['type']);
    $date = $_POST['filing_date'];
    $status = $_POST['status'];
    $description = trim($_POST['description']);
    $user_id = $_SESSION['user_id'];

    if ($case_id) {
        // Update
        $stmt = $pdo->prepare('UPDATE cases SET CaseTitle=?, CaseType=?, FilingDate=?, Description=?, Status=? WHERE CaseID=?');
        if ($stmt->execute([$title, $type, $date, $description, $status, $case_id])) {
            $success = "Case updated successfully.";
            $action = 'list';
        } else {
            $error = "Failed to update case.";
        }
    } else {
        // Generate unique CaseNumber e.g., CASE-YYYY-XXXX
        $year = date('Y', strtotime($date));
        $stmt = $pdo->query('SELECT COUNT(*) FROM cases');
        $count = $stmt->fetchColumn() + 1;
        $case_number = sprintf("CASE-%s-%04d", $year, $count);

        $stmt = $pdo->prepare('INSERT INTO cases (CaseNumber, CaseTitle, CaseType, FilingDate, Description, Status, CreatedBy) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if ($stmt->execute([$case_number, $title, $type, $date, $description, $status, $user_id])) {
            $success = "Case added successfully. Case Number: $case_number";
            $action = 'list';
        } else {
            $error = "Failed to add case.";
        }
    }
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Case Management</h2>
    <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">Add New Case</a>
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

<?php if ($action === 'list'): ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Case No.</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Filing Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query('SELECT * FROM cases ORDER BY CreatedAt DESC');
                        $cases = $stmt->fetchAll();
                        foreach ($cases as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['CaseNumber']) ?></strong></td>
                                <td><?= htmlspecialchars($c['CaseTitle']) ?></td>
                                <td><?= htmlspecialchars($c['CaseType']) ?></td>
                                <td><?= date('M j, Y', strtotime($c['FilingDate'])) ?></td>
                                <td>
                                    <?php
                                    $badgeClass = 'bg-primary';
                                    if ($c['Status'] === 'Open') $badgeClass = 'bg-success';
                                    if ($c['Status'] === 'Closed') $badgeClass = 'bg-secondary';
                                    if ($c['Status'] === 'Appealed') $badgeClass = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($c['Status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?action=edit&id=<?= $c['CaseID'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this case?');">
                                        <input type="hidden" name="case_id" value="<?= $c['CaseID'] ?>">
                                        <button type="submit" name="delete_case" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                    <a href="case_details.php?id=<?= $c['CaseID'] ?>" class="btn btn-sm btn-outline-info">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cases)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No cases found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): 
    $case = null;
    if ($action === 'edit' && isset($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM cases WHERE CaseID = ?');
        $stmt->execute([$_GET['id']]);
        $case = $stmt->fetch();
    }
?>
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><?= $case ? 'Edit Case: ' . htmlspecialchars($case['CaseNumber']) : 'Register New Case' ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="?action=list">
                <?php if ($case): ?>
                    <input type="hidden" name="case_id" value="<?= $case['CaseID'] ?>">
                <?php endif; ?>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Case Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required value="<?= $case ? htmlspecialchars($case['CaseTitle']) : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Case Type <span class="text-danger">*</span></label>
                        <select name="type" class="form-select" required>
                            <option value="">Select Type</option>
                            <?php 
                            $types = ['Civil', 'Criminal', 'Family', 'Corporate', 'Probate'];
                            foreach ($types as $t) {
                                $selected = ($case && $case['CaseType'] === $t) ? 'selected' : '';
                                echo "<option value=\"$t\" $selected>$t</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Filing Date <span class="text-danger">*</span></label>
                        <input type="date" name="filing_date" class="form-control" required value="<?= $case ? $case['FilingDate'] : date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <?php 
                            $statuses = ['Open', 'In Progress', 'Closed', 'Appealed'];
                            foreach ($statuses as $s) {
                                $selected = ($case && $case['Status'] === $s) ? 'selected' : '';
                                echo "<option value=\"$s\" $selected>$s</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Case Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= $case ? htmlspecialchars($case['Description']) : '' ?></textarea>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" name="save_case" class="btn btn-primary px-4"><?= $case ? 'Update Case' : 'Register Case' ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
