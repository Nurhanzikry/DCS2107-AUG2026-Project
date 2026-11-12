
<?php
require_once __DIR__ . '/includes/bootstrap.php';

require_role('admin');

$pageTitle = 'Manage Clubs';

$errors = [];
$editClub = null;

// Handle submitted forms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    // Add or update a club
    if ($action === 'save') {

        $id = filter_var(
            $_POST['club_id'] ?? '',
            FILTER_VALIDATE_INT
        ) ?: 0;

        $name = trim((string) ($_POST['club_name'] ?? ''));

        $description = trim(
            (string) ($_POST['description'] ?? '')
        );

        $category = trim((string) ($_POST['category'] ?? ''));

        $leaderId = filter_var(
            $_POST['club_leader_user_id'] ?? '',
            FILTER_VALIDATE_INT
        ) ?: null;

        // Validate required fields
        if (
            $name === ''
            || $description === ''
            || $category === ''
        ) {
            $errors[] = 'Club name, description and category are required.';
        }

        if (mb_strlen($name) > 120 || mb_strlen($category) > 80) {
            $errors[] = 'The club name or category is too long.';
        }

        // Check whether the selected club leader is valid
        if ($leaderId) {

            $leader = $pdo->prepare("
                SELECT COUNT(*)
                FROM users
                WHERE user_id = ?
                  AND role = 'club_member'
                  AND is_active = 1
            ");

            $leader->execute([$leaderId]);

            if (!(int) $leader->fetchColumn()) {
                $errors[] = 'Choose an active club member as the club leader.';
            }
        }

        // Save the club if there are no validation errors
        if (!$errors) {

            try {

                if ($id) {

                    // Update an existing club
                    $stmt = $pdo->prepare("
                        UPDATE clubs
                        SET
                            club_name = ?,
                            description = ?,
                            category = ?,
                            club_leader_user_id = ?
                        WHERE club_id = ?
                    ");

                    $stmt->execute([
                        $name,
                        $description,
                        $category,
                        $leaderId,
                        $id
                    ]);

                    flash('success', 'Club details updated.');

                } else {

                    // Insert a new club
                    $stmt = $pdo->prepare("
                        INSERT INTO clubs
                            (club_name, description, category, club_leader_user_id)
                        VALUES (?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $name,
                        $description,
                        $category,
                        $leaderId
                    ]);

                    flash('success', 'New club added.');
                }

                redirect('manage_clubs.php');

            } catch (PDOException $e) {

                $errors[] = 'The club could not be saved. The club name may already exist.';
            }
        }

        // Keep the entered values when validation fails
        $editClub = [
            'club_id' => $id,
            'club_name' => $name,
            'description' => $description,
            'category' => $category,
            'club_leader_user_id' => $leaderId
        ];

    // Delete a club
    } elseif ($action === 'delete') {

        $id = filter_var(
            $_POST['club_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        if ($id) {

            try {

                $stmt = $pdo->prepare("
                    DELETE FROM clubs
                    WHERE club_id = ?
                ");

                $stmt->execute([$id]);

                flash('success', 'Club deleted.');

            } catch (PDOException $e) {

                flash(
                    'error',
                    'This club cannot be deleted because it still has events linked to it. Remove or reassign those events first.'
                );
            }
        }

        redirect('manage_clubs.php');
    }
}

// Retrieve a club for editing
if (!$editClub && isset($_GET['edit'])) {

    $id = filter_input(
        INPUT_GET,
        'edit',
        FILTER_VALIDATE_INT
    );

    if ($id) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM clubs
            WHERE club_id = ?
        ");

        $stmt->execute([$id]);

        $editClub = $stmt->fetch() ?: null;
    }
}

// Retrieve all clubs and their event counts
$clubs = $pdo->query("
    SELECT
        c.*,
        u.full_name AS leader_name,
        (
            SELECT COUNT(*)
            FROM events e
            WHERE e.club_id = c.club_id
        ) AS event_count
    FROM clubs c
    LEFT JOIN users u
        ON u.user_id = c.club_leader_user_id
    ORDER BY c.club_name
")->fetchAll();

// Retrieve available club leaders
$leaders = $pdo->query("
    SELECT user_id, full_name
    FROM users
    WHERE role = 'club_member'
      AND is_active = 1
    ORDER BY full_name
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="page-heading">

    <div>
        <span class="eyebrow">Administrator tools</span>

        <h1>Manage Clubs</h1>

        <p>
            Add, edit, search and remove club and society records.
        </p>
    </div>

    <a class="btn btn-outline" href="clubs.php">
        View public clubs
    </a>

</section>

<?php if ($errors): ?>

    <div class="alert alert-error">
        <ul>

            <?php foreach ($errors as $error): ?>

                <li><?= e($error) ?></li>

            <?php endforeach; ?>

        </ul>
    </div>

<?php endif; ?>

<div class="management-layout">

    <!-- Club form -->
    <section class="panel">

        <h2>
            <?= $editClub ? 'Edit Club' : 'Add a Club' ?>
        </h2>

        <form
            method="post"
            action="manage_clubs.php"
            class="form-stack"
        >

            <?= csrf_field() ?>

            <input type="hidden" name="action" value="save">

            <input
                type="hidden"
                name="club_id"
                value="<?= (int) ($editClub['club_id'] ?? 0) ?>"
            >

            <label>
                Club name

                <input
                    type="text"
                    name="club_name"
                    maxlength="120"
                    required
                    value="<?= e($editClub['club_name'] ?? '') ?>"
                >
            </label>

            <label>
                Category

                <input
                    type="text"
                    name="category"
                    maxlength="80"
                    required
                    placeholder="Technology, Sports, Arts..."
                    value="<?= e($editClub['category'] ?? '') ?>"
                >
            </label>

            <label>
                Description

                <textarea
                    name="description"
                    rows="4"
                    maxlength="2000"
                    required
                    data-counter
                ><?= e($editClub['description'] ?? '') ?></textarea>

                <small class="counter-text"></small>
            </label>

            <label>
                Club leader

                <select name="club_leader_user_id">

                    <option value="">Not assigned</option>

                    <?php foreach ($leaders as $leader): ?>

                        <option
                            value="<?= (int) $leader['user_id'] ?>"
                            <?= (int) ($editClub['club_leader_user_id'] ?? 0)
                                === (int) $leader['user_id']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= e($leader['full_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </label>

            <div class="form-actions">

                <button class="btn btn-primary" type="submit">
                    <?= $editClub ? 'Save Changes' : 'Add Club' ?>
                </button>

                <?php if ($editClub): ?>

                    <a class="btn btn-light" href="manage_clubs.php">
                        Cancel
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </section>

    <!-- Club records table -->
    <section class="panel panel-table">

        <div class="panel-heading">

            <h2>Club records</h2>

            <span><?= count($clubs) ?> total</span>

        </div>

        <?php if ($clubs): ?>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Club</th>
                            <th>Category</th>
                            <th>Leader</th>
                            <th>Events</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($clubs as $club): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= e($club['club_name']) ?>
                                    </strong>

                                    <br>

                                    <span class="muted">
                                        <?= e(mb_strimwidth(
                                            $club['description'],
                                            0,
                                            55,
                                            '…'
                                        )) ?>
                                    </span>

                                </td>

                                <td>
                                    <?= e($club['category']) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $club['leader_name']
                                        ?? 'Not assigned'
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int) $club['event_count'] ?>
                                </td>

                                <td class="action-cell">

                                    <a
                                        class="table-link"
                                        href="manage_clubs.php?edit=<?= (int) $club['club_id'] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="post"
                                        action="manage_clubs.php"
                                        data-confirm="Delete this club? Clubs with linked events cannot be deleted."
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="club_id"
                                            value="<?= (int) $club['club_id'] ?>"
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
                <p>No clubs have been added.</p>
            </div>

        <?php endif; ?>

    </section>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
