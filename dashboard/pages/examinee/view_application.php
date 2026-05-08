<?php
// View Application Status - Examinee page
// Loaded by layout.php - session/db already set up

if (!defined('IN_LAYOUT')) die('Direct access not allowed');

require_once __DIR__ . '/../../classes/EntranceExam.php';
require_once __DIR__ . '/../../classes/EntranceExamResults.php';

$entrance_exam = new EntranceExam($db);
$exam_results = new EntranceExamResults($db);

// Get user's applications
$applications = $entrance_exam->getByUserId($uid);
?>

<h1 class="text-xl font-bold text-primary mb-5"><i class="fas fa-clipboard-list mr-2"></i>My Entrance Exam Applications</h1>

<?php if($applications->rowCount() > 0): ?>
    <?php while($app = $applications->fetch(PDO::FETCH_ASSOC)): ?>
        <div class="bg-white rounded-xl shadow-sm p-5 mb-5">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h5 class="text-base font-bold text-gray-800 mb-1">Entrance Exam Application</h5>
                    <small class="text-gray-500">Applied on <?= date('F j, Y', strtotime($app['created_at'])) ?></small>
                </div>
                <?php
                $status_class = '';
                $status_icon = '';
                $display_status = ($app['status'] === 'pending') ? 'confirmed' : $app['status'];
                $status_label = $display_status;
                switch($display_status) {
                    case 'confirmed': 
                        $status_class = 'bg-blue-500 text-white'; 
                        $status_icon = 'fa-check-circle';
                        break;
                    case 'awaiting_results': 
                        $status_class = 'bg-purple-500 text-white'; 
                        $status_icon = 'fa-hourglass-half';
                        break;
                    case 'completed': 
                        $status_class = 'bg-green-500 text-white'; 
                        $status_icon = 'fa-certificate';
                        break;
                    case 'cancelled': 
                        $status_class = 'bg-red-500 text-white'; 
                        $status_icon = 'fa-times-circle';
                        break;
                    case 'missed': 
                        $status_class = 'bg-gray-500 text-white'; 
                        $status_icon = 'fa-user-times';
                        break;
                }
                ?>
                <span class="px-4 py-2 rounded-full text-sm font-semibold <?= $status_class ?>">
                    <i class="fas <?= $status_icon ?> mr-2"></i><?= ucfirst(str_replace('_', ' ', $status_label)) ?>
                </span>
            </div>
            
            <div class="grid md:grid-cols-3 gap-4 mb-4">
                <div>
                    <div class="text-xs text-gray-500 mb-1">Grade Level</div>
                    <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($app['grade_level_applying']) ?></div>
                </div>
                <?php 
                $is_college = stripos($app['grade_level_applying'], 'college') !== false || 
                             stripos($app['grade_level_applying'], 'year') !== false;
                ?>
                <?php if($is_college && !empty($app['preferred_program'])): ?>
                <div>
                    <div class="text-xs text-gray-500 mb-1">Preferred Program</div>
                    <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($app['preferred_program']) ?></div>
                </div>
                <?php endif; ?>
                <div>
                    <div class="text-xs text-gray-500 mb-1">Previous School</div>
                    <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($app['previous_school']) ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 mb-1">Exam Date</div>
                    <div class="text-sm font-semibold text-gray-800"><?= date('M j, Y', strtotime($app['preferred_date'])) ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 mb-1">Exam Time</div>
                    <div class="text-sm font-semibold text-gray-800"><?= date('g:i A', strtotime($app['preferred_time'])) ?></div>
                </div>
                <?php if($app['confirmed_at']): ?>
                <div>
                    <div class="text-xs text-gray-500 mb-1">Confirmed On</div>
                    <div class="text-sm font-semibold text-gray-800"><?= date('M j, Y', strtotime($app['confirmed_at'])) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <?php if($app['status'] == 'completed' && $app['exam_result'] != 'pending'): ?>
                <?php
                // Get detailed OLSAT results
                $olsat_data = $exam_results->getResultsByAppointmentId($app['id']);
                ?>
                
                <?php if($olsat_data && !empty($olsat_data['total_score'])): ?>
                    <!-- OLSAT Results Display -->
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 rounded-lg p-4 mt-4">
                        <div class="flex justify-between items-center mb-3">
                            <h6 class="text-green-700 font-bold text-sm"><i class="fas fa-certificate mr-2"></i>OLSAT Entrance Exam Results</h6>
                            <a href="layout.php?page=view_exam_results" class="px-3 py-1 bg-primary text-white rounded-lg text-xs font-semibold hover:bg-primary-dark transition-colors">
                                <i class="fas fa-eye mr-1"></i>View Official Certificate
                            </a>
                        </div>
                        
                        <div class="grid md:grid-cols-3 gap-3 mb-3">
                            <div class="text-center p-3 bg-white rounded-lg shadow-sm">
                                <h4 class="text-primary text-xl font-bold mb-1"><?= $olsat_data['total_score'] ?>/<?= $olsat_data['total_items'] ?: 72 ?></h4>
                                <small class="text-gray-500 text-xs">Total Score</small>
                            </div>
                            <div class="text-center p-3 bg-white rounded-lg shadow-sm">
                                <h4 class="text-blue-600 text-xl font-bold mb-1"><?= $olsat_data['stanine_score'] ?></h4>
                                <small class="text-gray-500 text-xs">Stanine</small>
                            </div>
                            <div class="text-center p-3 <?= !empty($olsat_data['qualified_grade']) ? 'bg-green-500' : 'bg-red-500' ?> text-white rounded-lg shadow-sm">
                                <?php if(!empty($olsat_data['qualified_grade'])): ?>
                                    <h6 class="text-sm font-bold mb-1">QUALIFIED</h6>
                                    <small class="text-xs"><?= htmlspecialchars($olsat_data['qualified_grade']) ?></small>
                                <?php else: ?>
                                    <h6 class="text-sm font-bold mb-1">NOT QUALIFIED</h6>
                                    <small class="text-xs">Try again next time</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="grid md:grid-cols-2 gap-4 text-sm">
                            <div class="bg-white rounded-lg p-3">
                                <h6 class="text-gray-600 font-semibold mb-2 text-xs">Verbal Abilities</h6>
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-500 text-xs">Verbal Comprehension:</span>
                                    <span class="font-semibold"><?= $olsat_data['verbal_comprehension_score'] ?: 'N/A' ?></span>
                                </div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-500 text-xs">Verbal Reasoning:</span>
                                    <span class="font-semibold"><?= $olsat_data['verbal_reasoning_score'] ?: 'N/A' ?></span>
                                </div>
                                <div class="flex justify-between pt-2 border-t border-gray-200">
                                    <span class="font-bold text-xs">Total Verbal:</span>
                                    <span class="font-bold"><?= $olsat_data['verbal_abilities_score'] ?: 'N/A' ?></span>
                                </div>
                            </div>
                            <div class="bg-white rounded-lg p-3">
                                <h6 class="text-gray-600 font-semibold mb-2 text-xs">Non-Verbal Abilities</h6>
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-500 text-xs">Figural Reasoning:</span>
                                    <span class="font-semibold"><?= $olsat_data['figural_reasoning_score'] ?: 'N/A' ?></span>
                                </div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-500 text-xs">Quantitative Reasoning:</span>
                                    <span class="font-semibold"><?= $olsat_data['quantitative_reasoning_score'] ?: 'N/A' ?></span>
                                </div>
                                <div class="flex justify-between pt-2 border-t border-gray-200">
                                    <span class="font-bold text-xs">Total Non-Verbal:</span>
                                    <span class="font-bold"><?= $olsat_data['nonverbal_abilities_score'] ?: 'N/A' ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if($olsat_data['interpretation']): ?>
                            <div class="mt-3 p-3 bg-white rounded-lg">
                                <strong class="text-xs">Interpretation:</strong>
                                <p class="text-gray-600 text-xs mt-1"><?= nl2br(htmlspecialchars($olsat_data['interpretation'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if($app['status'] == 'confirmed' || $app['status'] == 'pending'): ?>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h6 class="font-bold text-blue-800 mb-1 text-sm">Appointment Confirmed</h6>
                            <p class="text-blue-700 text-sm mb-1">Your entrance exam appointment has been confirmed for <strong><?= date('F j, Y', strtotime($app['preferred_date'])) ?> at <?= date('g:i A', strtotime($app['preferred_time'])) ?></strong>.</p>
                            <small class="text-blue-600 text-xs">Please arrive 15 minutes before your scheduled time. Bring a valid ID and writing materials.</small>
                        </div>
                    </div>
                </div>
            <?php elseif($app['status'] == 'awaiting_results'): ?>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mt-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-600">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div>
                            <h6 class="font-bold text-purple-800 mb-1 text-sm">Exam Completed - Results Pending</h6>
                            <p class="text-purple-700 text-sm mb-1">You have successfully completed the entrance exam on <strong><?= date('F j, Y', strtotime($app['preferred_date'])) ?></strong>.</p>
                            <small class="text-purple-600 text-xs">Your results are being processed and will be released soon. You will be notified once they are available.</small>
                        </div>
                    </div>
                </div>
            <?php elseif($app['status'] == 'missed'): ?>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mt-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-600">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div>
                            <h6 class="font-bold text-gray-800 mb-1 text-sm">Appointment Missed</h6>
                            <p class="text-gray-700 text-sm mb-1">You missed your scheduled entrance exam appointment on <strong><?= date('F j, Y', strtotime($app['preferred_date'])) ?></strong>.</p>
                            <small class="text-gray-600 text-xs">Please contact the guidance office to reschedule or book a new appointment.</small>
                        </div>
                    </div>
                </div>
            <?php elseif($app['status'] == 'cancelled'): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mt-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center text-red-600">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div>
                            <h6 class="font-bold text-red-800 mb-1 text-sm">Appointment Cancelled</h6>
                            <p class="text-red-700 text-sm mb-1">Your entrance exam appointment scheduled for <strong><?= date('F j, Y', strtotime($app['preferred_date'])) ?></strong> has been cancelled.</p>
                            <?php if($app['remarks']): ?>
                                <small class="text-red-600 text-xs"><strong>Reason:</strong> <?= htmlspecialchars($app['remarks']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm p-10 text-center">
        <i class="fas fa-clipboard-list text-6xl text-gray-200 mb-4"></i>
        <h5 class="text-lg font-bold text-gray-800 mb-2">No Applications Found</h5>
        <p class="text-gray-500 mb-5">You haven't submitted any entrance exam applications yet.</p>
        <a href="layout.php?page=book_exam" class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors font-semibold">
            <i class="fas fa-calendar-plus"></i>Book Entrance Exam
        </a>
    </div>
<?php endif; ?>

<div class="mt-5">
    <a href="layout.php?page=dashboard" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-semibold">
        <i class="fas fa-arrow-left"></i>Back to Dashboard
    </a>
</div>
