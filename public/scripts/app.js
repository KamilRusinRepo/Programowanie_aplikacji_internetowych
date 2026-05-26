const togglePasswordVisibility = (event) => {
    const toggleButton = event.target.closest('[data-password-toggle]');

    if (!toggleButton) return;

    const container = toggleButton.closest('.password-field') || toggleButton.closest('label');
    const field = container ? container.querySelector('[data-password-field]') : null;

    if (!field) return;

    // Toggle the input type
    field.type = field.type === 'password' ? 'text' : 'password';

    // Swap icon if present
    const img = toggleButton.querySelector('.password-icon');
    if (img) {
        img.src = field.type === 'password' ? '/icons/eye.svg' : '/icons/eye_off.svg';
        img.alt = field.type === 'password' ? 'Show password' : 'Hide password';
    }
};

const clearAuthErrors = (form) => {
    form.querySelectorAll('[data-field-error]').forEach((node) => {
        node.textContent = '';
    });

    form.querySelectorAll('.input-invalid').forEach((node) => {
        node.classList.remove('input-invalid');
    });

    const generalError = form.closest('.auth-form-panel')?.querySelector('[data-form-error]');

    if (generalError) {
        generalError.textContent = '';
    }
};

const renderAuthErrors = (form, errors) => {
    const generalError = form.closest('.auth-form-panel')?.querySelector('[data-form-error]');

    if (generalError) {
        generalError.textContent = errors.general || '';
    }

    Object.entries(errors).forEach(([fieldName, message]) => {
        if (fieldName === 'general') {
            return;
        }

        const errorNode = form.querySelector(`[data-field-error="${fieldName}"]`);
        const input = form.querySelector(`[name="${fieldName}"]`);

        if (errorNode) {
            errorNode.textContent = message;
        }

        if (input) {
            input.classList.add('input-invalid');
        }
    });
};

const handleAuthSubmit = async (event) => {
    const form = event.target.closest('[data-auth-form]');

    if (!form) {
        return;
    }

    event.preventDefault();
    clearAuthErrors(form);

    const response = await fetch(form.action, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: new FormData(form),
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok || !payload.success) {
        renderAuthErrors(form, payload.errors || { general: 'Request failed.' });
        return;
    }

    window.location.assign(payload.redirect || '/dashboard');
};

document.addEventListener('click', togglePasswordVisibility);
document.addEventListener('submit', handleAuthSubmit);