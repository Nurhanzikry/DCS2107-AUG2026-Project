<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) redirect('dashboard.php');
$pageTitle = 'Create Student Account';
$errors = [];
$values = ['username'=>'', 'full_name'=>'', 'email'=>'', 'phone'=>'', 'student_number'=>'', 'programme'=>''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($values as $key => $unused) $values[$key] = trim((string)($_POST[$key] ?? ''));
    $passwordInput = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    if ($values['username'] === '' || strlen($values['username']) < 3) $errors[] = 'Username must contain at least 3 characters.';
    if ($values['full_name'] === '') $errors[] = 'Full name is required.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($values['student_number'] === '' || $values['programme'] === '') $errors[] = 'Student number and programme are required.';
    if (strlen($passwordInput) < 8) $errors[] = 'Password must contain at least 8 characters.';
    if ($passwordInput !== $confirmPassword) $errors[] = 'The passwords do not match.';
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
            $check->execute([$values['username'], $values['email']]);
            if ((int)$check->fetchColumn() > 0) throw new RuntimeException('That username or email address is already in use.');
            $checkStudent = $pdo->prepare('SELECT COUNT(*) FROM students WHERE student_number = ?');
            $checkStudent->execute([$values['student_number']]);
            if ((int)$checkStudent->fetchColumn() > 0) throw new RuntimeException('That student number is already registered.');
            $insertUser = $pdo->prepare('INSERT INTO users (username, full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?, \'student\')');
            $insertUser->execute([$values['username'], $values['full_name'], $values['email'], $values['phone'] ?: null, password_hash($passwordInput, PASSWORD_DEFAULT)]);
            $userId = (int)$pdo->lastInsertId();
            $insertStudent = $pdo->prepare('INSERT INTO students (user_id, student_number, programme) VALUES (?, ?, ?)');
            $insertStudent->execute([$userId, $values['student_number'], $values['programme']]);
            $pdo->commit();
            flash('success', 'Your account was created. You can now log in.');
            redirect('login.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'The account could not be created. Check that the information is unique and try again.';
        }
    }
}
include __DIR__ . '/includes/header.php';
?>
<section class="form-page narrow-page"><div class="form-intro"><span class="eyebrow">Join the community</span><h1>Create your student account</h1><p>Use your student details to register for campus events.</p></div>
<?php if ($errors): ?><div class="alert alert-error"><strong>Please check the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="panel form-grid" method="post" action="register.php" data-validate="student-registration">
    <?= csrf_field() ?>
    <label>Full name <input type="text" name="full_name" maxlength="120" required value="<?= e($values['full_name']) ?>"></label>
    <label>Username <input type="text" name="username" minlength="3" maxlength="50" required value="<?= e($values['username']) ?>"></label>
    <label>Email address <input type="email" name="email" maxlength="150" required value="<?= e($values['email']) ?>"></label>
    <label>Phone number <input type="tel" name="phone" maxlength="30" value="<?= e($values['phone']) ?>"></label>
    <label>Student number <input type="text" name="student_number" maxlength="30" required value="<?= e($values['student_number']) ?>"></label>
    <label>Programme <input type="text" name="programme" maxlength="120" required value="<?= e($values['programme']) ?>" placeholder="e.g. Diploma in Information Technology"></label>
    <label>Password <input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
    <label>Confirm password <input type="password" name="confirm_password" minlength="8" required autocomplete="new-password"></label>
    <div class="form-actions full-width"><button class="btn btn-primary" type="submit">Create Account</button><span>Already registered? <a href="login.php">Log in</a></span></div>
</form></section>
<?php include __DIR__ . '/includes/footer.php'; ?>
