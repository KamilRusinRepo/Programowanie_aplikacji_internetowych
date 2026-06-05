const studyMain = document.querySelector('[data-study-cards][data-deck-id]');
    let cards = [];
    try {
        cards = JSON.parse(studyMain?.dataset.studyCards || '[]');
    } catch (error) {
        cards = [];
    }
    const deckId = Number(studyMain?.dataset.deckId || 0);
    const cardFront = document.querySelector('[data-card-front]');
    const cardAnswer = document.querySelector('[data-card-answer]');
    const cardExampleFront = document.querySelector('[data-card-example-front]');
    const cardTranslated = document.querySelector('[data-card-translated]');
    const cardTag = document.querySelector('[data-card-tag]');
    const cardBox = document.querySelector('[data-study-card]');
    const submitBtn = document.querySelector('[data-submit-btn]');
    const input = document.querySelector('[data-card-input]');
    const progressBar = document.querySelector('[data-progress-bar]');
    const progressCount = document.querySelector('[data-progress-count]');
    const correctCount = document.querySelector('[data-correct]');
    const wrongCount = document.querySelector('[data-wrong]');
    const accuracyValue = document.querySelector('[data-accuracy]');
    const accuracyBar = document.querySelector('[data-accuracy-bar]');
    const studyForm = document.querySelector('[data-study-form]');
    const hintBtn = document.querySelector('[data-hint-btn]');
    const skipBtn = document.querySelector('[data-skip-btn]');
    const submitLabel = submitBtn?.querySelector('span');

    let index = 0;
    let showingBack = false;
    let correct = 0;
    let wrong = 0;
    let answered = false;
    let currentWasCorrect = null;
    let answers = [];
    let completed = false;
    let finishing = false;
    let hintUsed = false;
    let startedAt = Date.now();

    const storageKey = `studySession_${deckId}`;

    const focusAnswerInput = () => {
        window.requestAnimationFrame(() => {
            if (input && !input.disabled) {
                input.focus();
            }
        });
    };

    const loadState = () => {
        try {
            const raw = window.localStorage.getItem(storageKey);
            if (!raw) return;
            const state = JSON.parse(raw);
            if (!state || typeof state !== 'object') return;
            index = Number.isInteger(state.index) ? state.index : 0;
            correct = Number.isInteger(state.correct) ? state.correct : 0;
            wrong = Number.isInteger(state.wrong) ? state.wrong : 0;
            showingBack = state.showingBack === true;
            answered = state.answered === true;
            currentWasCorrect = typeof state.currentWasCorrect === 'boolean' ? state.currentWasCorrect : null;
            answers = Array.isArray(state.answers) ? state.answers : [];
            completed = state.completed === true;
            hintUsed = state.hintUsed === true;
            startedAt = Number.isInteger(state.startedAt) && state.startedAt > 0 ? state.startedAt : Date.now();
        } catch (error) {
            // ignore storage errors
        }
    };

    const saveState = () => {
        try {
            const state = { index, correct, wrong, showingBack, answered, currentWasCorrect, answers, completed, hintUsed, startedAt };
            window.localStorage.setItem(storageKey, JSON.stringify(state));
        } catch (error) {
            // ignore storage errors
        }
    };

    const updateSubmitLabel = () => {
        if (!answered) {
            if (submitLabel) submitLabel.textContent = 'Submit';
            submitBtn.type = 'submit';
            return;
        }

        if (submitLabel) submitLabel.textContent = index >= cards.length - 1 ? 'Finish session' : 'Next';
        submitBtn.type = index >= cards.length - 1 ? 'button' : 'submit';
    };

    const normalizeAnswer = (value) => String(value || '').trim().toLowerCase();

    const answerOptions = (answer) => String(answer || '')
        .split(',')
        .map((option) => normalizeAnswer(option))
        .filter(Boolean);

    const displayAnswer = (answer) => {
        const first = String(answer || '').split(',')[0] || '';
        return first.trim();
    };

    const resetSessionState = () => {
        try {
            window.localStorage.removeItem(storageKey);
        } catch (error) {
            // ignore storage errors
        }
    };

    const applyState = () => {
        if (showingBack) {
            cardBox.classList.add('is-flipped');
        } else {
            cardBox.classList.remove('is-flipped');
        }
        updateSubmitLabel();
        cardBox.classList.toggle('is-correct', currentWasCorrect === true);
        cardBox.classList.toggle('is-wrong', currentWasCorrect === false);
    };

    const updateCard = (preserveAnswerState = false) => {
        const card = cards[index];
        if (!card) {
            cardFront.textContent = 'No cards available';
            cardAnswer.textContent = '';
            cardExampleFront.textContent = '';
            cardTranslated.textContent = '';
            if (submitLabel) submitLabel.textContent = 'Submit';
            submitBtn.disabled = true;
            input.disabled = true;
            saveState();
            return;
        }

        if (!preserveAnswerState) {
            cardBox.classList.add('is-switching');
            cardBox.classList.remove('is-flipped', 'is-correct', 'is-wrong');
        }

        cardFront.textContent = card.front_question || '';
        cardAnswer.textContent = displayAnswer(card.answer);
        cardExampleFront.textContent = card.example_sentence || '';
        cardTranslated.textContent = card.translated_example || '';
        cardTag.textContent = card.example_sentence ? 'Front + Example' : 'Front';
        if (!preserveAnswerState) {
            if (submitLabel) submitLabel.textContent = 'Submit';
            submitBtn.type = 'submit';
            showingBack = false;
            answered = false;
            currentWasCorrect = null;
            hintUsed = false;
            input.value = '';
            input.disabled = false;
            submitBtn.disabled = false;
            if (hintBtn) hintBtn.disabled = false;
            if (skipBtn) skipBtn.disabled = false;
            window.requestAnimationFrame(() => {
                cardBox.classList.remove('is-switching');
            });
            focusAnswerInput();
        } else {
            input.disabled = answered;
            submitBtn.disabled = false;
            updateSubmitLabel();
            if (hintBtn) hintBtn.disabled = answered;
            if (skipBtn) skipBtn.disabled = answered;
            if (!answered) {
                focusAnswerInput();
            }
        }
        updateProgress();
        saveState();
    };

    const updateProgress = () => {
        const total = cards.length || 0;
        const current = total === 0 ? 0 : index + 1;
        const percent = total === 0 ? 0 : Math.round((current / total) * 100);
        progressBar.style.width = percent + '%';
        progressCount.textContent = current + ' of ' + total + ' words';
        correctCount.textContent = correct;
        wrongCount.textContent = wrong;
        const answered = correct + wrong;
        const accuracy = answered === 0 ? 0 : Math.round((correct / answered) * 100);
        if (accuracyValue) {
            accuracyValue.textContent = accuracy + '%';
        }
        if (accuracyBar) {
            accuracyBar.style.width = accuracy + '%';
        }
    };

    const onSubmit = () => {
        const card = cards[index];
        if (!card) return;

        if (!answered) {
            const guess = normalizeAnswer(input.value);
            const answersForCard = answerOptions(card.answer);
            currentWasCorrect = guess !== '' && answersForCard.includes(guess);

            if (currentWasCorrect) {
                correct += 1;
            } else {
                wrong += 1;
            }

            answers.push({
                cardId: Number(card.id),
                correct: currentWasCorrect,
                userAnswer: input.value || '',
                usedHint: hintUsed,
            });

            answered = true;
            showingBack = true;
            input.disabled = true;
            if (hintBtn) hintBtn.disabled = true;
            if (skipBtn) skipBtn.disabled = true;
            cardBox.classList.add('is-flipped');
            cardBox.classList.toggle('is-correct', currentWasCorrect);
            cardBox.classList.toggle('is-wrong', !currentWasCorrect);
            updateSubmitLabel();
            updateProgress();
            saveState();
        } else {
            index += 1;
            if (index >= cards.length) {
                finishSession();
                return;
            }
            updateCard();
        }
    };

    const finishSession = async () => {
        if (finishing) return;
        finishing = true;
        completed = true;
        submitBtn.disabled = true;
        input.disabled = true;
        if (hintBtn) hintBtn.disabled = true;
        if (skipBtn) skipBtn.disabled = true;
        saveState();

        try {
            const controller = new AbortController();
            const timeoutId = window.setTimeout(() => controller.abort(), 6000);
            const body = new URLSearchParams();
            const durationSeconds = Math.max(1, Math.round((Date.now() - startedAt) / 1000));
            body.set('answers', JSON.stringify(answers));
            body.set('durationSeconds', String(durationSeconds));
            const response = await fetch(`/decks/${deckId}/study/complete`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
                signal: controller.signal,
            });
            window.clearTimeout(timeoutId);
            const payload = await response.json();
            if (payload && payload.success && payload.redirect) {
                resetSessionState();
                window.location.assign(payload.redirect);
                return;
            }
        } catch (error) {
            // Keep the session state so the user can retry finishing.
        }

        finishing = false;
        completed = false;
        submitBtn.disabled = false;
        updateSubmitLabel();
        saveState();
        alert('Nie udało się zapisać sesji. Spróbuj kliknąć Finish session jeszcze raz.');
    };

    const revealHint = () => {
        const card = cards[index];
        if (!card || answered) return;
        const answer = displayAnswer(card.answer);
        if (answer === '') return;
        hintUsed = true;
        input.value = answer.slice(0, Math.max(1, Math.ceil(answer.length / 3)));
        input.focus();
        saveState();
    };

    const skipCard = () => {
        if (answered) return;
        input.value = '';
        onSubmit();
    };

    if (studyForm) {
        studyForm.addEventListener('submit', (event) => {
            event.preventDefault();
            onSubmit();
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) {
            return;
        }

        if (event.target instanceof HTMLTextAreaElement) {
            return;
        }

        if (finishing || !cards[index]) {
            return;
        }

        event.preventDefault();
        onSubmit();
    });

    if (submitBtn) {
        submitBtn.addEventListener('click', (event) => {
            if (answered && index >= cards.length - 1) {
                event.preventDefault();
                finishSession();
            }
        });
    }

    if (cardBox) {
        cardBox.addEventListener('click', () => {
            if (!answered) return;
            showingBack = !showingBack;
            applyState();
            saveState();
        });
    }

    if (hintBtn) hintBtn.addEventListener('click', revealHint);
    if (skipBtn) skipBtn.addEventListener('click', skipCard);

    document.querySelectorAll('[data-reset-session]').forEach((link) => {
        link.addEventListener('click', resetSessionState);
    });

    loadState();
    updateCard(true);
    applyState();
    updateProgress();
    saveState();
    focusAnswerInput();
