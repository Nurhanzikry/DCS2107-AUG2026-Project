
<?php
require_once __DIR__ . '/includes/bootstrap.php';

require_role('club_member', 'admin');

$pageTitle = 'Manage Events';

$user = current_user();

$errors = [];
$editEvent = null;
$availableClubs = [];

// Retrieve clubs that the current user can manage
if ($user['role'] === 'admin') {

    $availableClubs = $pdo->query("
        SELECT club_id, club_name
        FROM clubs
        ORDER BY club_name
    ")->fetchAll();

} else {

    $stmt = $pdo->prepare("
        SELECT club_id, club_name
        FROM clubs
        WHERE club_leader_user_id = ?
        ORDER BY club_name
    ");

    $stmt->execute([$user['user_id']]);

    $availableClubs = $stmt->fetchAll();
}

// Handle submitted forms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    // Create or update an event
    if ($action === 'save') {

        $id = filter_var(
            $_POST['event_id'] ?? '',
            FILTER_VALIDATE_INT
        ) ?: 0;

        $clubId = filter_var(
            $_POST['club_id'] ?? '',
            FILTER_VALIDATE_INT
        ) ?: 0;

        $name = trim((string) ($_POST['event_name'] ?? ''));

        $description = trim(
            (string) ($_POST['description'] ?? '')
        );

        $date = trim((string) ($_POST['event_date'] ?? ''));

        $time = trim((string) ($_POST['event_time'] ?? ''));

        $location = trim((string) ($_POST['location'] ?? ''));

        $capacity = filter_var(
            $_POST['capacity'] ?? '',
            FILTER_VALIDATE_INT
        );

        $status = $id
            ? (string) ($_POST['status'] ?? 'upcoming')
            : 'upcoming';

        // Check required fields
        if (
            $name === ''
            || $description === ''
            || $date === ''
            || $time === ''
            || $location === ''
        ) {
            $errors[] = 'Complete all required event fields.';
        }

        // Check field lengths
        if (
            mb_strlen($name) > 150
            || mb_strlen($location) > 180
        ) {
            $errors[] = 'The event name or location is too long.';
        }

        // Check event capacity
        if (!$capacity || $capacity < 1) {
            $errors[] = 'Capacity must be a positive whole number.';
        }

        // Validate event date
        $dateObj = DateTime::createFromFormat('!Y-m-d', $date);

        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            $errors[] = 'Enter a valid event date.';
        }

        // Validate event time
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time)) {
            $errors[] = 'Enter a valid event time.';
        }

        // Validate event status
        if (!in_array(
            $status,
            ['upcoming', 'completed', 'cancelled'],
            true
        )) {
            $errors[] = 'Choose a valid event status.';
        }

        // Upcoming events cannot have past dates
        if (
            $dateObj
            && $dateObj < new DateTime('today')
            && $status === 'upcoming'
        ) {
            $errors[] = 'An upcoming event cannot have a past date.';
        }

        // Check whether the user can manage the selected club
        if (!$clubId || !can_manage_club($pdo, $clubId)) {
            $errors[] = 'You do not have permission to manage that club.';
        }

        // Check whether the user can edit the selected event
        if ($id && !can_manage_event($pdo, $id)) {
            $errors[] = 'You do not have permission to edit that event.';
        }

        // Prevent reducing capacity below active registrations
        if ($id && !$errors && $capacity) {

            $count = $pdo->prepare("
                SELECT COUNT(*)
                FROM registrations
                WHERE event_id = ?
                  AND status = 'registered'
            ");

            $count->execute([$id]);

            if ($capacity < (int) $count->fetchColumn()) {
                $errors[] = 'Capacity cannot be lower than the current number of active registrations.';
            }
        }

        // Save the event if validation passes
        if (!$errors) {

            try {

                if ($id) {

                    // Update an existing event
                    $stmt = $pdo->prepare("
                        UPDATE events
                        SET
                            club_id = ?,
                            event_name = ?,
                            description = ?,
                            event_date = ?,
                            event_time = ?,
                            location = ?,
                            capacity = ?,
                            status = ?
                        WHERE event_id = ?
                    ");

                    $stmt->execute([
                        $clubId,
                        $name,
                        $description,
                        $date,
                        $time,
                        $location,
                        $capacity,
                        $status,
                        $id
                    ]);

                    flash('success', 'Event details updated.');

                } else {

                    // Insert a new event
                    $stmt = $pdo->prepare("
                        INSERT INTO events (
                            club_id,
                            created_by_user_id,
                            event_name,
                            description,
                            event_date,
                            event_time,
                            location,
                            capacity,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'upcoming')
                    ");

                    $stmt->execute([
                        $clubId,
                        $user['user_id'],
                        $name,
                        $description,
                        $date,
                        $time,
                        $location,
                        $capacity
                    ]);

                    flash('success', 'New event created.');
                }

                redirect('manage_events.php');

            } catch (PDOException $e) {

                $errors[] = 'The event could not be saved. Check the selected club and event details.';
            }
        }

        // Keep entered values if validation fails
        $editEvent = [
            'event_id' => $id,
            'club_id' => $clubId,
            'event_name' => $name,
            'description' => $description,
            'event_date' => $date,
            'event_time' => $time,
            'location' => $location,
            'capacity' => $capacity,
            'status' => $status
        ];

    // Delete an event
    } elseif ($action === 'delete') {

        $id = filter_var(
            $_POST['event_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        if (!$id || !can_manage_event($pdo, (int) $id)) {

            flash(
                'error',
                'You do not have permission to delete that event.'
            );

            redirect('manage_events.php');
        }

        try {

            $stmt = $pdo->prepare("
                DELETE FROM events
                WHERE event_id = ?
            ");

            $stmt->execute([$id]);

            flash('success', 'Event deleted.');

        } catch (PDOException $e) {

            flash(
                'error',
                'This event has registration or attendance records and cannot be permanently deleted. You can change its status to cancelled instead.'
            );
        }

        redirect('manage_events.php');
    }
}

// Retrieve an event for editing
if (!$editEvent && isset($_GET['edit'])) {

    $id = filter_input(
        INPUT_GET,
        'edit',
        FILTER_VALIDATE_INT
    );

    if ($id && can_manage_event($pdo, (int) $id)) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM events
            WHERE event_id = ?
        ");

        $stmt->execute([$id]);

        $editEvent = $stmt->fetch() ?: null;
    }
}

// Retrieve event records based on user role
if ($user['role'] === 'admin') {

    $stmt = $pdo->query("
        SELECT
            e.*,
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
    ");

} else {

    $stmt = $pdo->prepare("
        SELECT
            e.*,
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
    ");

    $stmt->execute([$user['user_id']]);
}

$events = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="page-heading">

    <div>
        <span class="eyebrow">Event administration</span>

        <h1>Manage Events</h1>

        <p>
            Create and update event information, review participants
            and maintain accurate capacity.
        </p>
    </div>

    <a class="btn btn-outline" href="events.php">
        View public events
    </a>

</section>

<?php if (!$availableClubs): ?>

    <div class="alert alert-warning">
        No clubs are assigned to your account.
        Ask an administrator to assign your user account
        as a club leader.
    </div>

<?php endif; ?>

<?php if ($errors): ?>

    <div class="alert alert-error">

        <strong>Please correct the following:</strong>

        <ul>

            <?php foreach ($errors as $error): ?>

                <li><?= e($error) ?></li>

            <?php endforeach; ?>

        </ul>

    </div>

<?php endif; ?>

<div class="management-layout">

    <!-- Event form -->
    <section class="panel">

        <h2>
            <?= $editEvent ? 'Edit Event' : 'Create an Event' ?>
        </h2>

        <?php if ($availableClubs): ?>

            <form
                method="post"
                action="manage_events.php"
                class="form-stack"
                data-validate="event-form"
            >

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="save"
                >

                <input
                    type="hidden"
                    name="event_id"
                    value="<?= (int) ($editEvent['event_id'] ?? 0) ?>"
                >

                <label>
                    Club

                    <select name="club_id" required>

                        <option value="">Choose a club</option>

                        <?php foreach ($availableClubs as $club): ?>

                            <option
                                value="<?= (int) $club['club_id'] ?>"
                                <?= (int) ($editEvent['club_id'] ?? 0)
                                    === (int) $club['club_id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($club['club_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </label>

                <label>
                    Event name

                    <input
                        type="text"
                        name="event_name"
                        maxlength="150"
                        required
                        value="<?= e($editEvent['event_name'] ?? '') ?>"
                    >
                </label>

                <label>
                    Description

                    <textarea
                        name="description"
                        rows="5"
                        maxlength="3000"
                        required
                        data-counter
                    ><?= e($editEvent['description'] ?? '') ?></textarea>

                    <small class="counter-text"></small>
                </label>

                <div class="form-grid small-gap">

                    <label>
                        Date

                        <input
                            type="date"
                            name="event_date"
                            <?= (
                                !$editEvent
                                || ($editEvent['status'] ?? '') === 'upcoming'
                            )
                                ? 'min="' . e(date('Y-m-d')) . '"'
                                : '' ?>
                            required
                            value="<?= e($editEvent['event_date'] ?? '') ?>"
                        >
                    </label>

                    <label>
                        Time

                        <input
                            type="time"
                            name="event_time"
                            required
                            value="<?= e(
                                isset($editEvent['event_time'])
                                    ? substr($editEvent['event_time'], 0, 5)
                                    : ''
                            ) ?>"
                        >
                    </label>

                </div>

                <label>
                    Location

                    <input
                        type="text"
                        name="location"
                        maxlength="180"
                        required
                        value="<?= e($editEvent['location'] ?? '') ?>"
                    >
                </label>

                <label>
                    Capacity

                    <input
                        type="number"
                        name="capacity"
                        min="1"
                        step="1"
                        required
                        value="<?= e($editEvent['capacity'] ?? '') ?>"
                    >
                </label>

                <?php if ($editEvent): ?>

                    <label>
                        Status

                        <select name="status">

                            <option
                                value="upcoming"
                                <?= ($editEvent['status'] ?? '') === 'upcoming'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Upcoming
                            </option>

                            <option
                                value="completed"
                                <?= ($editEvent['status'] ?? '') === 'completed'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Completed
                            </option>

                            <option
                                value="cancelled"
                                <?= ($editEvent['status'] ?? '') === 'cancelled'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Cancelled
                            </option>

                        </select>
                    </label>

                <?php endif; ?>

                <div class="form-actions">

                    <button class="btn btn-primary" type="submit">
                        <?= $editEvent ? 'Save Changes' : 'Create Event' ?>
                    </button>

                    <?php if ($editEvent): ?>

                        <a class="btn btn-light" href="manage_events.php">
                            Cancel
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        <?php else: ?>

            <p class="muted">
                Event creation becomes available after an administrator
                assigns a club to your account.
            </p>

        <?php endif; ?>

    </section>

    <!-- Event records table -->
    <section class="panel panel-table">

        <div class="panel-heading">

            <h2>Event records</h2>

            <span><?= count($events) ?> total</span>

        </div>

        <?php if ($events): ?>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Club</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($events as $event): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= e($event['event_name']) ?>
                                    </strong>

                                    <br>

                                    <span class="muted">
                                        <?= (int) $event['registration_count'] ?>
                                        registered
                                    </span>
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
                                    <?= (int) $event['registration_count'] ?>
                                    /
                                    <?= (int) $event['capacity'] ?>
                                </td>

                                <td>

                                    <span class="status-pill status-<?= e($event['status']) ?>">
                                        <?= e(ucfirst($event['status'])) ?>
                                    </span>

                                </td>

                                <td class="action-cell">

                                    <a
                                        class="table-link"
                                        href="event_details.php?id=<?= (int) $event['event_id'] ?>"
                                    >
                                        View
                                    </a>

                                    <a
                                        class="table-link"
                                        href="manage_events.php?edit=<?= (int) $event['event_id'] ?>"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        class="table-link"
                                        href="participants.php?event_id=<?= (int) $event['event_id'] ?>"
                                    >
                                        Participants
                                    </a>

                                    <form
                                        method="post"
                                        action="manage_events.php"
                                        data-confirm="Permanently delete this event? Events with registrations cannot be deleted."
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="event_id"
                                            value="<?= (int) $event['event_id'] ?>"
                                        >

                                        <button
                                            class="table-action danger-text"
                                            type="submit"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state compact">
                <p>No events have been added yet.</p>
            </div>

        <?php endif; ?>

    </section>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
