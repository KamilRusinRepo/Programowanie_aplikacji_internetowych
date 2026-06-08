    const adminMain = document.querySelector('[data-admin-modal-state]');
    const userModal = document.querySelector('[data-user-modal]');
    const userForm = document.querySelector('[data-user-form]');
    const modalTitle = document.querySelector('[data-modal-title]');
    const modalUsername = document.querySelector('[data-modal-username]');
    const modalEmail = document.querySelector('[data-modal-email]');
    const modalPassword = document.querySelector('[data-modal-password]');
    const modalRole = document.querySelector('[data-modal-role]');
    const modalErrors = document.querySelectorAll('[data-modal-error]');
    let currentUserId = 0;
    let validationTimer = 0;

    const showModalErrors = (errors = {}) => {
        modalErrors.forEach((errorNode) => {
            const field = errorNode.dataset.modalError;
            errorNode.textContent = errors[field] || '';
        });
    };

    const readInitialModalState = () => {
        if (!adminMain) return null;
        try {
            return JSON.parse(adminMain.dataset.adminModalState || 'null');
        } catch (error) {
            return null;
        }
    };

    const validateIdentityLive = async () => {
        if (!modalUsername || !modalEmail) return;

        const username = modalUsername.value.trim();
        const email = modalEmail.value.trim();

        if (username === '' && email === '') {
            showModalErrors({});
            return;
        }

        const params = new URLSearchParams({
            id: String(currentUserId || 0),
            username,
            email,
        });

        try {
            const response = await fetch(`/admin/users/validate?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json();
            showModalErrors(payload.errors || {});
        } catch (error) {
            // Server-side validation still runs on submit.
        }
    };

    const scheduleIdentityValidation = () => {
        window.clearTimeout(validationTimer);
        validationTimer = window.setTimeout(validateIdentityLive, 300);
    };

    const openUserModal = (mode, user = {}) => {
        if (!userModal || !userForm) return;

        const isEdit = mode === 'edit';
        currentUserId = isEdit ? Number(user.id || 0) : 0;
        userForm.action = isEdit ? `/admin/users/${user.id}/edit` : '/admin/users';
        modalTitle.textContent = isEdit ? 'Edit User' : 'Add User';
        modalUsername.value = user.username || '';
        modalEmail.value = user.email || '';
        modalPassword.value = '';
        modalPassword.required = !isEdit;
        modalPassword.placeholder = isEdit ? 'Leave blank to keep current password' : '';
        modalRole.value = user.role === 'ADMIN' ? 'ADMIN' : 'USER';
        showModalErrors(user.errors || {});
        userModal.hidden = false;
        modalUsername.focus();
    };

    const closeUserModal = () => {
        if (userModal) userModal.hidden = true;
        currentUserId = 0;
        showModalErrors({});
    };

    modalUsername?.addEventListener('input', scheduleIdentityValidation);
    modalEmail?.addEventListener('input', scheduleIdentityValidation);

    document.querySelector('[data-add-user]')?.addEventListener('click', () => {
        openUserModal('add');
    });

    document.querySelectorAll('[data-edit-user]').forEach((button) => {
        button.addEventListener('click', () => {
            openUserModal('edit', {
                id: button.dataset.userId,
                username: button.dataset.userUsername,
                email: button.dataset.userEmail,
                role: button.dataset.userRole,
            });
        });
    });

    document.querySelectorAll('[data-close-user-modal]').forEach((button) => {
        button.addEventListener('click', closeUserModal);
    });

    userModal?.addEventListener('click', (event) => {
        if (event.target === userModal) {
            closeUserModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeUserModal();
        }
    });

    const initialModalState = readInitialModalState();
    if (initialModalState) {
        openUserModal(initialModalState.mode || 'add', initialModalState);
    }
