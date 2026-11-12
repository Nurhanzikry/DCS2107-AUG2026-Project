
<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Events';

// Get search and filter values
$search = trim((string) ($_GET['q'] ?? ''));

$clubId = filter_input(
    INPUT_GET,
    'club_id',
    FILTER_VALIDATE_INT
) ?: 0;

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));

$status = (string) ($_GET['status'] ?? 'upcoming');

$allowedStatuses = [
    'upcoming',
    'completed',
    'cancelled',
    'all'
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = 'upcoming';
}

// Retrieve available clubs for the filter
$clubs = $pdo->query("
    SELECT club_id, club_name
    FROM clubs
    ORDER BY club_name
")->fetchAll();

// Prepare the main event query
$sql = "
    SELECT
        e.*,
        c.club_name,
        (
            SELECT COUNT(*)
            FROM registrations r
            WHERE r.event_id = e.event_id
              AND r.status = 'registered'
        ) AS registered_count
    FROM events e
    JOIN clubs c ON c.club_id = e.club_id
    WHERE 1 = 1
";

$params = [];

// Filter by event status
if ($status !== 'all') {
    $sql .= ' AND e.status = ?';
    $params[] = $status;
}

// Show upcoming events from today onwards
if ($status === 'upcoming') {
    $sql .= ' AND e.event_date >= CURDATE()';
}

// Filter by club
if ($clubId > 0) {
    $sql .= ' AND e.club_id = ?';
    $params[] = $clubId;
}

// Filter by starting date
if (
    $dateFrom !== ''
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)
) {
    $sql .= ' AND e.event_date >= ?';
    $params[] = $dateFrom;
}

// Search event name, description, location or club
if ($search !== '') {

    $sql .= "
        AND (
            e.event_name LIKE ?
            OR e.description LIKE ?
            OR e.location LIKE ?
            OR c.club_name LIKE ?
        )
    ";

    $term = '%' . $search . '%';

    array_push(
        $params,
        $term,
        $term,
        $term,
        $term
    );
}

// Sort events by date and time
$sql .= ' ORDER BY e.event_date ASC, e.event_time ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$events = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="page-heading">

    <div>
        <span class="eyebrow">Plan something new</span>

        <h1>Campus Events</h1>

        <p>
            Search activities, check the details
            and register for a place.
        </p>
    </div>

    <?php
    if (
        current_user()
        && in_array(
            current_user()['role'],
            ['club_member', 'admin'],
            true
        )
    ):
    ?>

        <a class="btn btn-primary" href="manage_events.php">
            + Manage Events
        </a>

    <?php endif; ?>

</section>

<form class="filter-panel" method="get" action="events.php">

    <label class="filter-wide">
        Search events

        <input
            type="search"
            name="q"
            value="<?= e($search) ?>"
            placeholder="Event name, location or club"
        >
    </label>

    <label>
        Club

        <select name="club_id">

            <option value="0">All clubs</option>

            <?php foreach ($clubs as $club): ?>

                <option
                    value="<?= (int) $club['club_id'] ?>"
                    <?= $clubId === (int) $club['club_id']
                        ? 'selected'
                        : '' ?>
                >
                    <?= e($club['club_name']) ?>
                </option>

            <?php endforeach; ?>

        </select>
    </label>

    <label>
        From date

        <input
            type="date"
            name="date_from"
            value="<?= e($dateFrom) ?>"
        >
    </label>

    <label>
        Status

        <select name="status">

            <?php
            $statusOptions = [
                'upcoming' => 'Upcoming',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                'all' => 'All events'
            ];

            foreach ($statusOptions as $key => $label):
            ?>

                <option
                    value="<?= e($key) ?>"
                    <?= $status === $key ? 'selected' : '' ?>
                >
                    <?= e($label) ?>
                </option>

            <?php endforeach; ?>

        </select>
    </label>

    <div class="filter-actions">

        <button type="submit" class="btn btn-primary">
            Apply Filters
        </button>

        <a class="btn btn-light" href="events.php">
            Reset
        </a>

    </div>

</form>

<div class="results-heading">
    <strong><?= count($events) ?> event(s) found</strong>

    <span>
        Search and filters use records saved in MySQL.
    </span>
</div>

<?php if ($events): ?>

    <div class="event-grid">

        <?php foreach ($events as $event): ?>

            <?php
            $remaining = max(
                0,
                (int) $event['capacity']
                - (int) $event['registered_count']
            );
            ?>

            <article class="event-card">

                <div class="event-date">

                    <span>
                        <?= e(date('M', strtotime($event['event_date']))) ?>
                    </span>

                    <strong>
                        <?= e(date('d', strtotime($event['event_date']))) ?>
                    </strong>

                </div>

                <div class="event-card-body">

                    <span class="category-label">
                        <?= e($event['club_name']) ?>
                        ·
                        <?= e(ucfirst($event['status'])) ?>
                    </span>

                    <h3><?= e($event['event_name']) ?></h3>

                    <p>
                        <?= e(mb_strimwidth(
                            $event['description'],
                            0,
                            115,
                            '…'
                        )) ?>
                    </p>

                    <div class="event-meta">

                        <span>
                            ◷ <?= e(format_time($event['event_time'])) ?>
                        </span>

                        <span>
                            ⌖ <?= e($event['location']) ?>
                        </span>

                    </div>

                    <a
                        class="text-link"
                        href="event_details.php?id=<?= (int) $event['event_id'] ?>"
                    >
                        View details →
                    </a>

                </div>

                <div class="event-capacity">
                    <?= (int) $event['registered_count'] ?>
                    /
                    <?= (int) $event['capacity'] ?>
                    registered ·
                    <?= $remaining ?> space(s) left
                </div>

            </article>

        <?php endforeach; ?>

    </div>

<?php else: ?>

    <div class="empty-state">
        <h3>No events match your search</h3>

        <p>
            Try changing the search term or filters.
        </p>
    </div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
