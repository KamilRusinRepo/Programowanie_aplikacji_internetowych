(function () {
    const storageKey = 'flashmindTheme';

    const normalizeTheme = (theme) => theme === 'dark' ? 'dark' : 'light';

    const getTheme = () => {
        try {
            return normalizeTheme(window.localStorage.getItem(storageKey) || 'light');
        } catch (error) {
            return 'light';
        }
    };

    const applyTheme = (theme) => {
        const normalized = normalizeTheme(theme);
        const isDark = normalized === 'dark';

        document.documentElement.classList.toggle('theme-dark', isDark);
        document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';

        if (document.body) {
            document.body.classList.toggle('theme-dark', isDark);
        }
    };

    const setTheme = (theme) => {
        const normalized = normalizeTheme(theme);

        try {
            window.localStorage.setItem(storageKey, normalized);
        } catch (error) {
            // Theme still updates for the current page.
        }

        applyTheme(normalized);
        window.dispatchEvent(new CustomEvent('flashmind:themechange', {
            detail: { theme: normalized },
        }));
    };

    window.FlashMindTheme = {
        getTheme,
        setTheme,
        applyTheme,
    };

    applyTheme(getTheme());
})();
