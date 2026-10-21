/**
 * Small helpers for plain Blade forms.
 *
 *  <input data-digits="11">      → only digits can be typed/pasted, at most 11
 *  <form data-validate>           → HTML5 validation with Bootstrap styles; the message comes from
 *                                   the input's data-error attribute (fallback: browser message)
 */

document.addEventListener('input', (event) => {
    const input = event.target;
    if (input.matches?.('input[data-digits]')) {
        const max = Number(input.dataset.digits) || undefined;
        const clean = input.value.replace(/\D/g, '').slice(0, max);
        if (clean !== input.value) input.value = clean;
    }
    if (input.closest?.('form[data-validate]') && input.classList.contains('is-invalid')) {
        validateField(input); // re-check while the user is fixing the field
    }
});

document.addEventListener('focusout', (event) => {
    const input = event.target;
    if (input.matches?.('input, select, textarea') && input.closest('form[data-validate]') && input.value !== '') {
        validateField(input);
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches?.('form[data-validate]')) return;

    const fields = [...form.querySelectorAll('input, select, textarea')].filter((el) => el.type !== 'hidden');
    const invalid = fields.filter((el) => !validateField(el));
    if (invalid.length) {
        event.preventDefault();
        event.stopImmediatePropagation();
        invalid[0].focus();
    }
}, true);

function validateField(input) {
    if (input.type === 'email') input.value = input.value.trim();

    const feedback = input.parentElement.querySelector('.invalid-feedback');
    const valid = input.checkValidity();

    input.classList.toggle('is-invalid', !valid);
    input.classList.toggle('is-valid', valid && input.value !== '' && input.type !== 'checkbox');
    if (feedback && !valid) {
        feedback.textContent = input.validity.valueMissing ? (input.validationMessage || 'Required.') : (input.dataset.error || input.validationMessage);
    }

    return valid;
}
