<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get counts
$stats = [
    'cases' => 0,
    'hearings' => 0,
    'notifications' => 0
];

if ($role === 'Admin' || $role === 'Clerk') {
    $stats['cases'] = $pdo->query('SELECT COUNT(*) FROM cases')->fetchColumn();
    $stats['hearings'] = $pdo->query('SELECT COUNT(*) FROM hearings WHERE Status = "Scheduled"')->fetchColumn();
} elseif ($role === 'Judge') {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM case_assignments WHERE UserID = ?');
    $stmt->execute([$user_id]);
    $stats['cases'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM hearings WHERE JudgeID = ? AND Status = "Scheduled"');
    $stmt->execute([$user_id]);
    $stats['hearings'] = $stmt->fetchColumn();
} elseif ($role === 'Lawyer') {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM case_assignments WHERE UserID = ?');
    $stmt->execute([$user_id]);
    $stats['cases'] = $stmt->fetchColumn();
    
    // Lawyer hearings based on cases they are assigned to
    $stmt = $pdo->prepare('
        SELECT COUNT(h.HearingID) FROM hearings h 
        JOIN case_assignments ca ON h.CaseID = ca.CaseID 
        WHERE ca.UserID = ? AND h.Status = "Scheduled"
    ');
    $stmt->execute([$user_id]);
    $stats['hearings'] = $stmt->fetchColumn();
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE UserID = ? AND Status = "Unread"');
$stmt->execute([$user_id]);
$stats['notifications'] = $stmt->fetchColumn();

require_once 'includes/header.php';
?>
<div class="row mb-4">
    <div class="col">
        <h2>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?> (<?= htmlspecialchars($role) ?>)</h2>
        <p class="text-muted">Here is an overview of your current tasks and alerts.</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white text-center shadow-sm">
            <div class="card-body py-4">
                <h1 class="display-4 fw-bold"><?= $stats['cases'] ?></h1>
                <p class="mb-0 fs-5">Total Cases</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white text-center shadow-sm">
            <div class="card-body py-4">
                <h1 class="display-4 fw-bold"><?= $stats['hearings'] ?></h1>
                <p class="mb-0 fs-5">Upcoming Hearings</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-dark text-center shadow-sm">
            <div class="card-body py-4">
                <h1 class="display-4 fw-bold"><?= $stats['notifications'] ?></h1>
                <p class="mb-0 fs-5">Unread Notifications</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">Quick Actions</div>
            <div class="card-body d-flex flex-column justify-content-center">
                <div class="d-grid gap-3">
                    <?php if (hasRole(['Admin', 'Clerk'])): ?>
                        <a href="case_management.php" class="btn btn-outline-primary btn-lg">Register New Case</a>
                        <a href="hearing_scheduling.php" class="btn btn-outline-success btn-lg">Schedule a Hearing</a>
                    <?php endif; ?>
                    <a href="document_upload.php" class="btn btn-outline-info btn-lg">Upload Document</a>
                    <a href="case_tracking.php" class="btn btn-outline-secondary btn-lg">Search & Track Cases</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">Recent Notifications</div>
            <div class="card-body">
                <?php
                $stmt = $pdo->prepare('SELECT * FROM notifications WHERE UserID = ? ORDER BY Date DESC LIMIT 5');
                $stmt->execute([$user_id]);
                $notifications = $stmt->fetchAll();
                
                if ($notifications): ?>
                    <ul class="list-group list-group-flush mb-3">
                    <?php foreach ($notifications as $n): ?>
                        <li class="list-group-item px-0 <?= $n['Status'] === 'Unread' ? 'fw-bold' : '' ?>">
                            <?= htmlspecialchars($n['Message']) ?>
                            <br><small class="text-muted"><?= date('M j, Y g:i A', strtotime($n['Date'])) ?></small>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                    <a href="notifications_reports.php" class="btn btn-sm btn-link text-decoration-none p-0">View all notifications &rarr;</a>
                <?php else: ?>
                    <div class="text-center text-muted my-5">
                        <p>No recent notifications.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
