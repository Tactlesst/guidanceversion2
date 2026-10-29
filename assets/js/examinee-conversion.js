(() => {
    'use strict';
    const root = document.querySelector('.ec-workspace');
    if (!root) return;
    const search = root.querySelector('#ec-search');
    const clear = root.querySelector('#ec-clear-search');
    const candidates = [...root.querySelectorAll('[data-ec-search]')];
    function filterCandidates() {
        const query = search.value.trim().toLocaleLowerCase();
        let visible = 0;
        for (const candidate of candidates) {
            candidate.hidden = !candidate.dataset.ecSearch.includes(query);
            if (!candidate.hidden) visible++;
        }
        root.querySelector('#ec-no-match').hidden = visible !== 0;
        clear.hidden = !search.value;
        root.querySelector('#ec-search-count').textContent = visible + (visible === 1 ? ' examinee' : ' examinees') + (query ? ' matching your search' : ' available');
    }
    if (search) {
        search.addEventListener('input', filterCandidates);
        clear.addEventListener('click', () => { search.value = ''; filterCandidates(); search.focus(); });
    }
    const department = root.querySelector('#conversion-department');
    const config = root.querySelector('#conversion-academics');
    if (!department || !config) return;
    const academics = JSON.parse(config.textContent);
    const labels = { grade_level: 'grade / year level', program: 'college program', strand: 'SHS strand' };
    let initial = true;
    function updatePlacement() {
        const selected = academics[department.value];
        for (const key of Object.keys(labels)) {
            const select = root.querySelector('#conversion-' + key);
            const field = root.querySelector('#conversion-' + key + '-field');
            const values = selected?.[key] || [];
            const prompt = !selected ? 'Choose department first' : values.length ? 'Choose ' + labels[key] : 'No active options configured';
            select.replaceChildren(new Option(prompt, ''));
            for (const value of values) select.add(new Option(value, value));
            field.hidden = key !== 'grade_level' && !values.length;
            select.disabled = !values.length;
            select.required = !!values.length;
            if (initial && values.includes(select.dataset.saved)) select.value = select.dataset.saved;
        }
        const hasGrades = !!selected?.grade_level?.length;
        root.querySelector('#ec-configuration-note').hidden = !selected || hasGrades;
        root.querySelector('#ec-enroll-button').disabled = !hasGrades;
        initial = false;
    }
    department.addEventListener('change', updatePlacement);
    updatePlacement();
})();
