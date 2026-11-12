
<?php
require_once __DIR__ . '/includes/bootstrap.php';

require_login();

$user = current_user();
$pageTitle = 'Dashboard';
$stats = [];

// Dashboard information for administrators
if ($user['role'] === 'admin') {

    $stats['Clubs'] = (int) $pdo->query(
        'SELECT COUNT(*) FROM clubs'
    )->fetchColumn();

    $stats['Events'] = (int) $pdo->query(
        'SELECT COUNT(*) FROM events'
    )->fetchColumn();

    $stats['Students'] = (int) $pdo->query(
        'SELECT COUNT(*) FROM students'
    )->fetchColumn();

    $stats['Active Registrations'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM registrations
         WHERE status = 'registered'"
    )->fetchColumn();

    $list = $pdo->query("
        SELECT
            e.event_id,
            e.event_name,
            e.event_date,
            e.event_time,
            e.status,
            c.club_name,
            (
                SELECT COUNT(*)
                FROM registrations r
                WHERE r.event_id = e.event_id
                  AND r.status = 'registered'
            ) AS registration_count
        FROM events e
        JOIN clubs c ON c.club_id = e.club_id
        ORDER BY e.event_date DESC
        LIMIT 6
    ")->fetchAll();

// Dashboard information for club members
} elseif ($user['role'] === 'club_member') {

    $clubStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM clubs
        WHERE club_leader_user_id = ?
    ");
    $clubStmt->execute([$user['user_id']]);

    $stats['My Clubs'] = (int) $clubStmt->fetchColumn();

    $eventStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM events e
        JOIN clubs c ON c.club_id = e.club_id
        WHERE c.club_leader_user_id = ?
    ");
    $eventStmt->execute([$user['user_id']]);

    $stats['My Events'] = (int) $eventStmt->fetchColumn();

    $regStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM registrations r
        JOIN events e ON e.event_id = r.event_id
        JOIN clubs c ON c.club_id = e.club_id
        WHERE c.club_leader_user_id = ?
          AND r.status = 'registered'
    ");
    $regStmt->execute([$user['user_id']]);

    $stats['Active Registrations'] = (int) $regStmt->fetchColumn();

    $attStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM attendance a
        JOIN registrations r
            ON r.registration_id = a.registration_id
        JOIN events e ON e.event_id = r.event_id
        JOIN clubs c ON c.club_id = e.club_id
        WHERE c.club_leader_user_id = ?
    ");
    $attStmt->execute([$user['user_id']]);

    $stats['Attendance Records'] = (int) $attStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT
            e.event_id,
            e.event_name,
            e.event_date,
            e.event_time,
            e.status,
            c.club_name,
            (
                SELECT COUNT(*)
                FROM registrations r
                WHERE r.event_id = e.event_id
                  AND r.status = 'registered'
            ) AS registration_count
        FROM events e
        JOIN clubs c ON c.club_id = e.club_id
        WHERE c.club_leader_user_id = ?
        ORDER BY e.event_date DESC
        LIMIT 6
    ");

    $stmt->execute([$user['user_id']]);
    $list = $stmt->fetchAll();

// Dashboard information for students
} else {

    $studentId = student_profile_id(
        $pdo,
        (int) $user['user_id']
    );

    $active = $pdo->prepare("
        SELECT COUNT(*)
        FROM registrations
        WHERE student_id = ?
          AND status = 'registered'
    ");
    $active->execute([$studentId ?? 0]);

    $stats['My Active Registrations'] =
        (int) $active->fetchColumn();

    $total = $pdo->prepare("
        SELECT COUNT(*)
        FROM registrations
        WHERE student_id = ?
    ");
    $total->execute([$studentId ?? 0]);

    $stats['Total Sign-ups'] = (int) $total->fetchColumn();

    $stats['Upcoming Events'] = (int) $pdo->query("
        SELECT COUNT(*)
        FROM events
        WHERE status = 'upcoming'
          AND event_date >= CURDATE()
    ")->fetchColumn();

    $stats['Available Clubs'] = (int) $pdo->query(
        'SELECT COUNT(*) FROM clubs'
    )->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT
            e.event_id,
            e.event_name,
            e.event_date,
            e.event_time,
            e.status,
            c.club_name,
            r.status AS registration_status,
            a.attendance_status
        FROM registrations r
        JOIN events e ON e.event_id = r.event_id
        JOIN clubs c ON c.club_id = e.club_id
        LEFT JOIN attendance a
            ON a.registration_id = r.registration_id
        WHERE r.student_id = ?
        ORDER BY e.event_date DESC
        LIMIT 6
    ");

    $stmt->execute([$studentId ?? 0]);
    $list = $stmt->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<section class="page-heading">
    <div>
        <span class="eyebrow">
            <?= e(ucwords(str_replace('_', ' ', $user['role']))) ?>
            workspace
        </span>

        <h1>Hello, <?= e($user['full_name']) ?></h1>

        <p>
            Here is a summary of activity in the
            Club & Society Event Registration System.
        </p>
    </div>

    <?php if ($user['role'] === 'student'): ?>

        <a class="btn btn-primary" href="events.php">
            Explore Events
        </a>

    <?php elseif ($user['role'] === 'admin'): ?>

        <a class="btn btn-primary" href="manage_users.php">
            Manage Users
        </a>

    <?php else: ?>

        <a class="btn btn-primary" href="manage_events.php">
            Manage Events
        </a>

    <?php endif; ?>
</section>

<div class="stats-grid">

    <?php foreach ($stats as $label => $value): ?>

        <article class="stat-card">
            <span><?= e($label) ?></span>
            <strong><?= (int) $value ?></strong>
            <small>Current database total</small>
        </article>

    <?php endforeach; ?>

</div>

<section class="section-block dashboard-list">

    <div class="section-heading">

        <div>
            <span class="eyebrow">Database overview</span>

            <h2>
                <?= $user['role'] === 'student'
                    ? 'Your registrations'
                    : 'Recent events' ?>
            </h2>
        </div>

        <?php if ($user['role'] === 'student'): ?>

            <a class="text-link" href="my_events.php">
                View My Events →
            </a>

        <?php else: ?>

            <a class="text-link" href="manage_events.php">
                Manage events →
            </a>

        <?php endif; ?>

    </div>

    <?php if ($list): ?>

        <div class="table-wrap">
            <table>

                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Club</th>
                        <th>Date</th>
                        <th>Status</th>

                        <?php if ($user['role'] !== 'student'): ?>
                            <th>Registrations</th>
                        <?php endif; ?>

                        <th>Details</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($list as $row): ?>

                        <tr>
                            <td><?= e($row['event_name']) ?></td>

                            <td><?= e($row['club_name']) ?></td>

                            <td>
                                <?= e(format_date($row['event_date'])) ?>
                            </td>

                            <td>
                                <?= e(ucfirst(
                                    $row['registration_status']
                                    ?? $row['status']
                                )) ?>
                            </td>

                            <?php if ($user['role'] !== 'student'): ?>

                                <td>
                                    <?= (int) (
                                        $row['registration_count'] ?? 0
                                    ) ?>
                                </td>

                            <?php endif; ?>

                            <td>
                                <a
                                    class="table-link"
                                    href="event_details.php?id=<?= (int) $row['event_id'] ?>"
                                >
                                    Open →
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>
            </table>
        </div>

    <?php else: ?>

        <div class="empty-state compact">
            <h3>Nothing to show yet</h3>

            <p>
                As new events and registrations are added,
                this dashboard will update from the database.
            </p>
        </div>

    <?php endif; ?>

</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
