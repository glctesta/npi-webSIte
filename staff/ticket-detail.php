<?php
require_once __DIR__ . '/includes/staff_auth.php';
require_staff_role(['admin', 'manager']);
require_once __DIR__ . '/includes/staff_functions.php';
require_once __DIR__ . '/../includes/mailer.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: /staff/tickets.php");
    exit;
}

$ticket = db_fetch("SELECT t.*, s.name as assigned_name FROM tickets t LEFT JOIN staff s ON t.assigned_staff_id = s.id WHERE t.id = ?", [$id]);
if (!$ticket) {
    flash("Ticket not found.", "error");
    header("Location: /staff/tickets.php");
    exit;
}

$page_title = "Ticket: " . htmlspecialchars($ticket['ticket_code']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        flash("Invalid CSRF token.", "error");
        header("Location: /staff/ticket-detail.php?id=$id");
        exit;
    }

    $status = $_POST['status'] ?? $ticket['status'];
    $priority = $_POST['priority'] ?? $ticket['priority'];
    $assigned_staff_id = !empty($_POST['assigned_staff_id']) ? $_POST['assigned_staff_id'] : null;
    $reply_message = trim($_POST['reply_message'] ?? '');
    $send_email = isset($_POST['send_email']);

    $closed_at = ($status === 'closed' && $ticket['status'] !== 'closed') ? date('Y-m-d H:i:s') : $ticket['closed_at'];

    db_query(
        "UPDATE tickets SET status = ?, priority = ?, assigned_staff_id = ?, closed_at = ? WHERE id = ?",
        [$status, $priority, $assigned_staff_id, $closed_at, $id]
    );

    if ($reply_message) {
        db_insert(
            "INSERT INTO ticket_messages (ticket_id, sender_type, sender_name, message) VALUES (?, 'staff', ?, ?)",
            [$id, $_SESSION['staff_name'], $reply_message]
        );

        if ($send_email) {
            $mailer = new SmtpMailer();
            $mail_subject = "Re: Ticket [" . $ticket['ticket_code'] . "] " . $ticket['subject'];
            $mail_body = "<h3>Update on your ticket</h3><p>" . nl2br(htmlspecialchars($reply_message)) . "</p><br><p>Regards,<br>Support Team</p>";
            $mailer->sendMail($ticket['contact_email'], $mail_subject, $mail_body);
        }
    }

    flash("Ticket updated successfully.", "success");
    header("Location: /staff/ticket-detail.php?id=$id");
    exit;
}

$messages = db_fetch_all("SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC", [$id]);
$staff_members = db_fetch_all("SELECT id, full_name AS name FROM staff WHERE role IN ('admin', 'manager')");

include __DIR__ . '/includes/staff_header.php';
?>

<div class="welcome-banner" style="display:flex; justify-content:space-between; align-items:center;">
    <div>
        <h1><?= htmlspecialchars($ticket['subject']) ?></h1>
        <p>Code: <strong><?= htmlspecialchars($ticket['ticket_code']) ?></strong></p>
    </div>
    <a href="tickets.php" class="btn btn-outline">Back to Tickets</a>
</div>

<div style="display:flex; gap:1.5rem; flex-wrap:wrap;">
    <!-- Main Content: Timeline -->
    <div style="flex:1; min-width:300px;">
        <div class="glass-card" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h3>Message Timeline</h3>
            <hr style="border-color:rgba(255,255,255,0.1); margin:1rem 0;">
            <div class="timeline" style="display:flex; flex-direction:column; gap:1rem;">
                <?php foreach ($messages as $m): ?>
                    <div style="padding:1rem; border-radius:8px; background: <?= $m['sender_type'] === 'user' ? 'rgba(255,255,255,0.05)' : ($m['sender_type'] === 'staff' ? 'rgba(59, 130, 246, 0.1)' : 'rgba(255, 193, 7, 0.1)') ?>; border-left: 4px solid <?= $m['sender_type'] === 'user' ? '#9ca3af' : ($m['sender_type'] === 'staff' ? '#3b82f6' : '#ffc107') ?>;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem; font-size:0.9rem;">
                            <strong><?= htmlspecialchars($m['sender_name']) ?> (<?= htmlspecialchars(ucfirst($m['sender_type'])) ?>)</strong>
                            <span class="text-muted"><?= date('Y-m-d H:i:s', strtotime($m['created_at'])) ?></span>
                        </div>
                        <?php if ($m['message']): ?>
                            <div style="line-height:1.5;"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                        <?php endif; ?>
                        <?php if ($m['attachment_path']): ?>
                            <div style="margin-top:0.5rem;">
                                <a href="download-ticket-attachment.php?ticket_id=<?= $id ?>&filename=<?= urlencode(basename($m['attachment_path'])) ?>" class="btn btn-sm btn-outline">📎 <?= htmlspecialchars($m['attachment_name']) ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="glass-card" style="padding:1.5rem;">
            <h3>Reply / Update</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div style="display:flex; gap:1rem; margin-bottom:1rem;">
                    <div style="flex:1;">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <?php foreach (['open', 'in_progress', 'resolved', 'closed'] as $s): ?>
                                <option value="<?= $s ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label>Priority</label>
                        <select name="priority" class="form-control">
                            <?php foreach (['low', 'normal', 'high', 'critical'] as $p): ?>
                                <option value="<?= $p ?>" <?= $ticket['priority'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label>Assigned To</label>
                        <select name="assigned_staff_id" class="form-control">
                            <option value="">Unassigned</option>
                            <?php foreach ($staff_members as $sm): ?>
                                <option value="<?= $sm['id'] ?>" <?= $ticket['assigned_staff_id'] == $sm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sm['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:1rem;">
                    <label>Reply Message</label>
                    <textarea name="reply_message" class="form-control" rows="5" placeholder="Type response here..."></textarea>
                </div>

                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
                    <input type="checkbox" id="send_email" name="send_email" value="1" checked>
                    <label for="send_email">Send response via email to client</label>
                </div>

                <button type="submit" class="btn btn-primary">Submit Reply / Update</button>
            </form>
        </div>
    </div>

    <!-- Sidebar: Info -->
    <div style="width:300px;">
        <div class="glass-card" style="padding:1.5rem; position:sticky; top:1.5rem;">
            <h3>Ticket Details</h3>
            <hr style="border-color:rgba(255,255,255,0.1); margin:1rem 0;">
            
            <p><strong>Contact Name:</strong><br><?= htmlspecialchars($ticket['contact_name']) ?></p>
            <p><strong>Contact Email:</strong><br><a href="mailto:<?= htmlspecialchars($ticket['contact_email']) ?>" style="color:var(--primary-color);"><?= htmlspecialchars($ticket['contact_email']) ?></a></p>
            <p><strong>Company:</strong><br><?= htmlspecialchars($ticket['company'] ?: '-') ?></p>
            <p><strong>App Version:</strong><br><?= htmlspecialchars($ticket['app_version'] ?: '-') ?></p>
            <p><strong>Category:</strong><br><?= htmlspecialchars($ticket['category'] ?: '-') ?></p>
            <p><strong>Created:</strong><br><?= date('Y-m-d H:i', strtotime($ticket['created_at'])) ?></p>
            <p><strong>Last Updated:</strong><br><?= date('Y-m-d H:i', strtotime($ticket['updated_at'])) ?></p>
            <?php if ($ticket['closed_at']): ?>
                <p><strong>Closed:</strong><br><?= date('Y-m-d H:i', strtotime($ticket['closed_at'])) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/staff_footer.php'; ?>
