(function () {
    const typeSelect = document.querySelector('[data-explore-deck-type]');
    const targetField = document.querySelector('[data-explore-target-language]');
    if (!typeSelect || !targetField) return;

    const form = typeSelect.closest('.explore-filter-bar');
    const targetSelect = targetField.querySelector('select');

    const syncTargetVisibility = () => {
        const isLanguage = typeSelect.value === 'language';
        targetField.classList.toggle('is-hidden', !isLanguage);
        if (form) {
            form.classList.toggle('has-target-language', isLanguage);
        }
        if (!isLanguage && targetSelect) {
            targetSelect.value = '';
        }
    };

    typeSelect.addEventListener('change', syncTargetVisibility);
    syncTargetVisibility();
})();
