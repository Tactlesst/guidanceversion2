<?php
/**
 * Appointment Card Component
 * 
 * Reusable card component for displaying appointment details
 * Used in manage_appointments.php
 * 
 * @package GuidanceSystem
 * @version 2.0
 * @requires $app array with appointment data
 */

// Ensure $app variable exists
if (!isset($app) || !is_array($app)) {
    return;
}
?>

<div class="appointment-card status-<?php echo htmlspecialchars($app['status']); ?> urgency-<?php echo htmlspecialchars($app['urgency_level']); ?>">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">
                <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>
                <small class="text-muted">
                    (<?php echo htmlspecialchars($app['student_id']); ?> - <?php echo htmlspecialchars($app['grade_level']); ?><?php echo isset($app['section']) && !empty($app['section']) ? ' ' . htmlspecialchars($app['section']) : ''; ?>)
                </small>
            </h5>
        </div>
        <div>
            <?php
            $status_colors = [
                'pending' => 'warning',
                'confirmed' => 'success',
                'completed' => 'primary',
                'cancelled' => 'danger',
                'missed' => 'secondary'
            ];
            $status_color = $status_colors[$app['status']] ?? 'secondary';
            
            $urgency_colors = [
                'urgent' => 'danger',
                'high' => 'warning',
                'medium' => 'info',
                'low' => 'success'
            ];
            $urgency_color = $urgency_colors[$app['urgency_level']] ?? 'secondary';
            ?>
            <span class="badge bg-<?php echo $status_color; ?> me-2">
                <?php echo ucfirst($app['status']); ?>
            </span>
            <span class="badge bg-<?php echo $urgency_color; ?>">
                <?php echo ucfirst($app['urgency_level']); ?> Priority
            </span>
        </div>
    </div>
    
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <div class="appointment-details">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($app['appointment_date'])); ?></p>
                            <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($app['appointment_time'])); ?></p>
                            <p><strong>Concern Type:</strong> <?php echo ucwords(str_replace('_', ' ', $app['concern_type'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Urgency:</strong> <?php echo ucfirst($app['urgency_level']); ?></p>
                            <p><strong>Requested:</strong> <?php echo date('M j, Y g:i A', strtotime($app['created_at'])); ?></p>
                            <?php if (!empty($app['confirmed_by'])): ?>
                            <p><strong>Confirmed by:</strong> 
                                <?php echo isset($app['confirmed_by_name']) ? htmlspecialchars($app['confirmed_by_name'] . ' ' . $app['confirmed_by_lastname']) : 'Admin'; ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($app['original_appointment_id'])): ?>
                            <p><span class="badge bg-warning">Rescheduled</span></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($app['concern_description'])): ?>
                    <div class="mt-3">
                        <strong>Description:</strong>
                        <div class="bg-light p-3 rounded mt-2">
                            <?php echo nl2br(htmlspecialchars($app['concern_description'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($app['nature_of_contact'])): ?>
                    <div class="mt-2">
                        <strong>Contact Type:</strong> 
                        <span class="badge bg-info">
                            <?php echo ucwords(str_replace(['_', '-'], ' ', $app['nature_of_contact'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="d-flex flex-column appointment-actions">
                    <?php if ($app['status'] == 'pending'): ?>
                        <button class="btn btn-success btn-sm mb-2" 
                                onclick="confirmAction('confirm', <?php echo $app['id']; ?>, '<?php echo addslashes($app['first_name'] . ' ' . $app['last_name']); ?>')">
                            <i class="fas fa-check me-1"></i>Confirm
                        </button>
                        <button class="btn btn-warning btn-sm mb-2" 
                                onclick="rescheduleAppointment(<?php echo $app['id']; ?>)">
                            <i class="fas fa-calendar-alt me-1"></i>Reschedule
                        </button>
                        <button class="btn btn-danger btn-sm" 
                                onclick="confirmAction('cancel', <?php echo $app['id']; ?>, '<?php echo addslashes($app['first_name'] . ' ' . $app['last_name']); ?>')">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                    <?php elseif ($app['status'] == 'confirmed'): ?>
                        <button class="btn btn-primary btn-sm mb-2" 
                                onclick="confirmAction('complete', <?php echo $app['id']; ?>, '<?php echo addslashes($app['first_name'] . ' ' . $app['last_name']); ?>')">
                            <i class="fas fa-check-double me-1"></i>Mark Complete
                        </button>
                        <button class="btn btn-warning btn-sm mb-2" 
                                onclick="rescheduleAppointment(<?php echo $app['id']; ?>)">
                            <i class="fas fa-calendar-alt me-1"></i>Reschedule
                        </button>
                        <button class="btn btn-danger btn-sm" 
                                onclick="confirmAction('cancel', <?php echo $app['id']; ?>, '<?php echo addslashes($app['first_name'] . ' ' . $app['last_name']); ?>')">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                    <?php else: ?>
                        <div class="text-center">
                            <i class="fas fa-info-circle text-muted fa-2x mb-2"></i>
                            <p class="text-muted mb-0">No actions available</p>
                            <small class="text-muted">
                                <?php 
                                if ($app['status'] == 'completed') {
                                    echo 'Appointment completed';
                                } elseif ($app['status'] == 'cancelled') {
                                    echo 'Appointment cancelled';
                                } elseif ($app['status'] == 'missed') {
                                    echo 'Appointment missed';
                                }
                                ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
