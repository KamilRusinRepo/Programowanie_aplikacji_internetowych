const settingsMain = document.querySelector('[data-password-modal-open]');
const passwordModal = document.querySelector('[data-password-modal]');
const passwordForm = passwordModal?.querySelector('form');
const passwordInput = passwordForm?.querySelector('[name="password"]');
const passwordConfirmationInput = passwordForm?.querySelector('[name="password_confirmation"]');
const themeSegment = document.querySelector('[data-theme-segment]');
const themeButtons = document.querySelectorAll('[data-theme-option]');
const languageToggle = document.querySelector('[data-language-toggle]');

const syncThemeButtons = () => {
    const activeTheme = window.FlashMindTheme?.getTheme?.() || 'light';

    themeSegment?.classList.toggle('is-dark', activeTheme === 'dark');
    themeButtons.forEach((button) => {
        button.classList.toggle('is-active', button.dataset.themeOption === activeTheme);
    });
};

themeButtons.forEach((button) => {
    button.addEventListener('click', () => {
        const theme = button.dataset.themeOption === 'dark' ? 'dark' : 'light';
        window.FlashMindTheme?.setTheme?.(theme);
        syncThemeButtons();
    });
});

window.addEventListener('flashmind:themechange', syncThemeButtons);
syncThemeButtons();

const syncLanguageButton = () => {
    if (!languageToggle) return;

    const language = window.FlashMindLanguage?.getLanguage?.() || 'en';
    languageToggle.textContent = language === 'pl' ? 'Polski' : 'English (US)';
};

languageToggle?.addEventListener('click', () => {
    const current = window.FlashMindLanguage?.getLanguage?.() || 'en';
    window.FlashMindLanguage?.setLanguage?.(current === 'pl' ? 'en' : 'pl');
    syncLanguageButton();
});

window.addEventListener('flashmind:languagechange', syncLanguageButton);
syncLanguageButton();

    const togglePasswordVisibility = (event) => {
        const toggleButton = event.target.closest('[data-password-toggle]');
        if (!toggleButton) return;

        const field = toggleButton.closest('.password-field')?.querySelector('[data-password-field]');
        if (!field) return;

        field.type = field.type === 'password' ? 'text' : 'password';

        const img = toggleButton.querySelector('.password-icon');
        if (img) {
            img.src = field.type === 'password' ? '/icons/login/eye.svg' : '/icons/login/eye_off.svg';
            img.alt = field.type === 'password' ? 'Show password' : 'Hide password';
        }
    };

    const openPasswordModal = () => {
        if (!passwordModal) return;
        passwordModal.hidden = false;
        passwordModal.querySelector('input')?.focus();
    };
    const closePasswordModal = () => {
        if (passwordModal) passwordModal.hidden = true;
    };

    const setPasswordError = (field, message) => {
        const node = passwordForm?.querySelector(`[data-password-error="${field}"]`);
        if (node) {
            node.textContent = message;
        }
    };

    const validatePasswordMatch = () => {
        if (!passwordInput || !passwordConfirmationInput) return true;

        const password = passwordInput.value;
        const confirmation = passwordConfirmationInput.value;

        if (password !== '' && password.length < 8) {
            setPasswordError('password', 'Hasło musi mieć co najmniej 8 znaków.');
        } else {
            setPasswordError('password', '');
        }

        if (confirmation === '') {
            setPasswordError('password_confirmation', '');
            return true;
        }

        if (password !== confirmation) {
            setPasswordError('password_confirmation', 'Hasła nie są takie same.');
            return false;
        }

        setPasswordError('password_confirmation', '');
        return true;
    };

    document.querySelector('[data-open-password-modal]')?.addEventListener('click', openPasswordModal);
    passwordModal?.addEventListener('click', togglePasswordVisibility);
    passwordInput?.addEventListener('input', validatePasswordMatch);
    passwordConfirmationInput?.addEventListener('input', validatePasswordMatch);
    passwordForm?.addEventListener('submit', (event) => {
        if (!validatePasswordMatch()) {
            event.preventDefault();
        }
    });
    document.querySelectorAll('[data-close-password-modal]').forEach((button) => {
        button.addEventListener('click', closePasswordModal);
    });
    passwordModal?.addEventListener('click', (event) => {
        if (event.target === passwordModal) closePasswordModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closePasswordModal();
    });

    if (settingsMain?.dataset.passwordModalOpen === 'true') {
        openPasswordModal();
    }
