<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) redirect('dashboard.php');
$pageTitle = 'Log In';
$error = '';
$usernameInput = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $usernameInput = trim((string)($_POST['username'] ?? ''));
    $passwordInput = (string)($_POST['password'] ?? '');
    $stmt = $pdo->prepare('SELECT user_id, username, full_name, email, role, password_hash, is_active FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$usernameInput]);
    $user = $stmt->fetch();
    if ($user && (int)$user['is_active'] === 1 && password_verify($passwordInput, $user['password_hash'])) {
        session_regenerate_id(true);
        unset($user['password_hash'], $user['is_active']);
        $_SESSION['user'] = $user;
        flash('success', 'Welcome back, ' . $user['full_name'] . '.');
        redirect('dashboard.php');
    }
    $error = 'The username or password is incorrect, or the account is inactive.';
}
include __DIR__ . '/includes/header.php';
?>
<section class="form-page narrow-page"><div class="form-intro"><span class="eyebrow">Welcome back</span><h1>Log in to your account</h1><p>Access your events and club management tools.</p></div>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form class="panel form-stack" method="post" action="login.php">
    <?= csrf_field() ?>
    <label>Username <input type="text" name="username" maxlength="50" required autofocus autocomplete="username" value="<?= e($usernameInput) ?>"></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary btn-wide" type="submit">Log In</button>
    <p class="form-footnote">Student account not created yet? <a href="register.php">Register here</a>.</p>
</form>
<div class="demo-note"><strong>Demo account for local testing</strong><p>Username: <code>admin</code> . Password: <code>Demo123!</code></p></div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
