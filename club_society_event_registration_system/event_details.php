
<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Get the event ID from the URL
$eventId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$eventId) {
    flash('error', 'Please choose a valid event.');
    redirect('events.php');
}

// Retrieve event information and registration count
$stmt = $pdo->prepare("
    SELECT
        e.*,
        c.club_name,
        u.full_name AS organiser,
        (
            SELECT COUNT(*)
            FROM registrations r
            WHERE r.event_id = e.event_id
              AND r.status = 'registered'
        ) AS registered_count
    FROM events e
    JOIN clubs c ON c.club_id = e.club_id
    JOIN users u ON u.user_id = e.created_by_user_id
    WHERE e.event_id = ?
");

$stmt->execute([$eventId]);
$event = $stmt->fetch();

// Check whether the event exists
if (!$event) {

    http_response_code(404);

    $pageTitle = 'Event Not Found';

    include __DIR__ . '/includes/header.php';

    echo '
        <div class="empty-state">
            <h1>Event not found</h1>
            <p>This event may have been removed.</p>
            <a class="btn btn-primary" href="events.php">
                Back to events
            </a>
        </div>
    ';

    include __DIR__ . '/includes/footer.php';

    exit;
}

$pageTitle = $event['event_name'];
$myRegistration = null;

// Check whether the current student has registered
if (current_user() && current_user()['role'] === 'student') {

    $studentId = student_profile_id(
        $pdo,
        (int) current_user()['user_id']
    );

    if ($studentId) {

        $r = $pdo->prepare("
            SELECT registration_id, status
            FROM registrations
            WHERE student_id = ?
              AND event_id = ?
        ");

        $r->execute([$studentId, $eventId]);

        $myRegistration = $r->fetch() ?: null;
    }
}

// Calculate available event spaces
$remaining = max(
    0,
    (int) $event['capacity'] - (int) $event['registered_count']
);

// Check whether registration is available
$canRegister =
    $event['status'] === 'upcoming'
    && $event['event_date'] >= date('Y-m-d')
    && $remaining > 0;

include __DIR__ . '/includes/header.php';
?>

<a class="back-link" href="events.php">
    ← Back to events
</a>

<section class="detail-hero">

    <div class="detail-date">
        <span>
            <?= e(date('M', strtotime($event['event_date']))) ?>
        </span>

        <strong>
            <?= e(date('d', strtotime($event['event_date']))) ?>
        </strong>

        <small>
            <?= e(date('Y', strtotime($event['event_date']))) ?>
        </small>
    </div>

    <div class="detail-heading">

        <span class="eyebrow">
            <?= e($event['club_name']) ?>
            ·
            <?= e(ucfirst($event['status'])) ?>
        </span>

        <h1><?= e($event['event_name']) ?></h1>

        <p><?= e($event['description']) ?></p>

    </div>

</section>

<div class="detail-layout">

    <section class="panel detail-info">

        <h2>Event information</h2>

        <div class="info-row">
            <span>Date</span>
            <strong>
                <?= e(format_date($event['event_date'])) ?>
            </strong>
        </div>

        <div class="info-row">
            <span>Time</span>
            <strong>
                <?= e(format_time($event['event_time'])) ?>
            </strong>
        </div>

        <div class="info-row">
            <span>Location</span>
            <strong><?= e($event['location']) ?></strong>
        </div>

        <div class="info-row">
            <span>Organised by</span>
            <strong><?= e($event['club_name']) ?></strong>
        </div>

        <div class="info-row">
            <span>Capacity</span>
            <strong>
                <?= (int) $event['registered_count'] ?>
                /
                <?= (int) $event['capacity'] ?>
                registered
            </strong>
        </div>

        <div class="capacity-track">
            <span
                style="width: <?= min(
                    100,
                    (int) round(
                        (
                            (int) $event['registered_count']
                            / max(1, (int) $event['capacity'])
                        ) * 100
                    )
                ) ?>%"
            ></span>
        </div>

        <p class="muted">
            <?= $remaining ?> available space(s)
        </p>

    </section>

    <aside class="panel register-panel">

        <h2>Want to join?</h2>

        <?php if (!is_logged_in()): ?>

            <p>
                Log in with a student account to register
                for this event.
            </p>

            <a class="btn btn-primary btn-wide" href="login.php">
                Log In to Register
            </a>

        <?php elseif (current_user()['role'] !== 'student'): ?>

            <p>
                Registration is available to student accounts.
                Your current role is
                <?= e(str_replace('_', ' ', current_user()['role'])) ?>.
            </p>

        <?php elseif (
            $myRegistration
            && $myRegistration['status'] === 'registered'
        ): ?>

            <div class="status-box status-success">
                ✓ You are registered for this event.
            </div>

            <a class="btn btn-outline btn-wide" href="my_events.php">
                View My Events
            </a>

        <?php elseif ($canRegister): ?>

            <p>
                Reserve a place at this event. The system will
                check the available capacity before saving
                your registration.
            </p>

            <form method="post" action="register_event.php">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="event_id"
                    value="<?= (int) $eventId ?>"
                >

                <button
                    class="btn btn-primary btn-wide"
                    type="submit"
                >
                    Register for Event
                </button>

            </form>

        <?php else: ?>

            <div class="status-box status-muted">
                Registration is closed because the event is full,
                completed, cancelled or has passed.
            </div>

        <?php endif; ?>

    </aside>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
