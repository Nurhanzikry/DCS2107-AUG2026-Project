
<?php
// Development-only connection check.
// Remove or restrict access to this file before hosting publicly.

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Database Check';

// List of required database tables
$expectedTables = [
    'users',
    'students',
    'clubs',
    'events',
    'registrations',
    'attendance'
];

$foundTables = [];

// Retrieve table names from the database
$stmt = $pdo->query('SHOW TABLES');

foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {

    $foundTables[] = $row[0];
}

include __DIR__ . '/includes/header.php';
?>

<section class="page-heading">

    <div>
        <span class="eyebrow">Local setup</span>

        <h1>Database connection check</h1>

        <p>
            This page checks whether PHP can connect
            to the MySQL database through PDO.
        </p>
    </div>

</section>

<div class="panel">

    <div class="status-box status-success">
        ✓ PHP connected to MySQL successfully.
    </div>

    <p>
        <strong>Database:</strong>
        club_society_event_registration_system
    </p>

    <h2>Required tables</h2>

    <ul class="check-list">

        <?php foreach ($expectedTables as $table): ?>

            <?php
            $exists = in_array(
                $table,
                $foundTables,
                true
            );
            ?>

            <li>

                <?= $exists ? '✓' : '✕' ?>

                <?= e($table) ?>

                —

                <?= $exists ? 'found' : 'missing' ?>

            </li>

        <?php endforeach; ?>

    </ul>

    <p class="muted">
        If a table is missing, import
        club_society_event_registration_system.sql
        through phpMyAdmin. Delete or restrict access to this
        check page after testing.
    </p>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
