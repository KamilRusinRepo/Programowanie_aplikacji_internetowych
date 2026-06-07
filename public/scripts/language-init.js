(function () {
    const storageKey = 'flashmindLanguage';
    const originalText = new WeakMap();
    const originalAttributes = new WeakMap();
    let isApplying = false;
    let observerTimer = 0;

    const translations = {
        'Master your learning': 'Opanuj swoją naukę',
        'Home': 'Strona Główna',
        'My Decks': 'Moje Talie',
        'Explore Decks': 'Odkrywaj Talie',
        'Statistics': 'Statystyki',
        'Statistics Dashboard': 'Dashboard Statystyk',
        'Admin Panel': 'Panel Admina',
        'Settings': 'Ustawienia',
        'Logout': 'Wyloguj',
        'Pro Learner': 'Aktywny uczeń',
        "You're on a roll today. Keep the momentum going.": 'Dzisiaj idzie Ci świetnie. Utrzymaj tempo.',
        'Continue Learning': 'Kontynuuj naukę',
        'View All Reviews': 'Zobacz wszystkie powtórki',
        'Mastery': 'Opanowanie',
        'Stats Overview': 'Przegląd Statystyk',
        'Start Session': 'Rozpocznij sesję',
        '+ Create Deck': '+ Utwórz talię',
        'Explore More': 'Odkrywaj więcej',
        'Explore Marketplace': 'Odkrywaj marketplace',
        'Find public decks, follow the useful ones, and review community flashcards.': 'Znajdź publiczne talie, obserwuj przydatne zestawy i oceniaj fiszki społeczności.',
        'Find public decks created by other learners.': 'Znajdź publiczne talie utworzone przez innych uczących się.',
        'Search': 'Szukaj',
        'Sort By': 'Sortuj Według',
        'All Types': 'Wszystkie Typy',
        'Most Followed': 'Najwięcej Obserwujących',
        'Most Cards': 'Najwięcej Kart',
        'Highest Rated': 'Najwyżej Oceniane',
        'Most Reviewed': 'Najwięcej Recenzji',
        'Newest': 'Najnowsze',
        'Trending Decks': 'Popularne Talie',
        'Follow Deck': 'Obserwuj Talię',
        'Unfollow Deck': 'Przestań Obserwować',
        'Unfollow': 'Przestań Obserwować',
        'Following': 'Obserwowana',
        'Your Deck': 'Twoja Talia',
        'No public decks match your filters yet.': 'Brak publicznych talii pasujących do filtrów.',
        'No new decks to discover right now. You already follow everything available.': 'Brak nowych talii do odkrycia. Obserwujesz już wszystko, co jest dostępne.',
        'Following Decks': 'Obserwowane Talie',
        'No followed decks yet. Explore public decks to follow one.': 'Nie obserwujesz jeszcze żadnych talii. Odkryj publiczne talie i wybierz jedną.',
        'Cards': 'Fiszki',
        'Reviews': 'Recenzje',
        'This deck does not have cards yet.': 'Ta talia nie ma jeszcze fiszek.',
        'Average rating': 'Średnia ocena',
        'View Public Page': 'Zobacz Stronę Publiczną',
        'Write a Review': 'Napisz Recenzję',
        'Rating': 'Ocena',
        'Comment': 'Komentarz',
        'Save Review': 'Zapisz Recenzję',
        'No reviews yet. Be the first to rate this deck.': 'Brak recenzji. Oceń tę talię jako pierwszy.',
        'No reviews yet. Reviews will appear here when learners rate this public deck.': 'Brak recenzji. Pojawią się tutaj, gdy użytkownicy ocenią tę publiczną talię.',
        'learners': 'uczących się',
        'SCIENCE': 'NAUKA',
        'LANGUAGE': 'JĘZYK',
        'PHILOSOPHY': 'FILOZOFIA',
        'Quantum Physics Basics': 'Podstawy fizyki kwantowej',
        'Italian for Travelers': 'Włoski dla podróżników',
        'Stoicism Daily': 'Stoicyzm na co dzień',
        'Profile Settings': 'Ustawienia Profilu',
        'FlashMind Pro Member': 'Użytkownik FlashMind Pro',
        'Username': 'Nazwa Użytkownika',
        'Email Address': 'Adres Email',
        'Save Changes': 'Zapisz zmiany',
        'Security': 'Bezpieczeństwo',
        'Password': 'Hasło',
        'Change Password': 'Zmień Hasło',
        'Preferences': 'Preferencje',
        'Appearance': 'Wygląd',
        'Choose light or dark theme': 'Wybierz Jasny lub Ciemny Motyw',
        'Light': 'Jasny',
        'Dark': 'Ciemny',
        'Language': 'Język',
        'Select your preferred language': 'Wybierz Preferowany Język',
        'English (US)': 'Polski',
        'Daily Reminders': 'Codzienne Przypomnienia',
        'Get notified for daily practice': 'Otrzymuj Przypomnienia o Codziennej Nauce',
        'Current Password': 'Obecne Hasło',
        'New Password': 'Nowe Hasło',
        'Confirm New Password': 'Potwierdź Nowe Hasło',
        'Save Password': 'Zapisz Hasło',
        'Create New Deck': 'Utwórz Nową Talię',
        'Organize your learning with a custom set of flashcards.': 'Organizuj naukę przy pomocy własnego zestawu fiszek.',
        'Deck Name': 'Nazwa Talii',
        'Description': 'Opis',
        'Deck Type': 'Typ Talii',
        'General': 'Ogólna',
        'Source Language': 'Język Źródłowy',
        'Target Language': 'Język Docelowy',
        'Select language': 'Wybierz język',
        'English': 'Angielski',
        'Polish': 'Polski',
        'German': 'Niemiecki',
        'Spanish': 'Hiszpański',
        'French': 'Francuski',
        'Japanese': 'Japoński',
        'Category': 'Kategoria',
        'Select category': 'Wybierz kategorię',
        'Academic': 'Akademicka',
        'History': 'Historia',
        'Science': 'Nauka',
        'Personal': 'Osobista',
        'Public Deck': 'Publiczna Talia',
        'Allow others to find and study this deck': 'Pozwól Innym Znaleźć i Uczyć Się z Tej Talii',
        'Create Deck': 'Utwórz Talię',
        'Cancel': 'Anuluj',
        'All the decks you have created so far.': 'Wszystkie talie, które do tej pory utworzyłeś.',
        'Import': 'Importuj',
        'Add Card': 'Dodaj Fiszkę',
        'Start Study': 'Rozpocznij Naukę',
        'Delete Deck': 'Usuń Talię',
        'Front (Question)': 'Przód (pytanie)',
        'Back (Answer)': 'Tył (odpowiedź)',
        'Add New Card': 'Dodaj Nową Fiszkę',
        'Front question': 'Pytanie z Przodu',
        'Example sentence (optional)': 'Zdanie przykładowe (opcjonalne)',
        'Image URL (optional)': 'URL obrazka (opcjonalnie)',
        'Answer': 'Odpowiedź',
        'Translated example sentence (optional)': 'Przetłumaczone zdanie przykładowe (opcjonalnie)',
        'Save Card': 'Zapisz Fiszkę',
        'Active Session': 'Aktywna Sesja',
        'Leaderboard': 'Ranking',
        'Daily Goal': 'Cel Dnia',
        'Daily': 'Dziennie',
        'Weekly': 'Tygodniowo',
        'All-time': 'Cały Okres',
        'Total Mastered': 'Łącznie Opanowane',
        'Study Time': 'Czas Nauki',
        'Avg. Accuracy': 'Śr. Celność',
        'Cards successfully learned': 'Karty skutecznie nauczone',
        'Time spent in study sessions': 'Czas spędzony w sesjach nauki',
        'Correct answers across the period': 'Poprawne odpowiedzi w wybranym okresie',
        'Learning Consistency': 'Systematyczność Nauki',
        'Less': 'Mniej',
        'More': 'Więcej',
        'You are building a steady learning rhythm. Keep it up!': 'Budujesz stabilny rytm nauki. Tak trzymaj!',
        'Weekly Performance': 'Wynik Tygodniowy',
        'XP earned each day this week.': 'XP zdobyte każdego dnia w tym tygodniu.',
        'Deck Mastery': 'Opanowanie Talii',
        'Cards Mastered': 'Opanowanych Kart',
        'View All Decks': 'Zobacz Wszystkie Talie',
        'Current Session Stats': 'Statystyki Obecnej Sesji',
        'Correct': 'Poprawne',
        'Wrong': 'Błędne',
        'Accuracy': 'Celność',
        'Study Tip': 'Wskazówka',
        'Try saying the word out loud as you type it to build muscle memory for pronunciation.': 'Spróbuj mówić słowo na głos podczas pisania, aby utrwalać wymowę.',
        'Session Progress': 'Postęp Sesji',
        'Front': 'Przód',
        'Front + Example': 'Przód + przykład',
        'Back': 'Tył',
        'Submit': 'Sprawdź',
        'Next': 'Dalej',
        'Finish session': 'Zakończ sesję',
        'Hint': 'Podpowiedź',
        'Skip': 'Pomiń',
        'Press Enter to submit': 'Naciśnij Enter, aby Zatwierdzić',
        'Session Complete!': 'Sesja Ukończona!',
        'cards': 'kart',
        'points': 'punktów',
        'XP Earned': 'Zdobyte XP',
        'Dashboard': 'Dashboard',
        'Study Again': 'Ucz Się Ponownie',
        'Choose a deck to inspect mastery, weak cards, and next review dates.': 'Wybierz talię, aby sprawdzić opanowanie, słabe karty i daty powtórek.',
        'Mastery Report': 'Raport Opanowania',
        'Export': 'Eksportuj',
        'Study Weak Cards': 'Ucz Się Słabych Kart',
        'Individual Card Mastery': 'Opanowanie Pojedynczych Fiszek',
        'Sort': 'Sortowanie',
        'Stars': 'Gwiazdki',
        'Card': 'Fiszka',
        'Mastery Level': 'Poziom Opanowania',
        'Last Reviewed': 'Ostatnia Powtórka',
        'Admin Panel': 'Panel Admina',
        'Manage users, roles, account status, and learning activity.': 'Zarządzaj użytkownikami, rolami, statusem kont i aktywnością nauki.',
        'All roles': 'Wszystkie Role',
        'All statuses': 'Wszystkie Statusy',
        'Active': 'Aktywny',
        'Disabled': 'Zablokowany',
        'Filter': 'Filtruj',
        'Add User': 'Dodaj Użytkownika',
        'User': 'Użytkownik',
        'Role': 'Rola',
        'Status': 'Status',
        'Last Activity': 'Ostatnia Aktywność',
        'Actions': 'Akcje',
        'EMAIL': 'EMAIL',
        'USERNAME': 'NAZWA UŻYTKOWNIKA',
        'PASSWORD': 'HASŁO',
        'ROLE': 'ROLA',
        'Edit User': 'Edytuj Użytkownika',
        'Changing a user\'s role to Admin will grant them full permissions to manage the database and other users in the panel.': 'Zmiana roli użytkownika na Admin nada mu pełne uprawnienia do zarządzania bazą danych i użytkownikami w panelu.',
    };

    const placeholders = {
        'Username': 'Nazwa Użytkownika',
        'Email Address': 'Adres Email',
        'Search users...': 'Szukaj użytkowników...',
        'Search decks, subjects, or creators...': 'Szukaj talii, tematów lub autorów...',
        'What do you think about this deck?': 'Co sądzisz o tej talii?',
        'Type translation...': 'Wpisz tłumaczenie...',
        'e.g. European History, Advanced Japanese...': 'np. Historia Europy, Zaawansowany japoński...',
        'What will you learn with this deck?': 'Czego będziesz się uczyć z tą talią?',
    };

    const normalizeLanguage = (language) => language === 'pl' ? 'pl' : 'en';

    const getLanguage = () => {
        try {
            return normalizeLanguage(window.localStorage.getItem(storageKey) || 'en');
        } catch (error) {
            return 'en';
        }
    };

    const translatePattern = (value) => {
        const trimmed = value.trim();
        const progressMatch = trimmed.match(/^(\d+) of (\d+) words$/);
        if (progressMatch) {
            return `${progressMatch[1]} z ${progressMatch[2]} słów`;
        }

        const dueMatch = trimmed.match(/^(\d+) cards due for review$/);
        if (dueMatch) {
            return `${dueMatch[1]} kart do powtórki`;
        }

        const cardCountMatch = trimmed.match(/^(\d+) Cards$/);
        if (cardCountMatch) {
            return `${cardCountMatch[1]} kart`;
        }

        const lowercaseCardCountMatch = trimmed.match(/^(\d+) cards$/);
        if (lowercaseCardCountMatch) {
            return `${lowercaseCardCountMatch[1]} kart`;
        }

        const learnerCountMatch = trimmed.match(/^([\d.,]+k?|\d+) learners$/);
        if (learnerCountMatch) {
            return `${learnerCountMatch[1]} uczących się`;
        }

        const reviewCountMatch = trimmed.match(/^Average rating ([\d.]+) • (\d+) reviews$/);
        if (reviewCountMatch) {
            return `Średnia ocena ${reviewCountMatch[1]} • ${reviewCountMatch[2]} recenzji`;
        }

        const statsCountMatch = trimmed.match(/^(\d+) cards • (\d+) good • (\d+) wrong$/);
        if (statsCountMatch) {
            return `${statsCountMatch[1]} kart • ${statsCountMatch[2]} dobrych • ${statsCountMatch[3]} błędnych`;
        }

        const lastChangedMatch = trimmed.match(/^Last changed (.+)$/);
        if (lastChangedMatch) {
            const value = lastChangedMatch[1];
            if (value === 'today') return 'Zmieniono dzisiaj';
            if (value === 'yesterday') return 'Zmieniono wczoraj';
            const days = value.match(/^(\d+) days ago$/);
            if (days) return `Zmieniono ${days[1]} dni temu`;
            return `Zmieniono ${value}`;
        }

        return null;
    };

    const translateValue = (value, language) => {
        if (language !== 'pl') {
            return value;
        }

        const exact = translations[value.trim()];
        if (exact) {
            return value.replace(value.trim(), exact);
        }

        return translatePattern(value) || value;
    };

    const translateTextNode = (node, language) => {
        const current = node.nodeValue || '';
        if (current.trim() === '') return;

        const remembered = originalText.get(node);
        const rememberedPolish = remembered ? translateValue(remembered, 'pl') : '';

        if (!remembered || (current !== remembered && current !== rememberedPolish)) {
            originalText.set(node, current);
        }

        const translated = translateValue(originalText.get(node), language);
        if (node.nodeValue !== translated) {
            node.nodeValue = translated;
        }
    };

    const translateAttributes = (element, language) => {
        ['placeholder', 'title', 'aria-label'].forEach((attribute) => {
            if (!element.hasAttribute(attribute)) return;

            let originals = originalAttributes.get(element);
            if (!originals) {
                originals = {};
                originalAttributes.set(element, originals);
            }

            if (!originals[attribute]) {
                originals[attribute] = element.getAttribute(attribute) || '';
            }

            const source = originals[attribute];
            const translated = language === 'pl'
                ? (placeholders[source] || translations[source] || source)
                : source;
            if (element.getAttribute(attribute) !== translated) {
                element.setAttribute(attribute, translated);
            }
        });
    };

    const applyLanguage = (language) => {
        const normalized = normalizeLanguage(language);
        if (!document.body) return;

        isApplying = true;
        document.documentElement.lang = normalized === 'pl' ? 'pl' : 'en';

        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        let node = walker.nextNode();
        while (node) {
            translateTextNode(node, normalized);
            node = walker.nextNode();
        }

        document.querySelectorAll('[placeholder], [title], [aria-label]').forEach((element) => {
            translateAttributes(element, normalized);
        });

        document.body.classList.toggle('language-pl', normalized === 'pl');
        isApplying = false;
    };

    const setLanguage = (language) => {
        const normalized = normalizeLanguage(language);

        try {
            window.localStorage.setItem(storageKey, normalized);
        } catch (error) {
            // Language still updates for the current page.
        }

        applyLanguage(normalized);
        window.dispatchEvent(new CustomEvent('flashmind:languagechange', {
            detail: { language: normalized },
        }));
    };

    window.FlashMindLanguage = {
        getLanguage,
        setLanguage,
        applyLanguage,
    };

    document.addEventListener('DOMContentLoaded', () => {
        applyLanguage(getLanguage());

        const observer = new MutationObserver(() => {
            if (isApplying || getLanguage() !== 'pl') return;

            window.clearTimeout(observerTimer);
            observerTimer = window.setTimeout(() => {
                applyLanguage('pl');
            }, 30);
        });

        observer.observe(document.body, {
            childList: true,
            characterData: true,
            subtree: true,
        });
    });
})();
