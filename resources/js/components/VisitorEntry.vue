<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    ticketTypes: { type: Array, required: true },
    categories: { type: Array, required: true },
    paymentMethods: { type: Array, required: true },
    can: { type: Object, required: true },
    urls: { type: Object, required: true },
    preselectVisitorId: { type: Number, default: null },
});

const paymentLabels = { cash: 'Cash', card: 'Card', transfer: 'Bank transfer' };
const defaultCategory = props.categories.find((c) => c.is_default) ?? props.categories[0];

// --- state ------------------------------------------------------------------------------
const searchInput = ref(null);
const query = ref('');
const results = ref([]);
const searching = ref(false);
const searched = ref(false);

const mode = ref('search'); // search | selected | new
const visitor = ref(null);
const newVisitor = reactive(emptyVisitor());

const entry = reactive({
    ticket_type_id: props.ticketTypes[0]?.id ?? null,
    visitor_category_id: defaultCategory?.id ?? null,
    payment_method: props.paymentMethods[0] ?? 'cash',
    use_subscription: true,
    terms_accepted: false,
    price_override: '',
    comment: '',
});

const quote = ref(null);
const quoting = ref(false);
const submitting = ref(false);
const errors = ref({});
const errorMessage = ref('');
const success = ref(null);
const recent = ref([]);

function emptyVisitor() {
    return { first_name: '', last_name: '', personal_id: '', birth_date: '', phone: '', email: '', comment: '' };
}

// --- client-side validation (same rules as the server) ------------------------------------
const RULES = {
    name: /^\p{L}[\p{L}\s'\-]*$/u,
    personalId: /^\d{11}$/,
    phone: /^\d{9,15}$/,
    email: /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/,
};
const touched = reactive({});
const submitAttempted = ref(false);
const duplicate = ref(null);
const now = new Date();
const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

function birthDateError(value) {
    if (!value) return 'Date of birth is required.';
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value) || Number.isNaN(Date.parse(value))) return 'Enter a valid date.';
    if (value >= today) return 'The date of birth must be in the past.';
    if (Number(value.slice(0, 4)) < 1900) return 'Check the year.';
    return null;
}

function nameError(value, label) {
    const v = value.trim();
    if (!v) return `${label} is required.`;
    if (v.length < 2) return `${label} is too short.`;
    if (!RULES.name.test(v)) return `${label} may contain only letters, spaces and dashes.`;
    return null;
}

const clientErrors = computed(() => {
    const v = newVisitor;
    const e = {
        first_name: nameError(v.first_name, 'First name'),
        last_name: nameError(v.last_name, 'Last name'),
        personal_id: !v.personal_id
            ? 'ID number is required.'
            : !RULES.personalId.test(v.personal_id) ? `ID number must be exactly 11 digits (now ${v.personal_id.length}).` : null,
        birth_date: birthDateError(v.birth_date),
        phone: !v.phone ? 'Phone is required.' : !RULES.phone.test(v.phone) ? 'Phone must be 9–15 digits, e.g. 599123456.' : null,
        email: v.email && !RULES.email.test(v.email.trim()) ? 'Enter a real email address, e.g. name@gmail.com.' : null,
    };
    return Object.fromEntries(Object.entries(e).filter(([, message]) => message));
});
const newVisitorValid = computed(() => Object.keys(clientErrors.value).length === 0);

/** Digits-only inputs: drop everything else while typing or pasting. */
function setDigits(field, max, event) {
    const clean = event.target.value.replace(/\D/g, '').slice(0, max);
    newVisitor[field] = clean;
    event.target.value = clean;
    clearServerError(field);
}

function clearServerError(field) {
    if (errors.value[field]) {
        const { [field]: _removed, ...rest } = errors.value;
        errors.value = rest;
    }
}

/** Shows a field as valid/invalid once the user has left it (or tried to submit). */
function fieldState(name) {
    if (errors.value[name]) return 'is-invalid';
    if (!touched[name] && !submitAttempted.value) return '';
    return clientErrors.value[name] ? 'is-invalid' : (newVisitor[name] ? 'is-valid' : '');
}

// Warn before creating a second card for the same ID number.
watch(() => newVisitor.personal_id, async (id) => {
    duplicate.value = null;
    if (mode.value !== 'new' || !RULES.personalId.test(id)) return;
    try {
        const { data } = await axios.get(props.urls.search, { params: { q: id } });
        if (newVisitor.personal_id === id) duplicate.value = data.data.find((v) => v.personal_id === id) ?? null;
    } catch {
        // the server re-checks on save
    }
});

// --- search -----------------------------------------------------------------------------
let searchTimer = null;
let searchController = null;

watch(query, (value) => {
    clearTimeout(searchTimer);
    if (value.trim().length < 2) {
        results.value = [];
        searched.value = false;
        return;
    }
    searchTimer = setTimeout(runSearch, 250);
});

async function runSearch() {
    searchController?.abort();
    searchController = new AbortController();
    searching.value = true;
    try {
        const { data } = await axios.get(props.urls.search, { params: { q: query.value.trim() }, signal: searchController.signal });
        results.value = data.data;
        searched.value = true;
    } catch (e) {
        if (!axios.isCancel(e)) showError(e);
    } finally {
        searching.value = false;
    }
}

function selectVisitor(v) {
    visitor.value = v;
    mode.value = 'selected';
    entry.visitor_category_id = v.visitor_category_id ?? defaultCategory?.id ?? null;
    entry.terms_accepted = v.terms_accepted;
    entry.use_subscription = true;
    errors.value = {};
    success.value = null;
    refreshQuote();
}

function startNewVisitor() {
    Object.assign(newVisitor, emptyVisitor());
    Object.keys(touched).forEach((k) => delete touched[k]);
    submitAttempted.value = false;
    duplicate.value = null;
    // Pre-fill from what was typed in the search box.
    const words = query.value.trim().split(/\s+/).filter(Boolean);
    const digits = words.filter((w) => /^[0-9+\-()]+$/.test(w));
    const names = words.filter((w) => !digits.includes(w));
    newVisitor.first_name = names[0] ?? '';
    newVisitor.last_name = names.slice(1).join(' ');
    if (digits[0]) {
        const number = digits[0].replace(/\D/g, '');
        if (number.length === 11) newVisitor.personal_id = number;
        else newVisitor.phone = number;
    }

    visitor.value = null;
    mode.value = 'new';
    entry.visitor_category_id = defaultCategory?.id ?? null;
    entry.terms_accepted = false;
    errors.value = {};
    success.value = null;
    quote.value = null;
    refreshQuote();
}

function backToSearch() {
    mode.value = 'search';
    visitor.value = null;
    quote.value = null;
    errors.value = {};
    setTimeout(() => searchInput.value?.focus(), 0);
}

// --- price ------------------------------------------------------------------------------
let quoteTimer = null;

function refreshQuote() {
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(loadQuote, 150);
}

async function loadQuote() {
    const params = { ticket_type_id: entry.ticket_type_id, visitor_category_id: entry.visitor_category_id };
    if (mode.value === 'selected' && visitor.value) {
        params.visitor_id = visitor.value.id;
    } else if (mode.value === 'new' && !birthDateError(newVisitor.birth_date)) {
        params.birth_date = newVisitor.birth_date;
    } else {
        quote.value = null;
        return;
    }

    quoting.value = true;
    try {
        const { data } = await axios.get(props.urls.quote, { params });
        quote.value = data;
    } catch (e) {
        quote.value = null;
        showError(e);
    } finally {
        quoting.value = false;
    }
}

watch(() => [entry.ticket_type_id, entry.visitor_category_id, newVisitor.birth_date], refreshQuote);

const selectedTicket = computed(() => props.ticketTypes.find((t) => t.id === entry.ticket_type_id));
const coveredBySubscription = computed(() => !!quote.value?.subscription && entry.use_subscription && !selectedTicket.value?.duration_days);

const amountToPay = computed(() => {
    if (!quote.value) return null;
    if (coveredBySubscription.value) return 0;
    if (entry.price_override !== '' && entry.price_override !== null) return Number(entry.price_override);
    return quote.value.quote.available ? quote.value.quote.price : null;
});

const age = computed(() => (mode.value === 'selected' ? visitor.value?.age : quote.value?.quote.age));

const canSubmit = computed(() => {
    if (submitting.value || mode.value === 'search') return false;
    return mode.value === 'new' || amountToPay.value !== null;
});

const termsMissing = computed(() => (mode.value === 'new' || (visitor.value && !visitor.value.terms_accepted)) && !entry.terms_accepted);
watch(() => entry.terms_accepted, () => clearServerError('terms_accepted'));

/** Client checks before sending a new visitor. Returns false (and shows messages) when something is wrong. */
function checkNewVisitor() {
    submitAttempted.value = true;
    const problems = [];
    if (!newVisitorValid.value) problems.push('Please fix the highlighted fields.');
    if (duplicate.value) problems.push('A visitor with this ID number already exists — select them instead.');
    if (!entry.terms_accepted) {
        errors.value = { ...errors.value, terms_accepted: ['The visitor must accept the gym terms.'] };
        problems.push('The visitor must accept the gym terms.');
    }
    errorMessage.value = problems.join(' ');

    return problems.length === 0;
}

/** Only create the visitor card (no entrance), then show it as the selected visitor. */
async function saveVisitor() {
    if (submitting.value || !checkNewVisitor()) return;
    submitting.value = true;
    errors.value = {};

    try {
        const { data } = await axios.post(props.urls.storeVisitor, {
            ...newVisitor,
            visitor_category_id: entry.visitor_category_id,
            terms_accepted: true,
        });
        selectVisitor(data.data);
        success.value = { message: `Visitor ${data.data.full_name} saved.`, visit: null };
    } catch (e) {
        showError(e);
    } finally {
        submitting.value = false;
    }
}

// The time slot changes during the day; keep the price fresh.
let clock = null;
onMounted(async () => {
    clock = setInterval(() => mode.value !== 'search' && loadQuote(), 60_000);
    searchInput.value?.focus();

    if (props.preselectVisitorId) {
        try {
            const { data } = await axios.get(props.urls.visitorApi.replace('__ID__', props.preselectVisitorId));
            selectVisitor(data.data);
        } catch (e) {
            showError(e);
        }
    }
});
onBeforeUnmount(() => clearInterval(clock));

// --- submit -----------------------------------------------------------------------------
async function submit() {
    if (!canSubmit.value) return;
    errorMessage.value = '';

    if (mode.value === 'new' && !checkNewVisitor()) return;
    if (termsMissing.value) {
        errors.value = { ...errors.value, terms_accepted: ['The visitor must accept the gym terms.'] };
        errorMessage.value = 'The visitor must accept the gym terms.';
        return;
    }

    submitting.value = true;
    errors.value = {};

    const payload = {
        ticket_type_id: entry.ticket_type_id,
        visitor_category_id: entry.visitor_category_id,
        payment_method: entry.payment_method,
        use_subscription: entry.use_subscription,
        terms_accepted: entry.terms_accepted,
        price_override: props.can.overridePrice && entry.price_override !== '' ? entry.price_override : null,
        comment: entry.comment || null,
    };
    if (mode.value === 'selected') payload.visitor_id = visitor.value.id;
    else payload.visitor = { ...newVisitor, visitor_category_id: entry.visitor_category_id };

    try {
        const { data } = await axios.post(props.urls.store, payload);
        success.value = { message: data.message, visit: data.data };
        recent.value.unshift(data.data);
        recent.value = recent.value.slice(0, 8);
        resetAfterSuccess();
    } catch (e) {
        showError(e);
    } finally {
        submitting.value = false;
    }
}

function resetAfterSuccess() {
    query.value = '';
    results.value = [];
    visitor.value = null;
    quote.value = null;
    mode.value = 'search';
    entry.price_override = '';
    entry.comment = '';
    entry.use_subscription = true;
    entry.ticket_type_id = props.ticketTypes[0]?.id ?? null;
    entry.payment_method = props.paymentMethods[0] ?? 'cash';
    setTimeout(() => searchInput.value?.focus(), 0);
}

function showError(e) {
    if (e.response?.status === 422) {
        const raw = e.response.data.errors ?? {};
        // "visitor.first_name" -> "first_name" so the new-visitor form can show it.
        errors.value = Object.fromEntries(Object.entries(raw).map(([k, v]) => [k.replace(/^visitor\./, ''), v]));
        errorMessage.value = Object.keys(errors.value).length > 1 ? 'Please fix the highlighted fields.' : e.response.data.message;
    } else if (e.response?.status === 419 || e.response?.status === 401) {
        errorMessage.value = 'Your session has expired. Please reload the page and log in again.';
    } else if (e.response?.status === 403) {
        errorMessage.value = 'You do not have permission for this action.';
    } else {
        errorMessage.value = e.response?.data?.message || e.message || 'Something went wrong.';
    }
}

// --- helpers ----------------------------------------------------------------------------
const money = (v) => `${Number(v ?? 0).toFixed(2).replace(/\.00$/, '')} ₾`;
const fmtDateTime = (v) => (v ? new Date(v).toLocaleString('ka-GE', { dateStyle: 'short', timeStyle: 'short' }) : '—');
const fmtDate = (v) => (v ? new Date(v).toLocaleDateString('ka-GE') : '—');
const isToday = (v) => v && new Date(v).toDateString() === new Date().toDateString();
const ticketLabel = (t) => (t.name_ka ? `${t.name} / ${t.name_ka}` : t.name);
const categoryLabel = (c) => (c.name_ka ? `${c.name} / ${c.name_ka}` : c.name);
const fieldError = (name) => errors.value[name]?.[0]
    ?? (mode.value === 'new' && (touched[name] || submitAttempted.value) ? clientErrors.value[name] : undefined);
</script>

<template>
    <div class="row g-3">
        <!-- ============ LEFT: find / create visitor ============ -->
        <div class="col-lg-7">
            <div v-if="success" class="alert alert-success d-flex align-items-center justify-content-between">
                <div>
                    <i class="bi bi-check-circle-fill me-2"></i>{{ success.message }}
                    <template v-if="success.visit">
                        <strong class="ms-2">{{ money(success.visit.price) }}</strong>
                        <span v-if="success.visit.payment_method === 'subscription'" class="badge text-bg-info ms-2">subscription</span>
                    </template>
                    <span v-else class="ms-1">Now choose the ticket on the right and register the entrance.</span>
                </div>
                <a v-if="success.visit" :href="urls.visitor.replace('__ID__', success.visit.visitor_id)" class="btn btn-sm btn-outline-success">Open visitor</a>
            </div>
            <div v-if="errorMessage && mode === 'search'" class="alert alert-danger">{{ errorMessage }}</div>

            <!-- Search -->
            <div v-if="mode === 'search'" class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-search me-1"></i> Find visitor</h3></div>
                <div class="card-body">
                    <div class="input-group input-group-lg mb-3">
                        <span class="input-group-text"><i class="bi bi-person-vcard"></i></span>
                        <input
                            ref="searchInput"
                            v-model="query"
                            type="search"
                            class="form-control"
                            placeholder="Name, surname, ID number or phone…"
                            autocomplete="off"
                            @keydown.enter.prevent="results.length === 1 && selectVisitor(results[0])"
                        />
                        <span v-if="searching" class="input-group-text"><span class="spinner-border spinner-border-sm"></span></span>
                    </div>

                    <div v-if="results.length" class="list-group search-results mb-3">
                        <button
                            v-for="v in results"
                            :key="v.id"
                            type="button"
                            class="list-group-item list-group-item-action"
                            @click="selectVisitor(v)"
                        >
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold">{{ v.full_name }}</div>
                                    <small class="text-body-secondary">
                                        ID {{ v.personal_id }} · {{ v.age }} y · {{ v.phone }}
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span v-if="v.active_subscriptions?.length" class="badge text-bg-info d-block mb-1">
                                        <i class="bi bi-card-checklist"></i> subscription
                                    </span>
                                    <span v-if="!v.terms_accepted" class="badge text-bg-warning d-block mb-1">no terms</span>
                                    <small class="text-body-secondary" :class="{ 'text-danger fw-semibold': isToday(v.last_visit_at) }">
                                        last: {{ fmtDateTime(v.last_visit_at) }}
                                    </small>
                                </div>
                            </div>
                        </button>
                    </div>

                    <p v-else-if="searched && !searching" class="text-body-secondary">No visitors found for “{{ query }}”.</p>

                    <button v-if="can.createVisitor" type="button" class="btn btn-outline-primary" @click="startNewVisitor">
                        <i class="bi bi-person-plus"></i> New visitor
                    </button>
                </div>
            </div>

            <!-- Selected visitor -->
            <div v-else-if="mode === 'selected' && visitor" class="card card-success card-outline">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0"><i class="bi bi-person-check me-1"></i> {{ visitor.full_name }}</h3>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" @click="backToSearch">
                        <i class="bi bi-arrow-left"></i> Change
                    </button>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">ID number</dt><dd class="col-sm-8">{{ visitor.personal_id }}</dd>
                        <dt class="col-sm-4">Date of birth</dt><dd class="col-sm-8">{{ fmtDate(visitor.birth_date) }} ({{ visitor.age }} years)</dd>
                        <dt class="col-sm-4">Phone</dt><dd class="col-sm-8">{{ visitor.phone }}</dd>
                        <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ visitor.email || '—' }}</dd>
                        <dt class="col-sm-4">Visits</dt><dd class="col-sm-8">{{ visitor.visits_count ?? 0 }} · last {{ fmtDateTime(quote?.last_visit_at ?? visitor.last_visit_at) }}</dd>
                    </dl>
                    <div v-if="visitor.comment" class="alert alert-warning mt-3 mb-0"><i class="bi bi-chat-left-text me-1"></i>{{ visitor.comment }}</div>
                    <div v-if="isToday(quote?.last_visit_at)" class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i> This visitor has already entered today.
                    </div>
                    <a :href="urls.visitor.replace('__ID__', visitor.id)" target="_blank" class="btn btn-link px-0 mt-2">Open profile <i class="bi bi-box-arrow-up-right"></i></a>
                </div>
            </div>

            <!-- New visitor -->
            <div v-else-if="mode === 'new'" class="card card-warning card-outline">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0"><i class="bi bi-person-plus me-1"></i> New visitor</h3>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" @click="backToSearch">
                        <i class="bi bi-arrow-left"></i> Back to search
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="nv-first">First name <span class="text-danger">*</span></label>
                            <input id="nv-first" v-model="newVisitor.first_name" class="form-control" :class="fieldState('first_name')"
                                   maxlength="100" autocomplete="off" @blur="touched.first_name = true" @input="clearServerError('first_name')" />
                            <div class="invalid-feedback">{{ fieldError('first_name') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="nv-last">Last name <span class="text-danger">*</span></label>
                            <input id="nv-last" v-model="newVisitor.last_name" class="form-control" :class="fieldState('last_name')"
                                   maxlength="100" autocomplete="off" @blur="touched.last_name = true" @input="clearServerError('last_name')" />
                            <div class="invalid-feedback">{{ fieldError('last_name') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="nv-pid">ID number <span class="text-danger">*</span> <small class="text-body-secondary">(11 digits)</small></label>
                            <input id="nv-pid" :value="newVisitor.personal_id" class="form-control font-monospace" :class="fieldState('personal_id')"
                                   inputmode="numeric" maxlength="11" placeholder="01001012345" autocomplete="off"
                                   @input="setDigits('personal_id', 11, $event)" @blur="touched.personal_id = true" />
                            <div class="invalid-feedback">{{ fieldError('personal_id') }}</div>
                            <div v-if="duplicate" class="alert alert-warning py-2 mt-2 mb-0 small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Already registered: <strong>{{ duplicate.full_name }}</strong> ({{ duplicate.age }} y)
                                <button type="button" class="btn btn-sm btn-warning ms-2" @click="selectVisitor(duplicate)">Use this visitor</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="nv-birth">Date of birth <span class="text-danger">*</span></label>
                            <input id="nv-birth" v-model="newVisitor.birth_date" type="date" class="form-control" :class="fieldState('birth_date')"
                                   min="1900-01-01" :max="today" @blur="touched.birth_date = true" @change="touched.birth_date = true; clearServerError('birth_date')" />
                            <div class="invalid-feedback">{{ fieldError('birth_date') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="nv-phone">Phone <span class="text-danger">*</span> <small class="text-body-secondary">(digits only)</small></label>
                            <input id="nv-phone" :value="newVisitor.phone" type="tel" class="form-control font-monospace" :class="fieldState('phone')"
                                   inputmode="numeric" maxlength="15" placeholder="599123456" autocomplete="off"
                                   @input="setDigits('phone', 15, $event)" @blur="touched.phone = true" />
                            <div class="invalid-feedback">{{ fieldError('phone') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="nv-email">Email <small class="text-body-secondary">(optional)</small></label>
                            <input id="nv-email" v-model.trim="newVisitor.email" type="email" class="form-control" :class="fieldState('email')"
                                   maxlength="255" placeholder="name@gmail.com" autocomplete="off"
                                   @blur="touched.email = true" @input="clearServerError('email')" />
                            <div class="invalid-feedback">{{ fieldError('email') }}</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="nv-comment">Comment</label>
                            <textarea id="nv-comment" v-model="newVisitor.comment" rows="2" maxlength="2000" class="form-control" placeholder="Health notes, experience, anything staff should know…"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input id="terms-new" v-model="entry.terms_accepted" class="form-check-input" type="checkbox" :class="{ 'is-invalid': fieldError('terms_accepted') }" />
                                <label for="terms-new" class="form-check-label">
                                    Visitor has read and signed the <a :href="urls.terms" target="_blank">gym terms &amp; safety rules</a> <span class="text-danger">*</span>
                                </label>
                                <div class="invalid-feedback">{{ fieldError('terms_accepted') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div v-if="errorMessage" class="alert alert-danger py-2">{{ errorMessage }}</div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary" :disabled="submitting" @click="saveVisitor">
                            <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-save me-1"></i> Save visitor
                        </button>
                        <button type="button" class="btn btn-success" :disabled="submitting" @click="submit">
                            <i class="bi bi-door-open me-1"></i> Save &amp; register entrance
                            <template v-if="amountToPay !== null"> · {{ money(amountToPay) }}</template>
                        </button>
                        <button type="button" class="btn btn-outline-secondary ms-auto" :disabled="submitting" @click="backToSearch">Cancel</button>
                    </div>
                </div>
            </div>

            <!-- Entrances registered from this screen -->
            <div v-if="recent.length" class="card mt-3">
                <div class="card-header"><h3 class="card-title">Registered just now</h3></div>
                <ul class="list-group list-group-flush">
                    <li v-for="r in recent" :key="r.id" class="list-group-item d-flex justify-content-between">
                        <span>{{ fmtDateTime(r.entered_at) }} — {{ r.visitor.full_name }}</span>
                        <span>{{ r.ticket_type?.name }} · <strong>{{ money(r.price) }}</strong></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- ============ RIGHT: ticket + price ============ -->
        <div class="col-lg-5">
            <div class="card card-outline card-secondary" :class="{ 'opacity-50': mode === 'search' }">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-ticket-perforated me-1"></i> Entrance</h3></div>
                <div class="card-body">
                    <fieldset :disabled="mode === 'search'">
                        <label class="form-label">Ticket</label>
                        <div class="btn-group w-100 mb-3" role="group">
                            <template v-for="t in ticketTypes" :key="t.id">
                                <input :id="`ticket-${t.id}`" v-model="entry.ticket_type_id" type="radio" class="btn-check" :value="t.id" />
                                <label :for="`ticket-${t.id}`" class="btn btn-outline-primary">{{ ticketLabel(t) }}</label>
                            </template>
                        </div>
                        <div v-if="fieldError('ticket_type_id')" class="text-danger small mb-2">{{ fieldError('ticket_type_id') }}</div>

                        <label class="form-label">Category</label>
                        <select v-model="entry.visitor_category_id" class="form-select mb-3">
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ categoryLabel(c) }}</option>
                        </select>

                        <!-- Price -->
                        <div class="border rounded p-3 mb-3 bg-body-tertiary">
                            <div v-if="quoting && !quote" class="text-center py-3"><span class="spinner-border"></span></div>
                            <div v-else-if="!quote" class="text-body-secondary">
                                {{ mode === 'new' ? 'Enter the date of birth to calculate the price.' : 'Select a visitor.' }}
                            </div>
                            <template v-else>
                                <div class="d-flex justify-content-between small text-body-secondary mb-1">
                                    <span>Age {{ age }} · {{ quote.quote.age_group?.name ?? 'no age group' }}</span>
                                    <span>{{ quote.quote.time_slot?.label ?? 'outside working hours' }}</span>
                                </div>

                                <div v-if="quote.subscription" class="alert alert-info py-2 my-2">
                                    <div class="form-check form-switch mb-0">
                                        <input id="use-sub" v-model="entry.use_subscription" class="form-check-input" type="checkbox" :disabled="!!selectedTicket?.duration_days" />
                                        <label for="use-sub" class="form-check-label">
                                            Use subscription ({{ quote.subscription.ticket_type?.name }}, {{ quote.subscription.time_slot?.label }})
                                            until {{ fmtDate(quote.subscription.ends_on) }}
                                            <span v-if="quote.subscription.visits_limit"> · {{ quote.subscription.visits_used }}/{{ quote.subscription.visits_limit }} used</span>
                                        </label>
                                    </div>
                                </div>

                                <div v-if="!quote.quote.available && !coveredBySubscription" class="alert alert-danger py-2 my-2">
                                    {{ quote.quote.message }}
                                </div>

                                <div class="d-flex align-items-end justify-content-between mt-2">
                                    <div class="small text-body-secondary">
                                        <template v-if="coveredBySubscription">Covered by subscription</template>
                                        <template v-else-if="quote.quote.available">
                                            {{ money(quote.quote.base_price) }}
                                            <span v-if="quote.quote.coefficient !== 1"> × {{ quote.quote.coefficient }}</span>
                                            <div v-for="c in quote.quote.coefficients.filter((c) => c.coefficient !== 1)" :key="c.id">{{ c.name }}: × {{ c.coefficient }}</div>
                                        </template>
                                    </div>
                                    <div class="entry-price" :class="amountToPay === null ? 'text-body-secondary' : 'text-success'">
                                        {{ amountToPay === null ? '—' : money(amountToPay) }}
                                    </div>
                                </div>
                            </template>
                        </div>

                        <template v-if="!coveredBySubscription && amountToPay !== 0">
                            <label class="form-label">Payment</label>
                            <div class="btn-group w-100 mb-3" role="group">
                                <template v-for="m in paymentMethods" :key="m">
                                    <input :id="`pay-${m}`" v-model="entry.payment_method" type="radio" class="btn-check" :value="m" />
                                    <label :for="`pay-${m}`" class="btn btn-outline-secondary">{{ paymentLabels[m] ?? m }}</label>
                                </template>
                            </div>
                        </template>

                        <div v-if="can.overridePrice && !coveredBySubscription" class="mb-3">
                            <label class="form-label">Manual price <small class="text-body-secondary">(leave empty to use the calculated one)</small></label>
                            <div class="input-group">
                                <input v-model="entry.price_override" type="number" min="0" step="0.01" class="form-control" :class="{ 'is-invalid': fieldError('price_override') }" />
                                <span class="input-group-text">₾</span>
                            </div>
                            <div v-if="fieldError('price_override')" class="text-danger small">{{ fieldError('price_override') }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Note for this visit</label>
                            <input v-model="entry.comment" class="form-control" />
                        </div>

                        <div v-if="mode === 'selected' && visitor && !visitor.terms_accepted" class="form-check mb-3">
                            <input id="terms" v-model="entry.terms_accepted" class="form-check-input" type="checkbox" :class="{ 'is-invalid': fieldError('terms_accepted') }" />
                            <label for="terms" class="form-check-label">
                                Visitor has read and signed the <a :href="urls.terms" target="_blank">gym terms &amp; safety rules</a>
                            </label>
                            <div class="invalid-feedback">{{ fieldError('terms_accepted') }}</div>
                        </div>

                        <div v-if="errorMessage && mode === 'selected'" class="alert alert-danger py-2">{{ errorMessage }}</div>

                        <button v-if="mode !== 'new'" type="button" class="btn btn-success btn-lg w-100" :disabled="!canSubmit" @click="submit">
                            <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-door-open me-1"></i>
                            Register entrance
                            <template v-if="amountToPay !== null"> · {{ money(amountToPay) }}</template>
                        </button>
                    </fieldset>
                </div>
            </div>
        </div>
    </div>
</template>
