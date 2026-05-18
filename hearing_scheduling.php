<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
requireRole(['Admin', 'Clerk', 'Judge']);

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';
$user_id = $_SESSION['user_id'];
$is_judge = hasRole('Judge');


if ($is_judge && $action !== 'list') {
    $error = "Judges do not have permission to schedule or edit hearings.";
    $action = 'list';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_hearing']) && !$is_judge) {
    $hearing_id = $_POST['hearing_id'] ?? '';
    $case_id = $_POST['case_id'];
    $date = $_POST['hearing_date'];
    $time = $_POST['hearing_time'];
    $courtroom_id = $_POST['courtroom_id'];
    $judge_id = $_POST['judge_id'];
    $status = $_POST['status'];

    $today = date('Y-m-d');
    $day_of_week = date('N', strtotime($date)); // 6 = Saturday, 7 = Sunday

    if ($date <= $today || $day_of_week == 6 || $day_of_week == 7 || $time < "08:00" || $time > "17:00") {
        $error = "Invalid Date or Time inputs! Please follow the scheduling rules.";
    } else {
        // Conflict detection: Same courtroom or Same judge at the same date/time
        $conflict_query = "SELECT h.HearingID, c.CaseNumber FROM hearings h 
                           JOIN cases c ON h.CaseID = c.CaseID
                           WHERE h.HearingDate = ? AND h.HearingTime = ? \n   AND (h.CourtroomID = ? OR h.JudgeID = ?) AND h.Status != 'Cancelled'";
        $params = [$date, $time, $courtroom_id, $judge_id];
        
        if ($hearing_id) {
            $conflict_query .= " AND h.HearingID != ?";
            $params[] = $hearing_id;
        }

        $stmt = $pdo->prepare($conflict_query);
        $stmt->execute($params);
        $conflict = $stmt->fetch();

        if ($conflict) {
            $error = "Conflict detected! Courtroom or Judge is already booked for case " . $conflict['CaseNumber'] . " at this time.";
        } else {
            if ($hearing_id) {
                $stmt = $pdo->prepare('UPDATE hearings SET CaseID=?, HearingDate=?, HearingTime=?, CourtroomID=?, JudgeID=?, Status=? WHERE HearingID=?');
                if ($stmt->execute([$case_id, $date, $time, $courtroom_id, $judge_id, $status, $hearing_id])) {
                    $success = "Hearing rescheduled successfully.";
                    $action = 'list';
                    
                    if ($status === 'Rescheduled') {
                        $msg = "A hearing has been rescheduled to $date $time.";
                        $pdo->prepare('INSERT INTO notifications (UserID, Message) VALUES (?,?)')->execute([$judge_id, $msg]);
                    }
                } else {
                    $error = "Failed to reschedule hearing.";
                }
            } else {
                $stmt = $pdo->prepare('INSERT INTO hearings (CaseID, HearingDate, HearingTime, CourtroomID, JudgeID, Status) VALUES (?, ?, ?, ?, ?, ?)');
                if ($stmt->execute([$case_id, $date, $time, $courtroom_id, $judge_id, $status])) {
                    $success = "Hearing scheduled successfully.";
                    $action = 'list';
                    
                    $msg = "A new hearing has been scheduled for $date at $time.";
                    $pdo->prepare('INSERT INTO notifications (UserID, Message) VALUES (?,?)')->execute([$judge_id, $msg]);
                } else {
                    $error = "Failed to schedule hearing.";
                }
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Hearing Scheduling</h2>
    <?php if (!$is_judge): ?>
        <?php if ($action === 'list'): ?>
            <a href="?action=add" class="btn btn-primary">Schedule Hearing</a>
        <?php else: ?>
            <a href="?action=list" class="btn btn-secondary">Back to List</a>
        <?php endif; ?>
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
                            <th>Date & Time</th>
                            <th>Case No.</th>
                            <th>Title</th>
                            <th>Courtroom</th>
                            <th>Judge</th>
                            <th>Status</th>
                            <?php if (!$is_judge): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = 'SELECT h.*, c.CaseNumber, c.CaseTitle, cr.RoomNumber, u.Name as JudgeName 
                                  FROM hearings h 
                                  JOIN cases c ON h.CaseID = c.CaseID 
                                  LEFT JOIN courtrooms cr ON h.CourtroomID = cr.CourtroomID 
                                  LEFT JOIN users u ON h.JudgeID = u.UserID';
                        
                        if ($is_judge) {
                            $query .= ' WHERE h.JudgeID = ?';
                            $stmt = $pdo->prepare($query . ' ORDER BY h.HearingDate DESC, h.HearingTime DESC');
                            $stmt->execute([$user_id]);
                        } else {
                            $stmt = $pdo->query($query . ' ORDER BY h.HearingDate DESC, h.HearingTime DESC');
                        }
                        
                        $hearings = $stmt->fetchAll();
                        foreach ($hearings as $h): ?>
                            <tr>
                                <td>
                                    <strong><?= date('M j, Y', strtotime($h['HearingDate'])) ?></strong><br>
                                    <small class="text-muted"><?= date('g:i A', strtotime($h['HearingTime'])) ?></small>
                                </td>
                                <td><?= htmlspecialchars($h['CaseNumber']) ?></td>
                                <td><?= htmlspecialchars($h['CaseTitle']) ?></td>
                                <td><?= htmlspecialchars($h['RoomNumber'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($h['JudgeName'] ?? 'N/A') ?></td>
                                <td>
                                    <?php
                                    $bClass = 'bg-primary';
                                    if ($h['Status'] === 'Completed') $bClass = 'bg-success';
                                    if ($h['Status'] === 'Cancelled') $bClass = 'bg-danger';
                                    if ($h['Status'] === 'Rescheduled') $bClass = 'bg-warning text-dark';
                                    ?>
                                    <span class="badge <?= $bClass ?>"><?= htmlspecialchars($h['Status']) ?></span>
                                </td>
                                <?php if (!$is_judge): ?>
                                <td>
                                    <a href="?action=edit&id=<?= $h['HearingID'] ?>" class="btn btn-sm btn-outline-primary">Reschedule / Edit</a>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($hearings)): ?>
                            <tr><td colspan="<?= $is_judge ? '6' : '7' ?>" class="text-center py-4 text-muted">No hearings found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif (($action === 'add' || $action === 'edit') && !$is_judge): 
    $hearing = null;
    if ($action === 'edit' && isset($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM hearings WHERE HearingID = ?');
        $stmt->execute([$_GET['id']]);
        $hearing = $stmt->fetch();
    }
?>
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><?= $hearing ? 'Reschedule/Edit Hearing' : 'Schedule New Hearing' ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="hearingForm">
                <?php if ($hearing): ?>
                    <input type="hidden" name="hearing_id" value="<?= $hearing['HearingID'] ?>">
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Select Case <span class="text-danger">*</span></label>
                        <select name="case_id" class="form-select" required>
                            <option value="">-- Select Case --</option>
                            <?php
                            $cases = $pdo->query('SELECT CaseID, CaseNumber, CaseTitle FROM cases WHERE Status != "Closed"')->fetchAll();
                            foreach ($cases as $c) {
                                $selected = ($hearing && $hearing['CaseID'] == $c['CaseID']) ? 'selected' : '';
                                echo "<option value=\"{$c['CaseID']}\" $selected>{$c['CaseNumber']} - {$c['CaseTitle']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Assign Judge <span class="text-danger">*</span></label>
                        <select name="judge_id" class="form-select" required>
                            <option value="">-- Select Judge --</option>
                            <?php
                            $judges = $pdo->query('SELECT UserID, Name FROM users WHERE Role = "Judge" AND Status = "Active"')->fetchAll();
                            foreach ($judges as $j) {
                                $selected = ($hearing && $hearing['JudgeID'] == $j['UserID']) ? 'selected' : '';
                                echo "<option value=\"{$j['UserID']}\" $selected>{$j['Name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Hearing Date <span class="text-danger">*</span></label>
                        <?php $tomorrow = date('Y-m-d', strtotime('+1 day')); ?>
                        <input type="date" name="hearing_date" id="hearing_date" class="form-control" min="<?= $tomorrow; ?>" required value="<?= $hearing ? $hearing['HearingDate'] : '' ?>" onchange="validateDateTime()">
                        <div id="dateError" class="text-danger small fw-bold mt-1" style="display: none;"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Hearing Time <span class="text-danger">*</span></label>
                        <input type="time" name="hearing_time" id="hearing_time" class="form-control" required value="<?= $hearing ? $hearing['HearingTime'] : '' ?>" onchange="validateDateTime()">
                        <div id="timeError" class="text-danger small fw-bold mt-1" style="display: none;">Court time must be between 8:00 AM and 5:00 PM!</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Courtroom <span class="text-danger">*</span></label>
                        <select name="courtroom_id" class="form-select" required>
                            <option value="">-- Select Courtroom --</option>
                            <?php
                            $rooms = $pdo->query('SELECT CourtroomID, RoomNumber FROM courtrooms')->fetchAll();
                            foreach ($rooms as $r) {
                                $selected = ($hearing && $hearing['CourtroomID'] == $r['CourtroomID']) ? 'selected' : '';
                                echo "<option value=\"{$r['CourtroomID']}\" $selected>{$r['RoomNumber']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php 
                        $statuses = ['Scheduled', 'Completed', 'Cancelled', 'Rescheduled'];
                        foreach ($statuses as $s) {
                            $selected = ($hearing && $hearing['Status'] === $s) ? 'selected' : '';
                            echo "<option value=\"$s\" $selected>$s</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" name="save_hearing" id="submitBtn" class="btn btn-primary px-4"><?= $hearing ? 'Save Changes' : 'Schedule Hearing' ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
function validateDateTime() {
    var dateInput = document.getElementById("hearing_date");
    var timeInput = document.getElementById("hearing_time");
    var submitBtn = document.getElementById("submitBtn");
    
    if(!dateInput || !timeInput || !submitBtn) return;

    var dateError = document.getElementById("dateError");
    var timeError = document.getElementById("timeError");
    
    var isValid = true;
    
    //Date eka blanna (Past, Today, and Weekends)
    if(dateInput.value) {
        var selectedDate = new Date(dateInput.value);
        var dayOfWeek = selectedDate.getDay(); // 0 = Sunday, 6 = Saturday
        
        var today = new Date();
        today.setHours(0,0,0,0);
        selectedDate.setHours(0,0,0,0);
        
        if(selectedDate <= today) {
            dateError.innerText = "You must select a future date! (Today & Past dates are not allowed)";
            dateError.style.display = "block";
            isValid = false;
        } else if(dayOfWeek === 0 || dayOfWeek === 6) {
            dateError.innerText = "Court is closed on Weekends! Please select a weekday (Monday - Friday).";
            dateError.style.display = "block";
            isValid = false;
        } else {
            dateError.style.display = "none";
        }
    }
    
    // Time eka blanna (8:00 AM - 5:00 PM)
    if(timeInput.value) {
        var time = timeInput.value;
        if(time < "08:00" || time > "17:00") {
            timeError.style.display = "block";
            isValid = false;
        } else {
            timeError.style.display = "none";
        }
    }
    
    if(isValid) {
        submitBtn.disabled = false;
        submitBtn.style.opacity = "1";
        submitBtn.style.cursor = "pointer";
    } else {
        submitBtn.disabled = true;
        submitBtn.style.opacity = "0.5";
        submitBtn.style.cursor = "not-allowed";
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>