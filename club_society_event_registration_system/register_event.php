<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role('student');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('events.php');
verify_csrf();
$eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
if (!$eventId) { flash('error', 'Invalid event selected.'); redirect('events.php'); }
$studentId = student_profile_id($pdo, (int)current_user()['user_id']);
if (!$studentId) { flash('error', 'Your account does not have a student profile. Please contact the administrator.'); redirect('events.php'); }
try {
    $pdo->beginTransaction();
    // Lock the event row while checking the capacity so that two sign-ups do not overfill it.
    $stmt = $pdo->prepare('SELECT event_id, event_date, status, capacity FROM events WHERE event_id = ? FOR UPDATE');
    $stmt->execute([$eventId]); $event = $stmt->fetch();
    if (!$event) throw new RuntimeException('The selected event could not be found.');
    if ($event['status'] !== 'upcoming' || $event['event_date'] < date('Y-m-d')) throw new RuntimeException('Registration is closed for this event.');
    $existing = $pdo->prepare('SELECT registration_id, status FROM registrations WHERE student_id = ? AND event_id = ?');
    $existing->execute([$studentId, $eventId]); $registration = $existing->fetch();
    if ($registration && $registration['status'] === 'registered') throw new RuntimeException('You are already registered for this event.');
    $count = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status = 'registered'");
    $count->execute([$eventId]);
    if ((int)$count->fetchColumn() >= (int)$event['capacity']) throw new RuntimeException('Sorry, this event is full.');
    if ($registration) {
        $update = $pdo->prepare("UPDATE registrations SET status = 'registered', registration_date = CURRENT_TIMESTAMP WHERE registration_id = ?");
        $update->execute([$registration['registration_id']]);
    } else {
        $insert = $pdo->prepare("INSERT INTO registrations (student_id, event_id, status) VALUES (?, ?, 'registered')");
        $insert->execute([$studentId, $eventId]);
    }
    $pdo->commit();
    flash('success', 'You are registered for the event.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Registration could not be saved. Please try again.');
}
redirect('event_details.php?id=' . $eventId);
