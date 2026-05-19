<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Court Case Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f5f5; }
        .navbar-brand { font-weight: bold; }
        .sidebar { background-color: #343a40; min-height: calc(100vh - 56px); }
        .sidebar .nav-link { color: #fff; }
        .sidebar .nav-link:hover { background-color: #495057; }
        .sidebar .nav-link.active { background-color: #007bff; }
        .main-content { padding: 20px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">⚖️ Court Case Management System</a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="d-flex">
                <span class="navbar-text me-3">
                    Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="col-md-2 sidebar p-0">
                <div class="nav flex-column nav-pills mt-3">
                    <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
                    
                    <?php if (hasRole(['Admin', 'Clerk'])): ?>
                    <a href="case_management.php" class="nav-link">📋 Case Management</a>
                    <?php endif; ?>
                    
                    <?php if (hasRole(['Admin', 'Clerk', 'Judge'])): ?>
                    <a href="hearing_scheduling.php" class="nav-link">🗓️ Hearing Scheduling</a>
                    <?php endif; ?>
                    
                    <?php if (hasRole(['Admin', 'Clerk', 'Lawyer'])): ?>
                    <a href="document_upload.php" class="nav-link">📄 Document Management</a>
                    <?php endif; ?>
                    
                    <a href="case_tracking.php" class="nav-link">🔍 Case Tracking</a>
                    
                    <?php if (hasRole('Admin')): ?>
                    <a href="user_management1.php" class="nav-link">👥 User Management</a>
                    <?php endif; ?>
                    
                    <a href="notifications_reports.php" class="nav-link">🔔 Notifications & Reports</a>
                </div>
            </div>
            <div class="col-md-10 main-content">
            <?php else: ?>
            <div class="col-md-12 main-content">
            <?php endif; ?>