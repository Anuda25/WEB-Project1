<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if (isset($_POST['mark_read'])) {
    $notif_id = $_POST['notif_id'];
    $pdo->prepare('UPDATE notifications SET Status = "Read" WHERE NotificationID = ? AND UserID = ?')->execute([$notif_id, $user_id]);
    header("Location: notifications_reports.php");
    exit;
}

if (isset($_GET['export_cases'])) {
    $type = $_GET['case_type'] ?? '';
    $start = $_GET['start_date'] ?? '';
    $end = $_GET['end_date'] ?? '';

    $query = "SELECT CaseNumber, CaseTitle, CaseType, FilingDate, Status FROM cases WHERE 1=1";
    $params = [];

    if ($type !== '') {
        $query .= " AND CaseType = ?";
        $params[] = $type;
    }
    if ($start !== '') {
        $query .= " AND FilingDate >= ?";
        $params[] = $start;
    }
    if ($end !== '') {
        $query .= " AND FilingDate <= ?";
        $params[] = $end;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Case_Report_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, array('COURT CASE MANAGEMENT SYSTEM - CASE REPORT'));
    fputcsv($output, array('Generated on:', date('Y-m-d H:i:s')));
    fputcsv($output, array('Filters:', ($type ?: 'All Types'), 'From: ' . ($start ?: 'Start'), 'To: ' . ($end ?: 'End')));
    fputcsv($output, array());
    
    fputcsv($output, array('Case Number', 'Case Title', 'Category', 'Date Filed', 'Current Status'));
    
    foreach ($cases as $row) {
        $row['FilingDate'] = date('d-M-Y', strtotime($row['FilingDate']));
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

if (isset($_GET['export_hearings'])) {
    $start = $_GET['h_start_date'] ?? '';
    $end = $_GET['h_end_date'] ?? '';

    $query = "SELECT h.HearingDate, h.HearingTime, c.CaseNumber, cr.RoomNumber, u.Name as JudgeName, h.Status 
              FROM hearings h 
              JOIN cases c ON h.CaseID = c.CaseID 
              LEFT JOIN courtrooms cr ON h.CourtroomID = cr.CourtroomID 
              LEFT JOIN users u ON h.JudgeID = u.UserID 
              WHERE 1=1";
    $params = [];

    if ($start !== '') {
        $query .= " AND h.HearingDate >= ?";
        $params[] = $start;
    }
    if ($end !== '') {
        $query .= " AND h.HearingDate <= ?";
        $params[] = $end;
    }

    $query .= " ORDER BY h.HearingDate ASC, h.HearingTime ASC"; 
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $hearings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Hearing_Schedule_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, array('OFFICIAL HEARING SCHEDULE'));
    fputcsv($output, array('Export Date:', date('d-M-Y H:i')));
    fputcsv($output, array());
    
    fputcsv($output, array('Scheduled Date', 'Scheduled Time', 'Case Reference', 'Courtroom/Location', 'Presiding Judge', 'Hearing Status'));
    
    foreach ($hearings as $row) {
        $formatted_row = [
            date('D, d-M-Y', strtotime($row['HearingDate'])), 
            date('h:i A', strtotime($row['HearingTime'])),   
            $row['CaseNumber'],
            $row['RoomNumber'] ?? 'N/A',
            $row['JudgeName'] ?? 'Unassigned',
            strtoupper($row['Status'])
        ];
        fputcsv($output, $formatted_row);
    }
    fclose($output);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE UserID = ? ORDER BY Date DESC');
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$reminder_query = '';
if ($role === 'Judge') {
    $reminder_query = "SELECT h.HearingID, h.HearingDate, h.HearingTime, c.CaseNumber FROM hearings h JOIN cases c ON h.CaseID = c.CaseID WHERE h.JudgeID = ? AND h.HearingDate IN (?, ?) AND h.Status = 'Scheduled'";
} elseif ($role === 'Lawyer') {
    $reminder_query = "SELECT h.HearingID, h.HearingDate, h.HearingTime, c.CaseNumber FROM hearings h JOIN case_assignments ca ON h.CaseID = ca.CaseID JOIN cases c ON h.CaseID = c.CaseID WHERE ca.UserID = ? AND h.HearingDate IN (?, ?) AND h.Status = 'Scheduled'";
}

if ($reminder_query !== '') {
    $stmt = $pdo->prepare($reminder_query);
    $stmt->execute([$user_id, $today, $tomorrow]);
    $upcoming_hearings = $stmt->fetchAll();

    foreach ($upcoming_hearings as $uh) {
        $day = ($uh['HearingDate'] === $today) ? 'Today' : 'Tomorrow';
        $msg = "Reminder: Hearing for Case {$uh['CaseNumber']} is scheduled for $day at {$uh['HearingTime']}.";
        
        $check = $pdo->prepare('SELECT 1 FROM notifications WHERE UserID = ? AND Message = ? AND Date >= CURRENT_DATE');
        $check->execute([$user_id, $msg]);
        if (!$check->fetch()) {
            $pdo->prepare('INSERT INTO notifications (UserID, Message) VALUES (?,?)')->execute([$user_id, $msg]);
        }
    }
    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE UserID = ? ORDER BY Date DESC');
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
}


require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Your Notifications</h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($notifications as $n): ?>
                        <li class="list-group-item p-3 <?= $n['Status'] === 'Unread' ? 'bg-light' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="mb-1 <?= $n['Status'] === 'Unread' ? 'fw-bold' : '' ?>"><?= htmlspecialchars($n['Message']) ?></p>
                                    <small class="text-muted"><?= date('M j, Y g:i A', strtotime($n['Date'])) ?></small>
                                </div>
                                <?php if ($n['Status'] === 'Unread'): ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="notif_id" value="<?= $n['NotificationID'] ?>">
                                    <button type="submit" name="mark_read" class="btn btn-sm btn-outline-secondary">Mark Read</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($notifications)): ?>
                        <li class="list-group-item text-center py-4 text-muted">No notifications.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-7 mb-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Generate Case Report</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" target="_blank">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Case Type</label>
                            <select name="case_type" class="form-select">
                                <option value="">All Types</option>
                                <option value="Civil">Civil</option>
                                <option value="Criminal">Criminal</option>
                                <option value="Family">Family</option>
                                <option value="Corporate">Corporate</option>
                                <option value="Probate">Probate</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">From Date</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">To Date</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                    </div>
                    <button type="submit" name="export_cases" class="btn btn-success">Download Case Report (CSV)</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Generate Hearing Schedule</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" target="_blank">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">From Date</label>
                            <input type="date" name="h_start_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">To Date</label>
                            <input type="date" name="h_end_date" class="form-control">
                        </div>
                    </div>
                    <button type="submit" name="export_hearings" class="btn btn-success">Download Schedule (CSV)</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
