<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Clubs and Societies';

// Get the search keyword from the URL.
$search = trim((string) ($_GET['q'] ?? ''));

// Get clubs and count their upcoming events.
$sql = "
    SELECT
        c.*,
        u.full_name AS leader_name,
        (
            SELECT COUNT(*)
            FROM events e
            WHERE e.club_id = c.club_id
              AND e.status = 'upcoming'
              AND e.event_date >= CURDATE()
        ) AS upcoming_count
    FROM clubs c
    LEFT JOIN users u
        ON u.user_id = c.club_leader_user_id
    WHERE 1 = 1
";

$params = [];

// Add search conditions when a keyword is entered.
if ($search !== '') {
    $sql .= "
        AND (
            c.club_name LIKE ?
            OR c.description LIKE ?
            OR c.category LIKE ?
        )
    ";

    $term = '%' . $search . '%';
    $params = [$term, $term, $term];
}

// Arrange clubs alphabetically.
$sql .= " ORDER BY c.club_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clubs = $stmt->fetchAll();

// Display the common page header.
include __DIR__ . '/includes/header.php';
?>

<section class="page-heading">
    <div>
        <span class="eyebrow">Find your community</span>
        <h1>Clubs &amp; Societies</h1>
        <p>Explore the groups that bring students together on campus.</p>
    </div>

    <?php
    if (
        current_user()
        && current_user()['role'] === 'admin'
    ):
    ?>
        <a class="btn btn-primary" href="manage_clubs.php">
            Manage Clubs
        </a>
    <?php endif; ?>
</section>

<form class="search-bar" method="get" action="clubs.php">
    <label class="search-input">
        <span>⌕</span>
        <input
            type="search"
            name="q"
            placeholder="Search clubs, categories or descriptions"
            value="<?= e($search) ?>">
    </label>

    <button class="btn btn-primary" type="submit">
        Search
    </button>

    <?php if ($search !== ''): ?>
        <a class="btn btn-light" href="clubs.php">
            Clear
        </a>
    <?php endif; ?>
</form>

<?php if ($clubs): ?>

    <div class="club-grid">
        <?php foreach ($clubs as $club): ?>
            <article class="club-card">

                <div class="club-card-top">
                    <span class="club-avatar">
                        <?= e(mb_strtoupper(
                            mb_substr($club['club_name'], 0, 1)
                        )) ?>
                    </span>

                    <span class="category-pill">
                        <?= e($club['category']) ?>
                    </span>
                </div>

                <h2><?= e($club['club_name']) ?></h2>

                <p><?= e($club['description']) ?></p>

                <div class="club-card-bottom">
                    <span>
                        <?= (int) $club['upcoming_count'] ?>
                        upcoming event(s)
                    </span>

                    <?php if ($club['leader_name']): ?>
                        <span>
                            Led by <?= e($club['leader_name']) ?>
                        </span>
                    <?php endif; ?>
                </div>

            </article>
        <?php endforeach; ?>
    </div>

<?php else: ?>

    <div class="empty-state">
        <h3>No clubs found</h3>
        <p>Try another search term.</p>
    </div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>