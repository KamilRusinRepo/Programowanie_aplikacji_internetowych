(() => {
    const modal = document.querySelector('[data-confirm-modal]');
    if (!modal) return;

    const titleNode = modal.querySelector('[data-confirm-title]');
    const messageNode = modal.querySelector('[data-confirm-message]');
    const cancelButton = modal.querySelector('[data-confirm-cancel]');
    const submitButton = modal.querySelector('[data-confirm-submit]');
    let pendingForm = null;

    const closeModal = () => {
        modal.hidden = true;
        pendingForm = null;
    };

    const openModal = (form) => {
        pendingForm = form;
        titleNode.textContent = form.dataset.confirmTitle || 'Are you sure?';
        messageNode.textContent = form.dataset.confirmMessage || 'This action cannot be undone.';
        submitButton.textContent = form.dataset.confirmAction || 'Delete';
        submitButton.className = `app-confirm-submit ${form.dataset.confirmActionClass || 'is-danger'}`.trim();
        modal.hidden = false;
        cancelButton.focus();
    };

    document.querySelectorAll('form[data-confirm-message]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                delete form.dataset.confirmed;
                return;
            }

            event.preventDefault();
            openModal(form);
        });
    });

    cancelButton?.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    submitButton?.addEventListener('click', () => {
        if (!pendingForm) return;
        pendingForm.dataset.confirmed = 'true';
        pendingForm.requestSubmit();
        closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
})();
