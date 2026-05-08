/* global MISurveyData */
let hasExistingData = Boolean(MISurveyData?.hasExistingData);
let surveyStarted = Boolean(MISurveyData?.surveyStarted);

function updateScore(section) {
    const checkboxes = document.querySelectorAll(`input[name="section${section}[]"]:checked`);
    const scoreElement = document.getElementById(`score${section}`);
    if (scoreElement) {
        scoreElement.textContent = checkboxes.length;
    }
}

function toggleQuestionStyle(checkbox) {
    const questionItem = checkbox.closest('.question-item');
    if (!questionItem) {
        return;
    }

    if (checkbox.checked) {
        questionItem.classList.add('checked');
        questionItem.style.animation = 'checkAnimation 0.3s ease';
    } else {
        questionItem.classList.remove('checked');
        questionItem.style.animation = '';
    }
}

function hideStickyNote(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const stickySubmit = document.getElementById('stickySubmit');
    const floatingBtn = document.getElementById('floatingActionBtn');

    if (stickySubmit && floatingBtn) {
        stickySubmit.classList.add('hidden');
        setTimeout(() => {
            floatingBtn.classList.add('show');
        }, 300);
    }
}

function showStickyNote() {
    const stickySubmit = document.getElementById('stickySubmit');
    const floatingBtn = document.getElementById('floatingActionBtn');

    if (stickySubmit && floatingBtn) {
        floatingBtn.classList.remove('show');
        setTimeout(() => {
            stickySubmit.classList.remove('hidden');
        }, 100);
    }
}

function applyExistingResponses(existingResponses) {
    Object.keys(existingResponses).forEach((sectionKey) => {
        const section = Number(sectionKey);
        const responses = existingResponses[sectionKey] || [];

        responses.forEach((response) => {
            const checkbox = document.querySelector(`input[name="section${section}[]"][value="${response}"]`);
            if (checkbox) {
                checkbox.checked = true;
                toggleQuestionStyle(checkbox);
            }
        });
    });

    Object.keys(existingResponses).forEach((sectionKey) => {
        updateScore(Number(sectionKey));
    });
}

function startSurvey() {
    const noDataCard = document.querySelector('.no-data-card');
    const surveyForm = document.getElementById('surveyForm');
    const surveyHeader = document.getElementById('surveyHeader');
    const stickyNote = document.getElementById('stickySubmit');

    if (noDataCard && surveyForm) {
        surveyStarted = true;
        noDataCard.style.transition = 'all 0.5s ease';
        noDataCard.style.opacity = '0';
        noDataCard.style.transform = 'translateY(-20px)';

        setTimeout(() => {
            noDataCard.style.display = 'none';

            if (surveyHeader) {
                surveyHeader.style.display = 'block';
                surveyHeader.style.opacity = '0';
                surveyHeader.style.transition = 'all 0.5s ease';
                setTimeout(() => {
                    surveyHeader.style.opacity = '1';
                }, 50);
            }

            surveyForm.style.display = 'block';
            surveyForm.style.opacity = '0';
            surveyForm.style.transform = 'translateY(20px)';
            surveyForm.style.transition = 'all 0.5s ease';

            if (stickyNote && !isMobileView) {
                stickyNote.style.display = 'block';
            }

            setTimeout(() => {
                surveyForm.style.opacity = '1';
                surveyForm.style.transform = 'translateY(0)';
            }, 50);
        }, 500);
    }
}

function formHasData() {
    const form = document.getElementById('surveyForm');
    if (!form) {
        return false;
    }
    const checkedBoxes = form.querySelectorAll('input[type="checkbox"]:checked');
    return checkedBoxes.length > 0;
}

function goBack() {
    const currentlyHasData = formHasData();
    if (hasExistingData || currentlyHasData) {
        window.location.href = 'survey_thankyou.php';
    } else {
        showNoDataCard();
    }
}

function showNoDataCard() {
    const noDataCard = document.querySelector('.no-data-card');
    const surveyForm = document.getElementById('surveyForm');
    const surveyHeader = document.getElementById('surveyHeader');
    const stickyNote = document.getElementById('stickySubmit');

    if (noDataCard && surveyForm) {
        if (surveyHeader) {
            surveyHeader.style.transition = 'all 0.5s ease';
            surveyHeader.style.opacity = '0';
            setTimeout(() => {
                surveyHeader.style.display = 'none';
            }, 500);
        }

        surveyForm.style.transition = 'all 0.5s ease';
        surveyForm.style.opacity = '0';
        surveyForm.style.transform = 'translateY(20px)';

        if (stickyNote) {
            stickyNote.style.display = 'none';
        }

        setTimeout(() => {
            surveyForm.style.display = 'none';
            noDataCard.style.display = 'block';
            noDataCard.style.opacity = '0';
            noDataCard.style.transform = 'translateY(-20px)';
            noDataCard.style.transition = 'all 0.5s ease';

            setTimeout(() => {
                noDataCard.style.opacity = '1';
                noDataCard.style.transform = 'translateY(0)';
            }, 50);
        }, 500);

        surveyStarted = false;
    }
}

function submitSurvey() {
    const surveyForm = document.getElementById('surveyForm');
    if (surveyForm) {
        surveyForm.submit();
    }
}

let currentSection = 1;
const totalSections = 9;
let isMobileView = false;
let resizeTimer;

function checkMobileView() {
    const wasMobile = isMobileView;
    isMobileView = window.innerWidth <= 768;

    if (wasMobile !== isMobileView) {
        if (isMobileView) {
            initMobileSectionNavigation();
        } else {
            showAllSections();
        }
    } else if (!isMobileView) {
        const stickySubmit = document.getElementById('stickySubmit');
        if (stickySubmit && (hasExistingData || surveyStarted)) {
            stickySubmit.style.display = 'block';
        }
    }
}

function initMobileSectionNavigation() {
    const sections = document.querySelectorAll('.section-card');
    if (!sections.length) {
        return;
    }

    sections.forEach((section, index) => {
        section.style.display = index === currentSection - 1 ? 'block' : 'none';
    });

    const stickySubmit = document.getElementById('stickySubmit');
    if (stickySubmit) {
        stickySubmit.style.display = 'none';
    }

    if (!document.getElementById('mobileNavigation')) {
        createMobileNavigation();
    }
    updateMobileNavigation();
}

function showAllSections() {
    const sections = document.querySelectorAll('.section-card');
    sections.forEach((section) => {
        section.style.display = 'block';
        section.classList.remove('active-section');
    });

    const stickySubmit = document.getElementById('stickySubmit');
    if (stickySubmit && (hasExistingData || surveyStarted)) {
        stickySubmit.style.display = 'block';
    }

    const mobileNav = document.getElementById('mobileNavigation');
    if (mobileNav) {
        mobileNav.remove();
    }
}

function createMobileNavigation() {
    const surveyForm = document.getElementById('surveyForm');
    if (!surveyForm) {
        return;
    }

    const navDiv = document.createElement('div');
    navDiv.id = 'mobileNavigation';
    navDiv.className = 'mobile-nav-bar';
    navDiv.innerHTML = `
        <div class="nav-progress">
            <div class="progress-label">Progress</div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" id="progressBarFill"></div>
            </div>
            <div class="progress-indicators" id="progressIndicators"></div>
            <div class="progress-count">
                <span id="currentSectionNum">${currentSection}</span>/${totalSections}
                <span class="progress-percentage" id="progressPercentage"></span>
            </div>
        </div>
        <div class="nav-buttons">
            <button type="button" class="nav-btn back-btn" onclick="previousSection()" id="backBtn">
                <i class="fas fa-arrow-left"></i>
                <span>Back</span>
            </button>
            <button type="button" class="nav-btn next-btn" onclick="nextSection()">
                <span id="nextBtnText">Next</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    `;

    surveyForm.appendChild(navDiv);
}

function updateMobileNavigation() {
    const sectionNum = document.getElementById('currentSectionNum');
    const btnText = document.getElementById('nextBtnText');
    const btnIcon = document.querySelector('.next-btn i');
    const backBtn = document.getElementById('backBtn');
    const nextBtn = document.querySelector('.next-btn');
    const progressBarFill = document.getElementById('progressBarFill');
    const progressPercentage = document.getElementById('progressPercentage');
    const progressIndicators = document.getElementById('progressIndicators');

    if (sectionNum) {
        sectionNum.textContent = String(currentSection);
    }

    if (progressBarFill) {
        const percentage = Math.round((currentSection / totalSections) * 100);
        progressBarFill.style.width = `${percentage}%`;
    }

    if (progressPercentage) {
        const percentage = Math.round((currentSection / totalSections) * 100);
        progressPercentage.textContent = `(${percentage}%)`;
    }

    if (progressIndicators) {
        let indicatorsHTML = '';
        for (let i = 1; i <= totalSections; i++) {
            if (i < currentSection) {
                indicatorsHTML += sectionHasAnswers(i)
                    ? '<span class="section-indicator completed">✓</span>'
                    : '<span class="section-indicator incomplete">✕</span>';
            } else if (i === currentSection) {
                indicatorsHTML += '<span class="section-indicator current">●</span>';
            } else {
                indicatorsHTML += '<span class="section-indicator pending">○</span>';
            }
        }
        progressIndicators.innerHTML = indicatorsHTML;
    }

    if (btnText && btnIcon) {
        if (currentSection === totalSections) {
            btnText.textContent = 'Finish';
            btnIcon.className = 'fas fa-check';
            if (nextBtn) {
                const hasAnswers = hasAnsweredQuestions();
                nextBtn.disabled = !hasAnswers;
                nextBtn.style.opacity = hasAnswers ? '1' : '0.5';
                nextBtn.style.cursor = hasAnswers ? 'pointer' : 'not-allowed';
                nextBtn.title = hasAnswers ? 'Submit survey' : 'Please answer at least one question';
            }
        } else {
            btnText.textContent = 'Next';
            btnIcon.className = 'fas fa-arrow-right';
            if (nextBtn) {
                nextBtn.disabled = false;
                nextBtn.style.opacity = '1';
                nextBtn.style.cursor = 'pointer';
                nextBtn.title = 'Go to next section';
            }
        }
    }

    if (backBtn) {
        backBtn.disabled = currentSection === 1;
    }
}

function nextSection() {
    if (currentSection < totalSections) {
        currentSection++;
        showSection(currentSection);
    } else if (hasAnsweredQuestions()) {
        submitSurvey();
    } else {
        alert('Please answer at least one question before submitting the survey.');
    }
}

function hasAnsweredQuestions() {
    const checkboxes = document.querySelectorAll('input[type="checkbox"]:checked');
    return checkboxes.length > 0;
}

function sectionHasAnswers(sectionNumber) {
    const sections = document.querySelectorAll('.section-card');
    if (sections[sectionNumber - 1]) {
        const checkboxes = sections[sectionNumber - 1].querySelectorAll('input[type="checkbox"]:checked');
        return checkboxes.length > 0;
    }
    return false;
}

function previousSection() {
    if (currentSection > 1) {
        currentSection--;
        showSection(currentSection);
    }
}

function showSection(sectionNum) {
    const sections = document.querySelectorAll('.section-card');
    sections.forEach((section, index) => {
        section.style.display = index === sectionNum - 1 ? 'block' : 'none';
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
    updateMobileNavigation();
}

window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(checkMobileView, 250);
});

document.addEventListener('change', (event) => {
    if (event.target.type === 'checkbox' && isMobileView) {
        updateMobileNavigation();
    }
});

function handleStickySubmitVisibility() {
    const stickySubmit = document.getElementById('stickySubmit');
    if (!stickySubmit) {
        return;
    }

    if (isMobileView) {
        stickySubmit.style.display = 'none';
    } else if (hasExistingData || surveyStarted) {
        stickySubmit.style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (MISurveyData?.existingResponses && Object.keys(MISurveyData.existingResponses).length > 0) {
        applyExistingResponses(MISurveyData.existingResponses);
    }

    checkMobileView();
    handleStickySubmitVisibility();
});
