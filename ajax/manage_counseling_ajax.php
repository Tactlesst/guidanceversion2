<?php
/**
 * AJAX Endpoint: Manage Counseling Appointments
 * 
 * Provides paginated, filtered appointment list for counselors
 * Returns HTML fragments for dynamic loading
 * 
 * @package GuidanceSystem
 * @version 2.0
 */

session_start();
require_once '../config/database.php';
require_once '../classes/CounselingAppointment.php';

header('Content-Type: application/json');

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['counselor', 'admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // Initialize database and counseling class
    $database = new Database();
    $db = $database->getConnection();
    $counseling = new CounselingAppointment($db);
    
    // Pagination settings
    $records_per_page = 10;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $records_per_page;
    
    // Get filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $grade_filter = isset($_GET['grade_level']) ? trim($_GET['grade_level']) : '';
    $department_filter = isset($_GET['department']) ? trim($_GET['department']) : '';
    $program_filter = isset($_GET['program']) ? trim($_GET['program']) : '';
    $strand_filter = isset($_GET['strand']) ? trim($_GET['strand']) : '';
    
    // Get total count and paginated results
    $total_appointments = $counseling->getTotalAppointmentsCount(
        $search, 
        $grade_filter, 
        $department_filter, 
        $program_filter, 
        $strand_filter
    );
    
    $total_pages = ceil($total_appointments / $records_per_page);
    
    $appointments = $counseling->getAllAppointmentsPaginated(
        $offset, 
        $records_per_page, 
        $search, 
        $grade_filter, 
        $department_filter, 
        $program_filter, 
        $strand_filter
    );
    
    // Generate table HTML
    ob_start();
    ?>
    
    <?php if ($total_appointments > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <th style="width: 20%;">Student</th>
                        <th style="width: 12%;">Date & Time</th>
                        <th style="width: 8%;">Status</th>
                        <th style="width: 8%;">Priority</th>
                        <th style="width: 15%;">Contact Type</th>
                        <th style="width: 20%;">Concern</th>
                        <th style="width: 10%;">Requested</th>
                        <th style="width: 5%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($app = $appointments->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr class="urgency-<?php echo htmlspecialchars($app['urgency_level']); ?>">
                            <td>
                                <div>
                                    <strong class="d-block"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></strong>
                                    <?php if (!empty($app['grade_level'])): ?>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($app['grade_level']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($app['program'])): ?>
                                        <small class="text-primary"><?php echo htmlspecialchars($app['program']); ?></small>
                                    <?php elseif (!empty($app['strand'])): ?>
                                        <small class="text-primary"><?php echo htmlspecialchars($app['strand']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-nowrap">
                                    <div><?php echo date('M j', strtotime($app['appointment_date'])); ?></div>
                                    <small class="text-muted"><?php echo date('g:i A', strtotime($app['appointment_time'])); ?></small>
                                </div>
                            </td>
                            <td>
                                <?php
                                $status_colors = [
                                    'pending' => 'warning',
                                    'confirmed' => 'info',
                                    'completed' => 'success',
                                    'cancelled' => 'danger',
                                    'missed' => 'secondary'
                                ];
                                $color = $status_colors[$app['status']] ?? 'secondary';
                                ?>
                                <span class="badge badge-sm bg-<?php echo $color; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $urgency_colors = [
                                    'urgent' => 'danger',
                                    'high' => 'warning',
                                    'medium' => 'info',
                                    'low' => 'success'
                                ];
                                $color = $urgency_colors[$app['urgency_level']] ?? 'secondary';
                                ?>
                                <span class="badge badge-sm bg-<?php echo $color; ?>">
                                    <?php echo ucfirst($app['urgency_level']); ?>
                                </span>
                            </td>
                            <td>
                                <div>
                                    <span class="badge badge-sm bg-<?php echo ($app['nature_of_contact'] === 'walk-in' || empty($app['nature_of_contact'])) ? 'primary' : 'info'; ?>">
                                        <?php echo !empty($app['nature_of_contact']) ? ucwords(str_replace(['_', '-'], ' ', $app['nature_of_contact'])) : 'Walk In'; ?>
                                    </span>
                                    <?php if (!empty($app['original_appointment_id'])): ?>
                                        <span class="badge badge-sm bg-warning mt-1">Rescheduled</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <span class="badge bg-light text-dark mb-1"><?php echo ucwords(str_replace('_', ' ', $app['concern_type'])); ?></span>
                                    <div class="small text-muted" title="<?php echo htmlspecialchars($app['concern_description']); ?>">
                                        <?php 
                                        $desc = htmlspecialchars($app['concern_description']);
                                        echo strlen($desc) > 40 ? substr($desc, 0, 40) . '...' : $desc; 
                                        ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo date('M j, Y', strtotime($app['created_at'])); ?></small>
                                <?php if (!empty($app['confirmed_at'])): ?>
                                    <div><i class="fas fa-check text-success" title="Confirmed"></i></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewModal<?php echo $app['id']; ?>"
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">No Appointments Found</h4>
            <p class="text-muted">No appointments match your current filters.</p>
        </div>
    <?php endif; ?>
    
    <?php
    $table_html = ob_get_clean();
    
    // Generate pagination HTML
    ob_start();
    ?>
    
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Appointments pagination" class="mt-4">
            <ul class="pagination justify-content-center">
                <!-- Previous Page -->
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="#" data-page="<?php echo $page - 1; ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                </li>
                
                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="1">1</a>
                    </li>
                    <?php if ($start_page > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                    </li>
                <?php endif; ?>
                
                <!-- Next Page -->
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="#" data-page="<?php echo $page + 1; ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
    
    <?php
    $pagination_html = ob_get_clean();
    
    // Generate summary HTML
    ob_start();
    ?>
    
    <?php if (!empty($search) || !empty($grade_filter) || !empty($program_filter) || !empty($strand_filter)): ?>
        <div class="mb-2">
            <span class="badge bg-info">
                <i class="fas fa-filter me-1"></i>
                Filtered Results: <?php echo $total_appointments; ?> appointments
            </span>
            <?php if (!empty($search)): ?>
                <span class="badge bg-secondary ms-1">
                    <i class="fas fa-search me-1"></i><?php echo htmlspecialchars($search); ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($grade_filter)): ?>
                <span class="badge bg-success ms-1">
                    <i class="fas fa-layer-group me-1"></i><?php echo htmlspecialchars($grade_filter); ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($program_filter)): ?>
                <span class="badge bg-warning ms-1">
                    <i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($program_filter); ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($strand_filter)): ?>
                <span class="badge bg-danger ms-1">
                    <i class="fas fa-stream me-1"></i><?php echo htmlspecialchars($strand_filter); ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <p class="text-muted mb-0">
        Showing <?php echo ($page - 1) * $records_per_page + 1; ?> to 
        <?php echo min($page * $records_per_page, $total_appointments); ?> of 
        <?php echo $total_appointments; ?> appointments
    </p>
    <div>
        <small class="text-muted">Page <?php echo $page; ?> of <?php echo $total_pages; ?></small>
    </div>
    
    <?php
    $summary_html = ob_get_clean();
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'table_html' => $table_html,
        'pagination_html' => $pagination_html,
        'summary_html' => $summary_html,
        'total_count' => $total_appointments,
        'current_page' => $page,
        'total_pages' => $total_pages
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in manage_counseling_ajax: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
    
} catch (Exception $e) {
    error_log("Error in manage_counseling_ajax: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred'
    ]);
}
