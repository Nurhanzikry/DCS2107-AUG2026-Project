
<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Home';

// Retrieve the three nearest upcoming events
$stmt = $pdo->prepare("
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
    WHERE e.status = 'upcoming'
      AND e.event_date >= CURDATE()
    ORDER BY e.event_date, e.event_time
    LIMIT 3
");

$stmt->execute();

$upcomingEvents = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Main homepage banner -->
<section class="hero">

    <div class="hero-copy">

        <span class="eyebrow">
            Campus life, made easier
        </span>

        <h1>
            Find your people.
            <br>
            <span>Join an event.</span>
        </h1>

        <p>
            Discover club activities, register online and
            keep all your event information in one place.
        </p>

        <div class="hero-actions">

            <a class="btn btn-primary" href="events.php">
                Explore Events
            </a>

            <?php if (!is_logged_in()): ?>

                <a class="btn btn-outline" href="register.php">
                    Create Student Account
                </a>

            <?php endif; ?>

        </div>

        <div class="hero-note">
            <span class="dot"></span>
            One place for clubs, events and registrations
        </div>

    </div>

    <div class="hero-art" aria-hidden="true">

        <div class="art-circle circle-one"></div>

        <div class="art-circle circle-two"></div>

        <div class="event-ticket">

            <span class="ticket-tag">
                CAMPUS EVENT
            </span>

            <strong>
                Make time
                <br>
                for something
                <br>
                new.
            </strong>

            <div class="ticket-lines">
                <i></i>
                <i></i>
                <i></i>
            </div>

            <span class="ticket-small">
                Discover · Participate · Connect
            </span>

        </div>

        <div class="floating-chip chip-top">
            ✦ Find your club
        </div>

        <div class="floating-chip chip-bottom">
            ✓ Easy registration
        </div>

    </div>

</section>

<!-- Website features -->
<section class="section-block">

    <div class="section-heading">

        <div>
            <span class="eyebrow">What you can do</span>

            <h2>Everything in one place</h2>
        </div>

        <a class="text-link" href="clubs.php">
            Browse clubs →
        </a>

    </div>

    <div class="feature-grid">

        <article class="feature-card">

            <div class="feature-icon icon-lilac">
                ⌕
            </div>

            <h3>Discover activities</h3>

            <p>
                Browse club information and find upcoming
                events that match your interests.
            </p>

        </article>

        <article class="feature-card">

            <div class="feature-icon icon-mint">
                ✓
            </div>

            <h3>Register online</h3>

            <p>
                Reserve a space and check your registered
                activities from your own account.
            </p>

        </article>

        <article class="feature-card">

            <div class="feature-icon icon-peach">
                ▤
            </div>

            <h3>Manage events</h3>

            <p>
                Authorized club members can manage event
                details and participant attendance.
            </p>

        </article>

    </div>

</section>

<!-- Upcoming events -->
<section class="section-block">

    <div class="section-heading">

        <div>
            <span class="eyebrow">Coming up</span>

            <h2>Upcoming events</h2>
        </div>

        <a class="text-link" href="events.php">
            View all events →
        </a>

    </div>

    <?php if ($upcomingEvents): ?>

        <div class="event-grid">

            <?php foreach ($upcomingEvents as $event): ?>

                <article class="event-card">

                    <div class="event-date">

                        <span>
                            <?= e(date(
                                'M',
                                strtotime($event['event_date'])
                            )) ?>
                        </span>

                        <strong>
                            <?= e(date(
                                'd',
                                strtotime($event['event_date'])
                            )) ?>
                        </strong>

                    </div>

                    <div class="event-card-body">

                        <span class="category-label">
                            <?= e($event['club_name']) ?>
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
                        registered
                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty-state">

            <h3>No upcoming events yet</h3>

            <p>
                Events added by club members will appear here.
            </p>

        </div>

    <?php endif; ?>

</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
