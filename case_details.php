<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
requireLogin();

if (!isset($_GET['id'])) {
    header("Location: case_tracking.php");
    exit;
}

$case_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch Case Details
$stmt = $pdo->prepare('SELECT c.*, u.Name as CreatorName FROM cases c LEFT JOIN users u ON c.CreatedBy = u.UserID WHERE c.CaseID = ?');
$stmt->execute([$case_id]);
$case = $stmt->fetch();

if (!$case) {
    die("Case not found.");
}

// Role Check for Judges & Lawyers
if ($role === 'Judge' || $role === 'Lawyer') {
    $stmt = $pdo->prepare('SELECT 1 FROM case_assignments WHERE CaseID = ? AND UserID = ?');
    $stmt->execute([$case_id, $user_id]);
    if (!$stmt->fetch()) {
        die("Access Denied: You are not assigned to this case.");
    }
}

// Fetch Assignments
$stmt = $pdo->prepare('SELECT ca.*, u.Name, u.Role FROM case_assignments ca JOIN users u ON ca.UserID = u.UserID WHERE ca.CaseID = ?');
$stmt->execute([$case_id]);
$assignments = $stmt->fetchAll();

// Fetch Hearings
$stmt = $pdo->prepare('SELECT h.*, cr.RoomNumber, u.Name as JudgeName FROM hearings h 
                       LEFT JOIN courtrooms cr ON h.CourtroomID = cr.CourtroomID 
                       LEFT JOIN users u ON h.JudgeID = u.UserID 
                       WHERE h.CaseID = ? ORDER BY h.HearingDate ASC, h.HearingTime ASC');
$stmt->execute([$case_id]);
$hearings = $stmt->fetchAll();

// Fetch Documents
$stmt = $pdo->prepare('SELECT d.*, u.Name as UploaderName FROM documents d 
                       LEFT JOIN users u ON d.UploadedBy = u.UserID 
                       WHERE d.CaseID = ? ORDER BY d.UploadDate DESC');
$stmt->execute([$case_id]);
$documents = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="mb-4">
    <a href="case_tracking.php" class="btn btn-secondary btn-sm">&larr; Back to Tracking</a>
</div>

<div class="row">
    <!-- Case Information -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Case Details</h5>
            </div>
            <div class="card-body">
                <h4 class="text-primary"><?= htmlspecialchars($case['CaseNumber']) ?></h4>
                <p><strong>Title:</strong> <?= htmlspecialchars($case['CaseTitle']) ?></p>
                <p><strong>Type:</strong> <?= htmlspecialchars($case['CaseType']) ?></p>
                <p><strong>Filing Date:</strong> <?= date('M j, Y', strtotime($case['FilingDate'])) ?></p>
                <p><strong>Status:</strong> <span class="badge bg-secondary"><?= htmlspecialchars($case['Status']) ?></span></p>
                <hr>
                <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($case['Description'])) ?></p>
                <hr>
                <h6>Assignments:</h6>
                <ul>
                    <?php foreach ($assignments as $a): ?>
                        <li><?= htmlspecialchars($a['Role']) ?>: <?= htmlspecialchars($a['Name']) ?></li>
                    <?php endforeach; ?>
                    <?php if(empty($assignments)) echo '<li>None</li>'; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Timeline & History -->
    <div class="col-md-8 mb-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Progress Timeline & Hearings</h5>
            </div>
            <div class="card-body">
                <?php if (empty($hearings)): ?>
                    <p class="text-muted">No hearings scheduled yet.</p>
                <?php else: ?>
                    <div class="timeline" style="border-left: 2px solid #007bff; padding-left: 20px; margin-left: 10px;">
                        <?php foreach ($hearings as $h): ?>
                            <div class="mb-4 position-relative">
                                <div style="position: absolute; left: -26px; top: 0; background: #fff; border: 2px solid #007bff; border-radius: 50%; width: 12px; height: 12px;"></div>
                                <h6><?= date('F j, Y', strtotime($h['HearingDate'])) ?> at <?= date('g:i A', strtotime($h['HearingTime'])) ?></h6>
                                <p class="mb-1"><strong>Courtroom:</strong> <?= htmlspecialchars($h['RoomNumber'] ?? 'TBD') ?> | <strong>Judge:</strong> <?= htmlspecialchars($h['JudgeName'] ?? 'TBD') ?></p>
                                <p class="mb-0 text-muted">Status: <span class="badge bg-light text-dark border"><?= htmlspecialchars($h['Status']) ?></span></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Case Documents</h5>
                <?php if (hasRole(['Admin', 'Clerk', 'Lawyer'])): ?>
                <a href="document_upload.php" class="btn btn-sm btn-outline-primary">Upload Document</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Uploaded</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $d): ?>
                                <tr>
                                    <td><?= htmlspecialchars($d['FileName']) ?></td>
                                    <td><?= date('M j, Y', strtotime($d['UploadDate'])) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($d['DocumentStatus']) ?></span>
                                    </td>
                                    <td>
                                        <a href="document_upload.php?download=<?= $d['DocumentID'] ?>" class="btn btn-xs btn-outline-info">Download</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($documents)): ?>
                                <tr><td colspan="4" class="text-center text-muted">No documents attached.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
