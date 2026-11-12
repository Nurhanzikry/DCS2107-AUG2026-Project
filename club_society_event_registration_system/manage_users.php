
<?php
require_once __DIR__ . '/includes/bootstrap.php';

require_role('admin');

$pageTitle = 'Manage Users';

$errors = [];
$editUser = null;

// Handle submitted forms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    // Create or update a user account
    if ($action === 'save') {

        $id = filter_var(
            $_POST['user_id'] ?? '',
            FILTER_VALIDATE_INT
        ) ?: 0;

        $username = trim((string) ($_POST['username'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));

        $role = (string) ($_POST['role'] ?? 'student');

        $studentNumber = trim(
            (string) ($_POST['student_number'] ?? '')
        );

        $programme = trim(
            (string) ($_POST['programme'] ?? '')
        );

        $newPassword = (string) ($_POST['password'] ?? '');

        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Validate user information
        if (
            $username === ''
            || strlen($username) < 3
            || $fullName === ''
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $errors[] = 'Enter a username (at least 3 characters), full name and valid email.';
        }

        if (!in_array(
            $role,
            ['student', 'club_member', 'admin'],
            true
        )) {
            $errors[] = 'Choose a valid user role.';
        }

        if (!$id && strlen($newPassword) < 8) {
            $errors[] = 'New user password must contain at least 8 characters.';
        }

        // Prevent an administrator from deactivating their own account
        if (
            $id
            && $id === (int) current_user()['user_id']
            && !$isActive
        ) {
            $errors[] = 'You cannot deactivate your own administrator account.';
        }

        // Student accounts require student information
        if (
            $role === 'student'
            && ($studentNumber === '' || $programme === '')
        ) {
            $errors[] = 'Student accounts require a student number and programme.';
        }

        // Save the account if validation passes
        if (!$errors) {

            try {

                $pdo->beginTransaction();

                // Check for duplicate username or email
                $check = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM users
                    WHERE (username = ? OR email = ?)
                      AND user_id <> ?
                ");

                $check->execute([
                    $username,
                    $email,
                    $id
                ]);

                if ((int) $check->fetchColumn() > 0) {
                    throw new RuntimeException(
                        'That username or email already belongs to another account.'
                    );
                }

                // Check for duplicate student numbers
                if ($role === 'student') {

                    $check = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM students
                        WHERE student_number = ?
                          AND user_id <> ?
                    ");

                    $check->execute([
                        $studentNumber,
                        $id
                    ]);

                    if ((int) $check->fetchColumn() > 0) {
                        throw new RuntimeException(
                            'That student number is already in use.'
                        );
                    }
                }

                // Update an existing account
                if ($id) {

                    $old = $pdo->prepare("
                        SELECT role
                        FROM users
                        WHERE user_id = ?
                    ");

                    $old->execute([$id]);

                    $oldRole = $old->fetchColumn();

                    if ($oldRole === false) {
                        throw new RuntimeException(
                            'User account not found.'
                        );
                    }

                    // Keep existing role to protect linked records
                    if ($oldRole !== $role) {
                        throw new RuntimeException(
                            'A user role cannot be changed here. Create an account with the correct role instead so linked student records stay consistent.'
                        );
                    }

                    // Update account with a new password
                    if ($newPassword !== '') {

                        if (strlen($newPassword) < 8) {
                            throw new RuntimeException(
                                'A changed password must contain at least 8 characters.'
                            );
                        }

                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET
                                username = ?,
                                full_name = ?,
                                email = ?,
                                phone = ?,
                                is_active = ?,
                                password_hash = ?
                            WHERE user_id = ?
                        ");

                        $stmt->execute([
                            $username,
                            $fullName,
                            $email,
                            $phone ?: null,
                            $isActive,
                            password_hash($newPassword, PASSWORD_DEFAULT),
                            $id
                        ]);

                    } else {

                        // Update account without changing password
                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET
                                username = ?,
                                full_name = ?,
                                email = ?,
                                phone = ?,
                                is_active = ?
                            WHERE user_id = ?
                        ");

                        $stmt->execute([
                            $username,
                            $fullName,
                            $email,
                            $phone ?: null,
                            $isActive,
                            $id
                        ]);
                    }

                    // Update student information
                    if ($role === 'student') {

                        $stmt = $pdo->prepare("
                            UPDATE students
                            SET
                                student_number = ?,
                                programme = ?
                            WHERE user_id = ?
                        ");

                        $stmt->execute([
                            $studentNumber,
                            $programme,
                            $id
                        ]);
                    }

                    $pdo->commit();

                    flash('success', 'User account updated.');

                } else {

                    // Create a new user account
                    $stmt = $pdo->prepare("
                        INSERT INTO users (
                            username,
                            full_name,
                            email,
                            phone,
                            password_hash,
                            role,
                            is_active
                        )
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                    ");

                    $stmt->execute([
                        $username,
                        $fullName,
                        $email,
                        $phone ?: null,
                        password_hash($newPassword, PASSWORD_DEFAULT),
                        $role
                    ]);

                    $newId = (int) $pdo->lastInsertId();

                    // Add student information if the role is student
                    if ($role === 'student') {

                        $stmt = $pdo->prepare("
                            INSERT INTO students (
                                user_id,
                                student_number,
                                programme
                            )
                            VALUES (?, ?, ?)
                        ");

                        $stmt->execute([
                            $newId,
                            $studentNumber,
                            $programme
                        ]);
                    }

                    $pdo->commit();

                    flash('success', 'User account created.');
                }

                redirect('manage_users.php');

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to save the account. Check the entered details and linked records.';
            }
        }

        // Keep entered information if validation fails
        $editUser = [
            'user_id' => $id,
            'username' => $username,
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'student_number' => $studentNumber,
            'programme' => $programme,
            'is_active' => $isActive
        ];

    // Delete a user account
    } elseif ($action === 'delete') {

        $id = filter_var(
            $_POST['user_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        if (
            !$id
            || $id === (int) current_user()['user_id']
        ) {

            flash('error', 'You cannot delete your own account.');

        } else {

            try {

                $stmt = $pdo->prepare("
                    DELETE FROM users
                    WHERE user_id = ?
                ");

                $stmt->execute([$id]);

                flash('success', 'User account deleted.');

            } catch (PDOException $e) {

                flash(
                    'error',
                    'This account is linked to an event, club or registration and cannot be deleted until those records are reassigned or removed.'
                );
            }
        }

        redirect('manage_users.php');
    }
}

// Retrieve a user account for editing
if (!$editUser && isset($_GET['edit'])) {

    $id = filter_input(
        INPUT_GET,
        'edit',
        FILTER_VALIDATE_INT
    );

    if ($id) {

        $stmt = $pdo->prepare("
            SELECT
                u.*,
                s.student_number,
                s.programme
            FROM users u
            LEFT JOIN students s ON s.user_id = u.user_id
            WHERE u.user_id = ?
        ");

        $stmt->execute([$id]);

        $editUser = $stmt->fetch() ?: null;
    }
}

// Search user accounts
$search = trim((string) ($_GET['q'] ?? ''));

$sql = "
    SELECT
        u.user_id,
        u.username,
        u.full_name,
        u.email,
        u.phone,
        u.role,
        u.is_active,
        s.student_number,
        s.programme
    FROM users u
    LEFT JOIN students s ON s.user_id = u.user_id
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {

    $sql .= "
        AND (
            u.username LIKE ?
            OR u.full_name LIKE ?
            OR u.email LIKE ?
        )
    ";

    $term = '%' . $search . '%';

    $params = [
        $term,
        $term,
        $term
    ];
}

$sql .= ' ORDER BY u.user_id DESC';

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$users = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="page-heading">

    <div>
        <span class="eyebrow">System administration</span>

        <h1>Manage Users</h1>

        <p>
            Create accounts, update user details, enable or disable
            access and maintain account records.
        </p>
    </div>

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

    <!-- User form -->
    <section class="panel">

        <h2>
            <?= $editUser ? 'Edit User' : 'Create User' ?>
        </h2>

        <form
            method="post"
            action="manage_users.php"
            class="form-stack"
            data-validate="user-form"
        >

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="save"
            >

            <input
                type="hidden"
                name="user_id"
                value="<?= (int) ($editUser['user_id'] ?? 0) ?>"
            >

            <label>
                Username

                <input
                    name="username"
                    type="text"
                    minlength="3"
                    maxlength="50"
                    required
                    value="<?= e($editUser['username'] ?? '') ?>"
                >
            </label>

            <label>
                Full name

                <input
                    name="full_name"
                    type="text"
                    maxlength="120"
                    required
                    value="<?= e($editUser['full_name'] ?? '') ?>"
                >
            </label>

            <label>
                Email

                <input
                    name="email"
                    type="email"
                    maxlength="150"
                    required
                    value="<?= e($editUser['email'] ?? '') ?>"
                >
            </label>

            <label>
                Phone

                <input
                    name="phone"
                    type="tel"
                    maxlength="30"
                    value="<?= e($editUser['phone'] ?? '') ?>"
                >
            </label>

            <label>
                Role

                <select
                    name="role"
                    required
                    <?= $editUser ? 'disabled' : '' ?>
                >

                    <option
                        value="student"
                        <?= ($editUser['role'] ?? 'student') === 'student'
                            ? 'selected'
                            : '' ?>
                    >
                        Student
                    </option>

                    <option
                        value="club_member"
                        <?= ($editUser['role'] ?? '') === 'club_member'
                            ? 'selected'
                            : '' ?>
                    >
                        Club Member
                    </option>

                    <option
                        value="admin"
                        <?= ($editUser['role'] ?? '') === 'admin'
                            ? 'selected'
                            : '' ?>
                    >
                        Administrator
                    </option>

                </select>

                <?php if ($editUser): ?>

                    <input
                        type="hidden"
                        name="role"
                        value="<?= e($editUser['role']) ?>"
                    >

                    <small class="muted">
                        Role is fixed after account creation
                        to protect linked records.
                    </small>

                <?php endif; ?>

            </label>

            <div
                class="student-fields"
                <?= ($editUser['role'] ?? 'student') === 'student'
                    ? ''
                    : 'hidden' ?>
            >

                <label>
                    Student number

                    <input
                        name="student_number"
                        maxlength="30"
                        value="<?= e($editUser['student_number'] ?? '') ?>"
                        <?= ($editUser['role'] ?? 'student') === 'student'
                            ? 'required'
                            : '' ?>
                    >
                </label>

                <label>
                    Programme

                    <input
                        name="programme"
                        maxlength="120"
                        value="<?= e($editUser['programme'] ?? '') ?>"
                        <?= ($editUser['role'] ?? 'student') === 'student'
                            ? 'required'
                            : '' ?>
                    >
                </label>

            </div>

            <label>
                <?= $editUser
                    ? 'New password (leave empty to keep current password)'
                    : 'Password' ?>

                <input
                    name="password"
                    type="password"
                    minlength="8"
                    <?= $editUser ? '' : 'required' ?>
                    autocomplete="new-password"
                >
            </label>

            <?php if ($editUser): ?>

                <label class="check-label">

                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        <?= (int) $editUser['is_active'] === 1
                            ? 'checked'
                            : '' ?>
                    >

                    Account is active

                </label>

            <?php endif; ?>

            <div class="form-actions">

                <button class="btn btn-primary" type="submit">
                    <?= $editUser ? 'Save Changes' : 'Create Account' ?>
                </button>

                <?php if ($editUser): ?>

                    <a class="btn btn-light" href="manage_users.php">
                        Cancel
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </section>

    <!-- User records table -->
    <section class="panel panel-table">

        <div class="panel-heading">

            <h2>User accounts</h2>

            <span><?= count($users) ?> total</span>

        </div>

        <form
            class="table-search"
            method="get"
            action="manage_users.php"
        >

            <input
                type="search"
                name="q"
                value="<?= e($search) ?>"
                placeholder="Search name, username or email"
            >

            <button class="btn btn-small btn-primary" type="submit">
                Search
            </button>

            <?php if ($search !== ''): ?>

                <a class="text-link" href="manage_users.php">
                    Clear
                </a>

            <?php endif; ?>

        </form>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Student details</th>
                        <th>Account</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($users as $u): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= e($u['full_name']) ?>
                                </strong>

                                <br>

                                <span class="muted">
                                    <?= e($u['username']) ?>
                                    ·
                                    <?= e($u['email']) ?>
                                </span>

                            </td>

                            <td>
                                <?= e(ucwords(
                                    str_replace('_', ' ', $u['role'])
                                )) ?>
                            </td>

                            <td>

                                <?= e($u['student_number'] ?: '—') ?>

                                <br>

                                <span class="muted">
                                    <?= e($u['programme'] ?: '') ?>
                                </span>

                            </td>

                            <td>

                                <span class="status-pill <?= (int) $u['is_active'] === 1
                                    ? 'status-registered'
                                    : 'status-cancelled' ?>">

                                    <?= (int) $u['is_active'] === 1
                                        ? 'Active'
                                        : 'Inactive' ?>

                                </span>

                            </td>

                            <td class="action-cell">

                                <a
                                    class="table-link"
                                    href="manage_users.php?edit=<?= (int) $u['user_id'] ?>"
                                >
                                    Edit
                                </a>

                                <?php
                                if (
                                    (int) $u['user_id']
                                    !== (int) current_user()['user_id']
                                ):
                                ?>

                                    <form
                                        method="post"
                                        action="manage_users.php"
                                        data-confirm="Delete this user account? Linked records may prevent deletion."
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int) $u['user_id'] ?>"
                                        >

                                        <button
                                            class="table-action danger-text"
                                            type="submit"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </section>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
