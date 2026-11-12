
<?php
require_once __DIR__ . '/includes/bootstrap.php';

require_role('club_member', 'admin');

// Get the event ID from the URL
$eventId = filter_input(
    INPUT_GET,
    'event_id',
    FILTER_VALIDATE_INT
);

// Check permission to manage this event
if (
    !$eventId
    || !can_manage_event($pdo, (int) $eventId)
) {

    http_response_code(403);

    exit(
        'You do not have permission to view participants for this event.'
    );
}

// Handle attendance updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $registrationId = filter_var(
        $_POST['registration_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $attendanceStatus = (string) (
        $_POST['attendance_status'] ?? ''
    );

    // Validate the registration and attendance status
    if (
        !$registrationId
        || !in_array(
            $attendanceStatus,
            ['present', 'absent', 'late', 'excused'],
            true
        )
    ) {

        flash('error', 'Choose a valid participant and attendance status.');

        redirect('participants.php?event_id=' . $eventId);
    }

    // Check that the registration belongs to this event
    $check = $pdo->prepare("
        SELECT r.registration_id
        FROM registrations r
        WHERE r.registration_id = ?
          AND r.event_id = ?
          AND r.status = 'registered'
    ");

    $check->execute([
        $registrationId,
        $eventId
    ]);

    if (!$check->fetchColumn()) {

        flash('error', 'This active registration could not be found.');

        redirect('participants.php?event_id=' . $eventId);
    }

    // Record check-in time for students marked present or late
    $checkIn = in_array(
        $attendanceStatus,
        ['present', 'late'],
        true
    )
        ? date('Y-m-d H:i:s')
        : null;

    // Insert attendance or update an existing attendance record
    $stmt = $pdo->prepare("
        INSERT INTO attendance (
            registration_id,
            attendance_status,
            check_in_time
        )
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            attendance_status = VALUES(attendance_status),
            check_in_time = VALUES(check_in_time)
    ");

    $stmt->execute([
        $registrationId,
        $attendanceStatus,
        $checkIn
    ]);

    flash('success', 'Attendance has been saved.');

    redirect('participants.php?event_id=' . $eventId);
}

// Retrieve event information
$stmt = $pdo->prepare("
    SELECT
        e.*,
        c.club_name
    FROM events e
    JOIN clubs c ON c.club_id = e.club_id
    WHERE e.event_id = ?
");

$stmt->execute([$eventId]);

$event = $stmt->fetch();

if (!$event) {

    http_response_code(404);

    exit('Event not found.');
}

// Retrieve registered participants and attendance information
$stmt = $pdo->prepare("
    SELECT
        r.registration_id,
        r.registration_date,
        r.status AS registration_status,
        s.student_number,
        s.programme,
        u.full_name,
        u.email,
        a.attendance_status,
        a.check_in_time
    FROM registrations r
    JOIN students s ON s.student_id = r.student_id
    JOIN users u ON u.user_id = s.user_id
    LEFT JOIN attendance a
        ON a.registration_id = r.registration_id
    WHERE r.event_id = ?
    ORDER BY r.registration_date ASC
");

$stmt->execute([$eventId]);

$participants = $stmt->fetchAll();

$pageTitle = 'Event Participants';

include __DIR__ . '/includes/header.php';
?>

<section class="page-heading">

    <div>
        <span class="eyebrow">
            <?= e($event['club_name']) ?>
        </span>

        <h1>Event Participants</h1>

        <p>
            <?= e($event['event_name']) ?>
            ·
            <?= e(format_date($event['event_date'])) ?>
        </p>
    </div>

    <a class="btn btn-outline" href="manage_events.php">
        Back to Events
    </a>

</section>

<!-- Attendance statistics -->
<div class="stats-grid mini-stats">

    <article class="stat-card">

        <span>Total registrations</span>

        <strong>
            <?= count(array_filter(
                $participants,
                fn($p) => $p['registration_status'] === 'registered'
            )) ?>
        </strong>

    </article>

    <article class="stat-card">

        <span>Marked present</span>

        <strong>
            <?= count(array_filter(
                $participants,
                fn($p) => in_array(
                    $p['attendance_status'],
                    ['present', 'late'],
                    true
                )
            )) ?>
        </strong>

    </article>

    <article class="stat-card">

        <span>Capacity</span>

        <strong>
            <?= (int) $event['capacity'] ?>
        </strong>

    </article>

</div>

<!-- Participant records -->
<section class="panel panel-table">

    <div class="panel-heading">

        <h2>Registration list</h2>

        <span><?= count($participants) ?> record(s)</span>

    </div>

    <?php if ($participants): ?>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Student number</th>
                        <th>Programme</th>
                        <th>Registration</th>
                        <th>Attendance</th>
                        <th>Update attendance</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($participants as $person): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= e($person['full_name']) ?>
                                </strong>

                                <br>

                                <span class="muted">
                                    <?= e($person['email']) ?>
                                </span>

                            </td>

                            <td>
                                <?= e($person['student_number']) ?>
                            </td>

                            <td>
                                <?= e($person['programme']) ?>
                            </td>

                            <td>

                                <span class="status-pill status-<?= e($person['registration_status']) ?>">
                                    <?= e(ucfirst($person['registration_status'])) ?>
                                </span>

                            </td>

                            <td>

                                <?= e(
                                    $person['attendance_status']
                                        ? ucfirst($person['attendance_status'])
                                        : 'Not marked'
                                ) ?>

                                <?php if ($person['check_in_time']): ?>

                                    <br>

                                    <span class="muted">
                                        <?= e(date(
                                            'd M, g:i A',
                                            strtotime($person['check_in_time'])
                                        )) ?>
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php
                                if (
                                    $person['registration_status'] === 'registered'
                                ):
                                ?>

                                    <form
                                        class="inline-attendance"
                                        method="post"
                                        action="participants.php?event_id=<?= (int) $eventId ?>"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="registration_id"
                                            value="<?= (int) $person['registration_id'] ?>"
                                        >

                                        <select
                                            name="attendance_status"
                                            aria-label="Attendance status for <?= e($person['full_name']) ?>"
                                        >

                                            <option
                                                value="present"
                                                <?= $person['attendance_status'] === 'present'
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                Present
                                            </option>

                                            <option
                                                value="late"
                                                <?= $person['attendance_status'] === 'late'
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                Late
                                            </option>

                                            <option
                                                value="absent"
                                                <?= $person['attendance_status'] === 'absent'
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                Absent
                                            </option>

                                            <option
                                                value="excused"
                                                <?= $person['attendance_status'] === 'excused'
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                Excused
                                            </option>

                                        </select>

                                        <button
                                            class="btn btn-small btn-primary"
                                            type="submit"
                                        >
                                            Save
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="muted">
                                        Cancelled registration
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php else: ?>

        <div class="empty-state compact">
            <p>No students have registered for this event yet.</p>
        </div>

    <?php endif; ?>

</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
