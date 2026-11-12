
<?php
require_once __DIR__ . '/includes/bootstrap.php';

require_role('student');

$pageTitle = 'My Events';

// Get the logged-in student's profile ID
$studentId = student_profile_id(
    $pdo,
    (int) current_user()['user_id']
);

// Retrieve the student's registered events
if (!$studentId) {

    $events = [];

} else {

    $stmt = $pdo->prepare("
        SELECT
            r.registration_id,
            r.registration_date,
            r.status AS registration_status,
            e.*,
            e.status AS event_status,
            c.club_name,
            a.attendance_status
        FROM registrations r
        JOIN events e ON e.event_id = r.event_id
        JOIN clubs c ON c.club_id = e.club_id
        LEFT JOIN attendance a
            ON a.registration_id = r.registration_id
        WHERE r.student_id = ?
        ORDER BY e.event_date DESC, e.event_time DESC
    ");

    $stmt->execute([$studentId]);

    $events = $stmt->fetchAll();
}

// Handle event registration cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $registrationId = filter_input(
        INPUT_POST,
        'registration_id',
        FILTER_VALIDATE_INT
    );

    if (!$registrationId || !$studentId) {

        flash('error', 'Invalid registration.');

        redirect('my_events.php');
    }

    // Check whether the registration belongs to this student
    $stmt = $pdo->prepare("
        SELECT
            r.registration_id,
            r.status,
            e.event_date,
            e.status AS event_status
        FROM registrations r
        JOIN events e ON e.event_id = r.event_id
        WHERE r.registration_id = ?
          AND r.student_id = ?
    ");

    $stmt->execute([
        $registrationId,
        $studentId
    ]);

    $registration = $stmt->fetch();

    if (
        !$registration
        || $registration['status'] !== 'registered'
    ) {

        flash('error', 'This registration is not active.');

    } elseif (
        $registration['event_date'] < date('Y-m-d')
        || $registration['event_status'] !== 'upcoming'
    ) {

        flash('error', 'Past or closed events cannot be cancelled.');

    } else {

        // Update registration status to cancelled
        $update = $pdo->prepare("
            UPDATE registrations
            SET status = 'cancelled'
            WHERE registration_id = ?
        ");

        $update->execute([$registrationId]);

        flash(
            'success',
            'Your event registration has been cancelled.'
        );
    }

    redirect('my_events.php');
}

include __DIR__ . '/includes/header.php';
?>

<section class="page-heading">

    <div>
        <span class="eyebrow">Your activity</span>

        <h1>My Events</h1>

        <p>
            Review your registrations and cancel an upcoming
            event if your plans change.
        </p>
    </div>

    <a class="btn btn-primary" href="events.php">
        Find Events
    </a>

</section>

<?php if ($events): ?>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Event</th>
                    <th>Date &amp; Time</th>
                    <th>Club</th>
                    <th>Registration</th>
                    <th>Attendance</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($events as $event): ?>

                    <tr>

                        <td>

                            <a
                                class="table-link"
                                href="event_details.php?id=<?= (int) $event['event_id'] ?>"
                            >
                                <?= e($event['event_name']) ?>
                            </a>

                        </td>

                        <td>

                            <?= e(format_date($event['event_date'])) ?>

                            <br>

                            <span class="muted">
                                <?= e(format_time($event['event_time'])) ?>
                            </span>

                        </td>

                        <td>
                            <?= e($event['club_name']) ?>
                        </td>

                        <td>

                            <span class="status-pill status-<?= e($event['registration_status']) ?>">
                                <?= e(ucfirst($event['registration_status'])) ?>
                            </span>

                        </td>

                        <td>
                            <?= e(
                                $event['attendance_status']
                                    ? ucfirst($event['attendance_status'])
                                    : 'Not recorded'
                            ) ?>
                        </td>

                        <td>

                            <?php
                            if (
                                $event['registration_status'] === 'registered'
                                && $event['event_status'] === 'upcoming'
                                && $event['event_date'] >= date('Y-m-d')
                            ):
                            ?>

                                <form
                                    method="post"
                                    action="my_events.php"
                                    data-confirm="Cancel this event registration?"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="registration_id"
                                        value="<?= (int) $event['registration_id'] ?>"
                                    >

                                    <button
                                        class="btn btn-small btn-danger-light"
                                        type="submit"
                                    >
                                        Cancel
                                    </button>

                                </form>

                            <?php else: ?>

                                <span class="muted">—</span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

<?php else: ?>

    <div class="empty-state">

        <h3>No registrations yet</h3>

        <p>
            Explore upcoming activities and register
            for your first event.
        </p>

        <a class="btn btn-primary" href="events.php">
            Browse Events
        </a>

    </div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
