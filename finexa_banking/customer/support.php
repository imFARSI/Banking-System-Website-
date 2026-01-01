<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();
if (isAdmin()) {
    header("Location: ../admin/admin-dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "Customer Support";

// Create new ticket
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_ticket'])) {
    $subject = sanitize($conn, $_POST['subject']);
    $category = sanitize($conn, $_POST['category']);
    $priority = sanitize($conn, $_POST['priority']);
    $description = sanitize($conn, $_POST['description']);
    
    if (!empty($subject) && !empty($description)) {
        $query = "INSERT INTO support_tickets (user_id, subject, category, priority, description, status) VALUES ('$user_id', '$subject', '$category', '$priority', '$description', 'open')";
        
        if (mysqli_query($conn, $query)) {
            $ticket_id = mysqli_insert_id($conn);
            
            createNotification($conn, $user_id, 'Support Ticket Created', 'Your support ticket #' . $ticket_id . ' has been created.', 'ticket');
            logActivity($conn, $user_id, 'create_ticket', 'ticket', $ticket_id, 'Created support ticket');
            
            setAlert('success', 'Support ticket created successfully! Ticket ID: #' . $ticket_id);
            header("Location: support.php");
            exit();
        } else {
            setAlert('error', 'Failed to create ticket.');
        }
    } else {
        setAlert('error', 'Please fill in all required fields.');
    }
}

// Reply to ticket
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_ticket'])) {
    $ticket_id = sanitize($conn, $_POST['ticket_id']);
    $message = sanitize($conn, $_POST['message']);
    
    // Verify ticket belongs to user
    $verify = mysqli_query($conn, "SELECT * FROM support_tickets WHERE ticket_id = '$ticket_id' AND user_id = '$user_id'");
    
    if (mysqli_num_rows($verify) == 1 && !empty($message)) {
        $query = "INSERT INTO ticket_responses (ticket_id, user_id, message, response_type) VALUES ('$ticket_id', '$user_id', '$message', 'customer')";
        
        if (mysqli_query($conn, $query)) {
            mysqli_query($conn, "UPDATE support_tickets SET status = 'in_progress', updated_at = NOW() WHERE ticket_id = '$ticket_id'");
            
            setAlert('success', 'Reply sent successfully!');
            header("Location: support.php?view=$ticket_id");
            exit();
        }
    }
}

// Get user tickets
$tickets_query = "SELECT * FROM support_tickets WHERE user_id = '$user_id' ORDER BY created_at DESC";
$tickets_result = mysqli_query($conn, $tickets_query);

// View specific ticket
$view_ticket = null;
$responses_result = null;
if (isset($_GET['view'])) {
    $ticket_id = sanitize($conn, $_GET['view']);
    $view_query = "SELECT * FROM support_tickets WHERE ticket_id = '$ticket_id' AND user_id = '$user_id'";
    $view_result = mysqli_query($conn, $view_query);
    
    if (mysqli_num_rows($view_result) == 1) {
        $view_ticket = mysqli_fetch_assoc($view_result);
        
        // Get responses
        $responses_query = "SELECT tr.*, u.name, u.role FROM ticket_responses tr JOIN users u ON tr.user_id = u.user_id WHERE tr.ticket_id = '$ticket_id' ORDER BY tr.created_at ASC";
        $responses_result = mysqli_query($conn, $responses_query);
    }
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="fas fa-headset"></i> Customer Support</h2>
    
    <?php if ($view_ticket): ?>
        <!-- View Ticket Details -->
        <div class="mb-3">
            <a href="support.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to All Tickets
            </a>
        </div>
        
        <div class="dashboard-card mb-4">
            <h4 class="mb-3">
                Ticket #<?php echo $view_ticket['ticket_id']; ?>: <?php echo $view_ticket['subject']; ?>
                <span class="badge bg-<?php echo $view_ticket['status'] == 'open' ? 'warning' : ($view_ticket['status'] == 'closed' ? 'secondary' : 'info'); ?> float-end">
                    <?php echo ucfirst($view_ticket['status']); ?>
                </span>
            </h4>
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <strong>Category:</strong> <?php echo ucfirst($view_ticket['category']); ?>
                </div>
                <div class="col-md-3">
                    <strong>Priority:</strong> 
                    <span class="badge bg-<?php echo $view_ticket['priority'] == 'high' ? 'danger' : ($view_ticket['priority'] == 'medium' ? 'warning' : 'info'); ?>">
                        <?php echo ucfirst($view_ticket['priority']); ?>
                    </span>
                </div>
                <div class="col-md-3">
                    <strong>Created:</strong> <?php echo formatDate($view_ticket['created_at']); ?>
                </div>
                <div class="col-md-3">
                    <strong>Last Update:</strong> <?php echo formatDate($view_ticket['updated_at']); ?>
                </div>
            </div>
            
            <div class="alert alert-light">
                <strong>Description:</strong><br>
                <?php echo nl2br($view_ticket['description']); ?>
            </div>
        </div>
        
        <!-- Conversation Thread -->
        <div class="dashboard-card mb-4">
            <h5 class="mb-3"><i class="fas fa-comments"></i> Conversation</h5>
            
            <?php if (mysqli_num_rows($responses_result) > 0): ?>
                <?php while ($response = mysqli_fetch_assoc($responses_result)): ?>
                    <div class="p-3 mb-2 <?php echo $response['response_type'] == 'admin' ? 'bg-light' : 'bg-primary bg-opacity-10'; ?>" style="border-radius: 8px;">
                        <div class="d-flex justify-content-between">
                            <strong>
                                <?php echo $response['name']; ?>
                                <?php if ($response['role'] == 'admin'): ?>
                                    <span class="badge bg-danger">Staff</span>
                                <?php endif; ?>
                            </strong>
                            <small class="text-muted"><?php echo formatDate($response['created_at']); ?></small>
                        </div>
                        <p class="mb-0 mt-2"><?php echo nl2br($response['message']); ?></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-muted">No responses yet. Waiting for support team...</p>
            <?php endif; ?>
        </div>
        
        <!-- Reply Form -->
        <?php if ($view_ticket['status'] != 'closed'): ?>
            <div class="form-custom">
                <h5 class="mb-3"><i class="fas fa-reply"></i> Add Reply</h5>
                <form method="POST" action="">
                    <input type="hidden" name="ticket_id" value="<?php echo $view_ticket['ticket_id']; ?>">
                    <div class="mb-3">
                        <textarea class="form-control" name="message" rows="4" placeholder="Type your message..." required></textarea>
                    </div>
                    <button type="submit" name="reply_ticket" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Reply
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary">
                <i class="fas fa-lock"></i> This ticket is closed. Contact support to reopen.
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- Create New Ticket -->
        <div class="form-custom mb-4">
            <h4 class="mb-3"><i class="fas fa-plus-circle"></i> Create New Ticket</h4>
            
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="subject" class="form-label">Subject *</label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="category" class="form-label">Category *</label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="account">Account Issue</option>
                            <option value="transaction">Transaction Issue</option>
                            <option value="loan">Loan Related</option>
                            <option value="technical">Technical Problem</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="priority" class="form-label">Priority *</label>
                        <select class="form-select" id="priority" name="priority" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description *</label>
                    <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                </div>
                
                <button type="submit" name="create_ticket" class="btn btn-primary">
                    <i class="fas fa-ticket-alt"></i> Submit Ticket
                </button>
            </form>
        </div>
        
        <!-- My Tickets -->
        <div class="dashboard-card">
            <h4 class="mb-3"><i class="fas fa-list"></i> My Support Tickets</h4>
            
            <?php if (mysqli_num_rows($tickets_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Ticket ID</th>
                                <th>Subject</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($ticket = mysqli_fetch_assoc($tickets_result)): ?>
                                <tr>
                                    <td><strong>#<?php echo $ticket['ticket_id']; ?></strong></td>
                                    <td><?php echo $ticket['subject']; ?></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($ticket['category']); ?></span></td>
                                    <td>
                                        <span class="badge bg-<?php echo $ticket['priority'] == 'high' ? 'danger' : ($ticket['priority'] == 'medium' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst($ticket['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $ticket['status'] == 'open' ? 'warning' : ($ticket['status'] == 'closed' ? 'secondary' : 'info'); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($ticket['created_at'])); ?></td>
                                    <td>
                                        <a href="support.php?view=<?php echo $ticket['ticket_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No support tickets yet. Create one if you need help!</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>