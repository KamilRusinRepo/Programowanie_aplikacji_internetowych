const statsPeriod = document.querySelector('[data-stats-period]');
const statsDataNode = document.querySelector('[data-stats-overview-json]');
const statValues = {
    mastered: document.querySelector('[data-stat-value="mastered"]'),
    studyTime: document.querySelector('[data-stat-value="studyTime"]'),
    accuracy: document.querySelector('[data-stat-value="accuracy"]'),
};

let statsData = {};

try {
    statsData = JSON.parse(statsDataNode?.textContent || '{}');
} catch (error) {
    statsData = {};
}

const setPeriod = (period) => {
    const selected = statsData[period] || statsData.all || {};

    if (statValues.mastered) statValues.mastered.textContent = selected.mastered || '0';
    if (statValues.studyTime) statValues.studyTime.textContent = selected.studyTime || '0m';
    if (statValues.accuracy) statValues.accuracy.textContent = selected.accuracy || '0%';

    statsPeriod?.querySelectorAll('[data-period]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.period === period);
    });
};

statsPeriod?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-period]');
    if (!button) return;

    setPeriod(button.dataset.period || 'all');
});

setPeriod('all');
