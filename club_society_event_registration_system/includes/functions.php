<?php

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function require_login(): void {
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

function require_role(string ...$roles): void {
    require_login();
    if (!in_array(current_user()['role'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('Access denied. Your account does not have permission to open this page.');
    }
}

function flash(string $type, string $message): void {
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array {
    if (empty($_SESSION['_flash'])) {
        return null;
    }
    $message = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $message;
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void {
    $posted = $_POST['csrf_token'] ?? '';
    if (!is_string($posted) || !hash_equals(csrf_token(), $posted)) {
        http_response_code(400);
        exit('The form expired or could not be verified. Please go back and try again.');
    }
}

function student_profile_id(PDO $pdo, int $userId): ?int {
    $stmt = $pdo->prepare('SELECT student_id FROM students WHERE user_id = ?');
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
}

function managed_club_ids(PDO $pdo, int $userId): array {
    if ((current_user()['role'] ?? '') === 'admin') {
        $stmt = $pdo->query('SELECT club_id FROM clubs ORDER BY club_id');
    } else {
        $stmt = $pdo->prepare('SELECT club_id FROM clubs WHERE club_leader_user_id = ? ORDER BY club_id');
        $stmt->execute([$userId]);
    }
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function can_manage_club(PDO $pdo, int $clubId): bool {
    if ((current_user()['role'] ?? '') === 'admin') {
        return true;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clubs WHERE club_id = ? AND club_leader_user_id = ?');
    $stmt->execute([$clubId, current_user()['user_id']]);
    return (int)$stmt->fetchColumn() > 0;
}

function can_manage_event(PDO $pdo, int $eventId): bool {
    $stmt = $pdo->prepare('SELECT club_id FROM events WHERE event_id = ?');
    $stmt->execute([$eventId]);
    $clubId = $stmt->fetchColumn();
    return $clubId !== false && can_manage_club($pdo, (int)$clubId);
}

function format_date(?string $date): string {
    if (!$date) return '—';
    $time = strtotime($date);
    return $time ? date('d M Y', $time) : e($date);
}

function format_time(?string $time): string {
    if (!$time) return '—';
    $time = substr($time, 0, 8);
    $stamp = strtotime($time);
    return $stamp ? date('g:i A', $stamp) : e($time);
}
