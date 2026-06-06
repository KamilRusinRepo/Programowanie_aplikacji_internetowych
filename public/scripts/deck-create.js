    const deckTypeInputs = document.querySelectorAll('input[name="deck_type"]');
    const deckTypeToggle = document.querySelector('.deck-type-toggle');
    const targetLanguageField = document.querySelector('[data-target-language]');
    const languageRow = document.querySelector('[data-language-row]');

    const updateLanguageFields = () => {
        const selected = document.querySelector('input[name="deck_type"]:checked');
        if (!selected || !targetLanguageField) return;

        deckTypeToggle?.classList.toggle('is-language', selected.value === 'language');

        if (selected.value === 'general') {
            targetLanguageField.style.display = 'none';
            if (languageRow) {
                languageRow.classList.add('is-single');
            }
        } else {
            targetLanguageField.style.display = 'block';
            if (languageRow) {
                languageRow.classList.remove('is-single');
            }
        }
    };

    deckTypeInputs.forEach((input) => {
        input.addEventListener('change', updateLanguageFields);
    });

    updateLanguageFields();
