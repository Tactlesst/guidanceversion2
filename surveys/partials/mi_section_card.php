<div class="section-card">
    <div class="section-header">
        <div class="intelligence-type">
            <div class="intelligence-icon">
                <i class="<?php echo htmlspecialchars($section['icon']); ?>"></i>
            </div>
            <div>
                <div>Section <?php echo (int) $sectionNumber; ?> - <?php echo htmlspecialchars($section['name']); ?></div>
                <div class="section-description"><?php echo htmlspecialchars($section['description']); ?></div>
            </div>
        </div>
        <div class="score-container">
            <div class="score-label">Score</div>
            <div class="score-display"><span id="score<?php echo (int) $sectionNumber; ?>">0</span>/10</div>
        </div>
    </div>
    <div class="section-content">
        <div class="questions-grid">
            <?php foreach ($section['questions'] as $questionIndex => $questionText): ?>
                <div class="question-item">
                    <label class="question-checkbox">
                        <input type="checkbox" name="section<?php echo (int) $sectionNumber; ?>[]" value="<?php echo (int) ($questionIndex + 1); ?>" onchange="updateScore(<?php echo (int) $sectionNumber; ?>); toggleQuestionStyle(this)">
                        <span class="question-text"><?php echo htmlspecialchars($questionText); ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
