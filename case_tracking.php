<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
requireLogin();

$search = $_GET['search'] ?? '';
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

require_once 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8 mx-auto text-center">
        <h2 class="mb-4">Case Tracking</h2>
        <form method="GET" action="" class="d-flex">
            <input type="text" name="search" class="form-control form-control-lg me-2" placeholder="Search by Case Number, Title, or Type..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary btn-lg px-4">Search</button>
        </form>
    </div>
</div>

<?php
if ($search !== '' || $role === 'Admin' || $role === 'Clerk' || $role === 'Judge' || $role === 'Lawyer'):
    
    $query = "SELECT c.*, u.Name as CreatorName FROM cases c LEFT JOIN users u ON c.CreatedBy = u.UserID WHERE 1=1";
    $params = [];
    
    if ($search !== '') {
        $query .= " AND (c.CaseNumber LIKE ? OR c.CaseTitle LIKE ? OR c.CaseType LIKE ?)";
        $search_param = "%$search%";
        $params = [$search_param, $search_param, $search_param];
    }

    
    if ($role === 'Judge' || $role === 'Lawyer') {
        $query .= " AND c.CaseID IN (SELECT CaseID FROM case_assignments WHERE UserID = ?)";
        $params[] = $user_id;
    }

    $query .= " ORDER BY c.CreatedAt DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $cases = $stmt->fetchAll();
?>

    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">Search Results</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Case Number</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cases as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['CaseNumber']) ?></strong></td>
                                <td><?= htmlspecialchars($c['CaseTitle']) ?></td>
                                <td><?= htmlspecialchars($c['CaseType']) ?></td>
                                <td>
                                    <?php
                                    $bClass = 'bg-primary';
                                    if ($c['Status'] === 'Open') $bClass = 'bg-success';
                                    if ($c['Status'] === 'Closed') $bClass = 'bg-secondary';
                                    if ($c['Status'] === 'Appealed') $bClass = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $bClass ?>"><?= htmlspecialchars($c['Status']) ?></span>
                                </td>
                                <td>
                                    <a href="case_details.php?id=<?= $c['CaseID'] ?>" class="btn btn-sm btn-outline-info">View Full History</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cases)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No matching cases found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
