const toggleAddButtons = document.querySelectorAll('[data-toggle-add-card]');
    const addForm = document.querySelector('[data-add-card-form]');
    const cancelAdd = document.querySelector('[data-cancel-add]');
    const deleteForm = document.querySelector('[data-confirm-delete]');
    const cardDeleteForms = document.querySelectorAll('[data-confirm-card-delete]');
    const editButtons = document.querySelectorAll('.card-icon-btn[title="Edit"]');
    const cardForm = document.querySelector('.deck-card-form');
    const cardFormTitle = document.querySelector('[data-card-form-title]');
    const defaultAction = cardForm ? cardForm.dataset.defaultAction : '';

    if (toggleAddButtons.length && addForm) {
        toggleAddButtons.forEach((toggleAdd) => toggleAdd.addEventListener('click', () => {
            addForm.hidden = false;
            addForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (cardForm && defaultAction) {
                cardForm.action = defaultAction;
            }
            if (cardFormTitle) {
                cardFormTitle.textContent = 'Add New Card';
            }
            if (cardForm) {
                cardForm.reset();
            }
        }));
    }

    if (cancelAdd && addForm) {
        cancelAdd.addEventListener('click', () => {
            addForm.hidden = true;
        });
    }

    editButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!addForm || !cardForm) return;

            const cardId = button.dataset.cardId;
            if (cardId) {
                cardForm.action = `${defaultAction}/${cardId}`;
            }

            cardForm.querySelector('#front-question').value = button.dataset.cardFront || '';
            cardForm.querySelector('#example-sentence').value = button.dataset.cardExample || '';
            cardForm.querySelector('#image-url').value = button.dataset.cardImage || '';
            cardForm.querySelector('#answer').value = button.dataset.cardAnswer || '';
            cardForm.querySelector('#translated-example').value = button.dataset.cardTranslated || '';

            if (cardFormTitle) {
                cardFormTitle.textContent = 'Edit Card';
            }

            addForm.hidden = false;
            addForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    if (deleteForm) {
        deleteForm.addEventListener('submit', (event) => {
            const ok = window.confirm('Are you sure you want to delete this deck? This cannot be undone.');
            if (!ok) {
                event.preventDefault();
            }
        });
    }

    cardDeleteForms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            const ok = window.confirm('Delete this card?');
            if (!ok) {
                event.preventDefault();
            }
        });
    });
