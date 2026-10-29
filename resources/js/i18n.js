/**
 * Same idea as Laravel's __(): the English text is the key, the layout puts lang/<locale>.json
 * into window.translations (empty for English).
 *
 *   t('Visitor :name saved.', { name: 'Nino' })
 */
export function t(key, replace = {}) {
    let text = window.translations?.[key] ?? key;
    for (const [name, value] of Object.entries(replace)) {
        text = text.replaceAll(`:${name}`, value ?? '');
    }
    return text;
}

export const locale = document.documentElement.lang || 'en';
