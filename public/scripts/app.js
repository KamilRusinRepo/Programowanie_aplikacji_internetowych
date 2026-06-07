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
        img.src = field.type === 'password' ? '/icons/login/eye.svg' : '/icons/login/eye_off.svg';
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

const setFieldError = (form, fieldName, message) => {
    const errorNode = form.querySelector(`[data-field-error="${fieldName}"]`);
    const input = form.querySelector(`[name="${fieldName}"]`);

    if (errorNode) {
        errorNode.textContent = message || '';
    }

    if (input) {
        input.classList.toggle('input-invalid', Boolean(message));
    }
};

let registerValidationTimer = null;

const validateRegisterRealtime = (form) => {
    const username = form.querySelector('[name="username"]')?.value.trim() || '';
    const email = form.querySelector('[name="email"]')?.value.trim() || '';
    const password = form.querySelector('[name="password"]')?.value || '';
    const confirmation = form.querySelector('[name="password_confirmation"]')?.value || '';

    if (password && confirmation) {
        setFieldError(form, 'password_confirmation', password === confirmation ? '' : 'Passwords do not match.');
    } else {
        setFieldError(form, 'password_confirmation', '');
    }

    window.clearTimeout(registerValidationTimer);
    registerValidationTimer = window.setTimeout(async () => {
        if (!username && !email) {
            setFieldError(form, 'username', '');
            setFieldError(form, 'email', '');
            return;
        }

        const params = new URLSearchParams({
            username,
            email,
            password,
            password_confirmation: confirmation,
        });

        const response = await fetch(`/register/validate?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json().catch(() => ({ errors: {} }));
        const errors = payload.errors || {};

        setFieldError(form, 'username', errors.username || '');
        setFieldError(form, 'email', errors.email || '');
        if (password && confirmation) {
            setFieldError(form, 'password_confirmation', errors.password_confirmation || '');
        }
    }, 250);
};

const handleAuthInput = (event) => {
    const form = event.target.closest('[data-auth-form]');
    if (!form || form.action.indexOf('/register') === -1) {
        return;
    }

    if (!['username', 'email', 'password', 'password_confirmation'].includes(event.target.name)) {
        return;
    }

    validateRegisterRealtime(form);
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
document.addEventListener('input', handleAuthInput);
