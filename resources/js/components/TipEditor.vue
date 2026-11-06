<script setup>
/**
 * Tech tip editor (admin/tech-tips/form.blade.php): the start screen (cover + title / short description per language)
 * and the slides, like a presentation: thumbnails on the left, the chosen slide on the right. Pictures, layout and
 * settings of a slide are shared by all languages; texts are written side by side for each language.
 * Sent with the normal form submit through inputs (slides as JSON, see App\Services\Tips\TipSlides).
 */
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { t } from '../i18n';
import MediaPicker from './tips/MediaPicker.vue';
import MarkdownField from './tips/MarkdownField.vue';

const props = defineProps({
    articles: { type: Object, required: true }, // {ka: {title, short}, en: {...}}
    image: { type: String, default: null },
    slides: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    locales: { type: Object, required: true },
    uploadUrl: { type: String, required: true },
    mediaUrl: { type: String, required: true },
    videoMaxMb: { type: Number, required: true },
    tips: { type: Array, default: () => [] },
    previewUrl: { type: String, required: true },
});

const config = { uploadUrl: props.uploadUrl, mediaUrl: props.mediaUrl, videoMaxMb: props.videoMaxMb };
const localeCodes = Object.keys(props.locales);
const short = (code) => (code === 'ka' ? 'ქარ' : code.toUpperCase());
const newId = () => Math.random().toString(36).slice(2, 10);
const isVideo = (src) => /\.(mp4|webm)$/i.test(src ?? '');

const LAYOUTS = {
    picture_text: { label: t('Picture + text'), hint: t('Picture on the left, text on the right') },
    picture: { label: t('Big picture'), hint: t('One big picture, short text under it') },
    text: { label: t('Only text'), hint: t('A slide with text only') },
    sequence: { label: t('Picture sequence'), hint: t('2–6 pictures with arrows: a movement step by step') },
    compare: { label: t('Correct / wrong'), hint: t('Two pictures: how to do it ✓ and how not to ✗') },
    video: { label: t('Video'), hint: t('A video stored on this computer') },
};
const NOTES = {
    info: { icon: 'bi-info-circle-fill', label: t('Info'), color: 'primary' },
    tip: { icon: 'bi-lightbulb-fill', label: t('Tip'), color: 'success' },
    warning: { icon: 'bi-exclamation-triangle-fill', label: t('Warning'), color: 'warning' },
    danger: { icon: 'bi-x-octagon-fill', label: t('Danger'), color: 'danger' },
};

const PLACES = { picture_text: 1, picture: 1, sequence: 3, compare: 2 }; // empty picture places a new layout gets

// --- State -------------------------------------------------------------------------------------------------

const emptyTexts = () => Object.fromEntries(localeCodes.map((code) => [code, { title: '', body: '', note: '', captions: {} }]));

/** Server shape {media: [...]} → editor shape: pictures and the video kept apart, so switching layouts loses nothing. */
function load(slide) {
    const media = slide.media ?? [];
    const texts = emptyTexts();
    for (const code of localeCodes) Object.assign(texts[code], slide.texts?.[code] ?? {});
    for (const code of localeCodes) texts[code].captions = { ...(texts[code].captions ?? {}) };
    const images = media.filter((m) => !isVideo(m.src)).map((m) => ({ id: m.id ?? newId(), src: m.src.split('/').pop() }));
    const video = media.find((m) => isVideo(m.src));
    return fillPlaces({
        key: newId(),
        layout: slide.layout ?? 'picture_text',
        light: !!slide.light,
        images,
        video: video ? { id: video.id, src: video.src.split('/').pop() } : { id: newId(), src: null },
        note: slide.note ?? null,
        link: slide.link ?? null,
        texts,
    });
}

const state = reactive({
    articles: JSON.parse(JSON.stringify(props.articles)),
    image: props.image,
    slides: props.slides.map(load),
});
const selected = ref(0);
const current = computed(() => state.slides[selected.value] ?? null);

/** Pictures the layout shows. */
function slotsFor(slide) {
    return slide.layout === 'sequence' ? slide.images.slice(0, 6) : slide.images.slice(0, Math.min(PLACES[slide.layout] ?? 0, 2));
}

function fillPlaces(slide) {
    while (slide.images.length < (PLACES[slide.layout] ?? 0)) slide.images.push({ id: newId(), src: null });
    return slide;
}

function setLayout(layout) {
    current.value.layout = layout;
    fillPlaces(current.value);
}

// --- Slides: add, move, copy, delete -----------------------------------------------------------------------

const editorEl = ref(null);

function addSlide(index = state.slides.length) {
    state.slides.splice(index, 0, load({ layout: 'picture_text' }));
    select(index);
}

function select(index) {
    selected.value = index;
    nextTick(() => {
        const top = editorEl.value?.getBoundingClientRect().top ?? 0;
        if (top < 0 || top > window.innerHeight * 0.6) editorEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

function move(by) {
    const from = selected.value;
    const to = from + by;
    if (to < 0 || to >= state.slides.length) return;
    const [slide] = state.slides.splice(from, 1);
    state.slides.splice(to, 0, slide);
    selected.value = to;
}

function duplicate() {
    const copy = JSON.parse(JSON.stringify(current.value));
    copy.key = newId();
    state.slides.splice(selected.value + 1, 0, copy);
    select(selected.value + 1);
}

function removeSlide() {
    if (hasContent(current.value) && !window.confirm(t('Delete this slide?'))) return;
    state.slides.splice(selected.value, 1);
    selected.value = Math.min(selected.value, state.slides.length - 1);
}

function hasContent(slide) {
    return slide.images.some((i) => i.src) || slide.video.src
        || localeCodes.some((code) => Object.values(slide.texts[code]).some((v) => (typeof v === 'string' ? v.trim() : Object.keys(v).length)));
}

// Drag a thumbnail to another place.
const dragFrom = ref(null);
const dragOver = ref(null);
function onDrop(index) {
    if (dragFrom.value !== null && dragFrom.value !== index) {
        const [slide] = state.slides.splice(dragFrom.value, 1);
        state.slides.splice(index, 0, slide);
        selected.value = index;
    }
    dragFrom.value = dragOver.value = null;
}

// --- Thumbnails --------------------------------------------------------------------------------------------

const adminLocale = document.documentElement.lang in props.locales ? document.documentElement.lang : localeCodes[0];

function thumbTitle(slide) {
    for (const code of [adminLocale, ...localeCodes]) {
        const text = slide.texts[code];
        if (text.title.trim()) return text.title;
        if (text.body.trim()) return text.body.replace(/!\[[^\]]*\]\([^)]*\)|[#*>_-]/g, '').trim().slice(0, 60);
    }
    return '';
}

function thumbImage(slide) {
    if (slide.layout === 'text') return null;
    const src = slide.layout === 'video' ? null : slotsFor(slide).find((i) => i.src)?.src;
    return src ? props.mediaUrl.replace('__FILE__', src) : null;
}

/** Languages the tip is written in (has a title) but this slide has no text in, while another language has. */
function missingLanguages(slide) {
    const written = localeCodes.filter((code) => slide.texts[code].title.trim() || slide.texts[code].body.trim());
    if (!written.length) return [];
    return localeCodes.filter((code) => state.articles[code].title.trim() && !written.includes(code));
}

const tipTitle = (tip) => (tip.title || `#${tip.id}`) + (tip.published ? '' : ` (${t('hidden')})`);

// --- Leaving with unsaved changes --------------------------------------------------------------------------

const dirty = ref(false);
let submitting = false;
watch(state, () => { dirty.value = true; }, { deep: true });
const onBeforeUnload = (event) => {
    if (dirty.value && !submitting) {
        event.preventDefault();
        event.returnValue = '';
    }
};
const onSubmit = (event) => { if (!event.submitter?.formTarget) submitting = true; }; // "Preview" opens another tab: still unsaved
const root = ref(null);
onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload);
    root.value?.closest('form')?.addEventListener('submit', onSubmit);
});
onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload));

// --- What is sent ------------------------------------------------------------------------------------------

const serialized = computed(() => JSON.stringify(state.slides.map((slide) => ({
    id: slide.key,
    layout: slide.layout,
    light: slide.light,
    media: slide.layout === 'video'
        ? (slide.video.src ? [slide.video] : [])
        : slotsFor(slide).filter((i) => i.src),
    note: slide.note,
    link: slide.link,
    texts: slide.texts,
}))));
</script>

<template>
    <div ref="root" class="tipx">
        <input type="hidden" name="image" :value="state.image ?? ''">
        <input type="hidden" name="slides" :value="serialized">
        <input type="hidden" name="preview_slide" :value="selected">

        <!-- 1. Start screen -->
        <section class="card mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="tipx-step">1</span>
                <div>
                    <div class="fw-semibold">{{ t('Start screen') }}</div>
                    <div class="small text-body-secondary">{{ t('What visitors see when they tap the tip, before they press "Start".') }}</div>
                </div>
            </div>
            <div class="card-body">
                <div v-if="errors.articles" class="alert alert-danger py-2"><i class="bi bi-exclamation-circle"></i> {{ errors.articles }}</div>
                <div class="row g-3">
                    <div class="col-lg-4">
                        <label class="form-label">{{ t('Cover picture') }} <span class="badge text-bg-light border fw-normal">{{ t('all languages') }}</span></label>
                        <MediaPicker v-model="state.image" :config="config" />
                        <div class="form-text">{{ t('On the tip card and as the background of the start screen. A landscape photo works best.') }}</div>
                    </div>
                    <div class="col-lg-8">
                        <div class="tipx-langs">
                            <div v-for="(language, code) in locales" :key="code">
                                <div class="tipx-lang">{{ language }}</div>
                                <label class="form-label small mb-1" :for="`title-${code}`">{{ t('Title') }}</label>
                                <input :id="`title-${code}`" v-model="state.articles[code].title" :name="`articles[${code}][title]`" maxlength="255" :lang="code"
                                       :class="['form-control fw-semibold', { 'is-invalid': errors[`articles.${code}.title`] }]" :placeholder="t('e.g. Spotting at the start of the route')">
                                <div class="invalid-feedback">{{ errors[`articles.${code}.title`] }}</div>
                                <label class="form-label small mb-1 mt-2" :for="`short-${code}`">{{ t('Short description') }}</label>
                                <textarea :id="`short-${code}`" v-model="state.articles[code].short" :name="`articles[${code}][short_description]`" rows="3" maxlength="500" :lang="code"
                                          class="form-control" :placeholder="t('One or two sentences: what will they learn?')"></textarea>
                            </div>
                        </div>
                        <div class="form-text">{{ t('A language without a title is not shown; the tablet then uses the other language.') }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. Slides -->
        <section class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="tipx-step">2</span>
                <div class="me-auto">
                    <div class="fw-semibold">{{ t('Slides') }} <span class="badge text-bg-secondary">{{ state.slides.length }}</span></div>
                    <div class="small text-body-secondary">{{ t('After "Start", visitors swipe through the slides one by one.') }}</div>
                </div>
            </div>
            <div v-if="errors.slides" class="alert alert-danger rounded-0 mb-0 py-2"><i class="bi bi-exclamation-circle"></i> {{ errors.slides }}</div>

            <div class="tipx-body">
                <!-- Thumbnails -->
                <aside class="tipx-rail">
                    <button v-for="(slide, index) in state.slides" :key="slide.key" type="button"
                            :class="['tipx-thumb', { active: index === selected, over: dragOver === index && dragFrom !== index }]"
                            draggable="true" @click="select(index)"
                            @dragstart="dragFrom = index" @dragover.prevent="dragOver = index" @drop.prevent="onDrop(index)" @dragend="dragFrom = dragOver = null">
                        <span class="tipx-thumb-num">{{ index + 1 }}</span>
                        <span class="tipx-thumb-pic">
                            <img v-if="thumbImage(slide)" :src="thumbImage(slide)" alt="">
                            <i v-else :class="['bi', slide.layout === 'video' ? 'bi-play-btn' : slide.layout === 'text' ? 'bi-text-paragraph' : 'bi-image']"></i>
                        </span>
                        <span class="tipx-thumb-text">
                            <span>{{ thumbTitle(slide) || t('Empty slide') }}</span>
                            <small>{{ LAYOUTS[slide.layout].label }}
                                <span v-for="code in missingLanguages(slide)" :key="code" class="text-danger" :title="t('No text in :language', { language: locales[code] })">
                                    · <i class="bi bi-exclamation-circle"></i> {{ short(code) }}
                                </span>
                            </small>
                        </span>
                    </button>
                    <button type="button" class="tipx-add" @click="addSlide()"><i class="bi bi-plus-lg"></i> {{ t('Add slide') }}</button>
                </aside>

                <!-- The chosen slide -->
                <div ref="editorEl" class="tipx-editor">
                    <div v-if="!current" class="text-center text-body-secondary py-5">
                        <i class="bi bi-collection-play fs-1 d-block mb-2"></i>
                        <p>{{ t('No slides yet. A slide is one screen of the tip: a picture and a short text.') }}</p>
                        <button type="button" class="btn btn-primary" @click="addSlide()"><i class="bi bi-plus-lg"></i> {{ t('Add the first slide') }}</button>
                    </div>

                    <template v-else>
                        <div class="tipx-toolbar">
                            <h5 class="mb-0 me-auto">{{ t('Slide :n of :total', { n: selected + 1, total: state.slides.length }) }}</h5>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" :disabled="selected === 0" :title="t('Move up')" @click="move(-1)"><i class="bi bi-arrow-up"></i></button>
                                <button type="button" class="btn btn-outline-secondary" :disabled="selected === state.slides.length - 1" :title="t('Move down')" @click="move(1)"><i class="bi bi-arrow-down"></i></button>
                                <button type="button" class="btn btn-outline-secondary" :title="t('Duplicate')" @click="duplicate"><i class="bi bi-copy"></i></button>
                                <button type="button" class="btn btn-outline-danger" :title="t('Delete')" @click="removeSlide"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>

                        <!-- Layout -->
                        <div class="tipx-part">
                            <div class="tipx-part-title">{{ t('Layout') }}</div>
                            <div class="tipx-layouts">
                                <button v-for="(meta, layout) in LAYOUTS" :key="layout" type="button" :class="['tipx-layout', { active: current.layout === layout }]" :title="meta.hint" @click="setLayout(layout)">
                                    <svg viewBox="0 0 60 40" aria-hidden="true">
                                        <template v-if="layout === 'picture_text'"><rect x="4" y="6" width="24" height="28" rx="3" class="pic" /><rect x="33" y="9" width="22" height="4" rx="2" /><rect x="33" y="17" width="20" height="2.5" rx="1" /><rect x="33" y="22" width="22" height="2.5" rx="1" /><rect x="33" y="27" width="16" height="2.5" rx="1" /></template>
                                        <template v-else-if="layout === 'picture'"><rect x="10" y="3" width="40" height="25" rx="3" class="pic" /><rect x="14" y="31" width="32" height="2.5" rx="1" /><rect x="18" y="35.5" width="24" height="2.5" rx="1" /></template>
                                        <template v-else-if="layout === 'text'"><rect x="10" y="6" width="34" height="4" rx="2" /><rect x="10" y="14" width="40" height="2.5" rx="1" /><rect x="10" y="19" width="38" height="2.5" rx="1" /><rect x="10" y="24" width="40" height="2.5" rx="1" /><rect x="10" y="29" width="26" height="2.5" rx="1" /></template>
                                        <template v-else-if="layout === 'sequence'"><rect x="3" y="7" width="14" height="16" rx="2" class="pic" /><path d="M19 15l3-2v4z" /><rect x="23" y="7" width="14" height="16" rx="2" class="pic" /><path d="M39 15l3-2v4z" /><rect x="43" y="7" width="14" height="16" rx="2" class="pic" /><rect x="12" y="29" width="36" height="2.5" rx="1" /></template>
                                        <template v-else-if="layout === 'compare'"><rect x="4" y="4" width="24" height="22" rx="3" class="pic good" /><rect x="32" y="4" width="24" height="22" rx="3" class="pic bad" /><path d="M11 15l3 3 6-6" class="mark" /><path d="M40 11l8 8M48 11l-8 8" class="mark" /><rect x="12" y="31" width="36" height="2.5" rx="1" /></template>
                                        <template v-else><rect x="8" y="3" width="44" height="27" rx="3" class="pic" /><path d="M26 10l10 6.5-10 6.5z" class="play" /><rect x="16" y="34" width="28" height="2.5" rx="1" /></template>
                                    </svg>
                                    <span>{{ meta.label }}</span>
                                </button>
                            </div>
                            <div class="form-text">{{ LAYOUTS[current.layout].hint }}</div>
                        </div>

                        <!-- Pictures / video (all languages) -->
                        <div v-if="current.layout !== 'text'" class="tipx-part">
                            <div class="tipx-part-title">
                                {{ current.layout === 'video' ? t('Video') : t('Pictures') }}
                                <span class="badge text-bg-light border fw-normal">{{ t('all languages') }}</span>
                            </div>

                            <div v-if="current.layout === 'video'" class="row g-3">
                                <div class="col-md-7"><MediaPicker v-model="current.video.src" kind="video" :config="config" /></div>
                                <div class="col-md-5">
                                    <label class="form-label small">{{ t('Caption') }}</label>
                                    <input v-for="code in localeCodes" :key="code" v-model="current.texts[code].captions[current.video.id]" class="form-control form-control-sm mb-1"
                                           maxlength="500" :lang="code" :placeholder="short(code)">
                                    <div class="form-text">{{ t('MP4 up to :size MB. It plays without internet.', { size: videoMaxMb }) }}</div>
                                </div>
                            </div>

                            <div v-else :class="['tipx-pictures', `layout-${current.layout}`]">
                                <template v-for="(item, i) in slotsFor(current)" :key="item.id">
                                    <i v-if="current.layout === 'sequence' && i > 0" class="bi bi-caret-right-fill tipx-arrow"></i>
                                    <div :class="['tipx-picture', current.layout === 'compare' ? (i === 0 ? 'good' : 'bad') : '']">
                                        <div v-if="current.layout === 'compare'" class="fw-semibold small mb-1">
                                            <i :class="['bi', i === 0 ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger']"></i>
                                            {{ i === 0 ? t('Correct') : t('Wrong') }}
                                        </div>
                                        <div v-else-if="current.layout === 'sequence'" class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="badge text-bg-secondary">{{ i + 1 }}</span>
                                            <button v-if="current.images.length > 2" type="button" class="btn btn-sm btn-link text-danger p-0" :title="t('Remove')" @click="current.images.splice(i, 1)"><i class="bi bi-x-lg"></i></button>
                                        </div>
                                        <MediaPicker v-model="item.src" :config="config" :light="current.light" :compact="current.layout === 'sequence'" />
                                        <input v-for="code in localeCodes" :key="code" v-model="current.texts[code].captions[item.id]" class="form-control form-control-sm mt-1"
                                               maxlength="500" :lang="code" :placeholder="`${t('Caption')} · ${short(code)}`">
                                    </div>
                                </template>
                                <button v-if="current.layout === 'sequence' && current.images.length < 6" type="button" class="btn btn-outline-secondary tipx-picture-add"
                                        @click="current.images.push({ id: newId(), src: null })">
                                    <i class="bi bi-plus-lg"></i><span class="small">{{ t('Picture') }}</span>
                                </button>
                            </div>
                            <div v-if="current.layout !== 'video'" class="form-check mt-2">
                                <input :id="`light-${current.key}`" v-model="current.light" class="form-check-input" type="checkbox">
                                <label class="form-check-label small" :for="`light-${current.key}`">{{ t('Drawings: show on a white background') }}</label>
                            </div>
                        </div>

                        <!-- Texts, side by side per language -->
                        <div class="tipx-part">
                            <div class="tipx-part-title">{{ t('Text') }}</div>
                            <div class="tipx-langs">
                                <div v-for="(language, code) in locales" :key="code">
                                    <div class="tipx-lang">{{ language }}</div>
                                    <input v-model="current.texts[code].title" class="form-control fw-semibold mb-2" maxlength="255" :lang="code" :placeholder="t('Slide title, e.g. 1. Do a partner check')">
                                    <MarkdownField v-model="current.texts[code].body" :config="config" :rows="current.layout === 'text' ? 9 : 6" :lang="code"
                                                   :placeholder="t('What to do and why. Empty line = new paragraph, **bold**, - list')" />
                                </div>
                            </div>
                        </div>

                        <!-- Note box + more info -->
                        <div class="tipx-part">
                            <div class="tipx-part-title">{{ t('Extras') }}</div>
                            <label class="form-label small">{{ t('Note box') }}</label>
                            <div class="btn-group btn-group-sm flex-wrap mb-2">
                                <button type="button" :class="['btn', current.note ? 'btn-outline-secondary' : 'btn-secondary']" @click="current.note = null">{{ t('None') }}</button>
                                <button v-for="(meta, variant) in NOTES" :key="variant" type="button"
                                        :class="['btn', current.note === variant ? `btn-${meta.color}` : `btn-outline-${meta.color}`]" @click="current.note = variant">
                                    <i :class="['bi', meta.icon]"></i> {{ meta.label }}
                                </button>
                            </div>
                            <div v-if="current.note" :class="['tipx-langs tipx-note', `border-${NOTES[current.note].color}`]">
                                <div v-for="(language, code) in locales" :key="code">
                                    <div class="tipx-lang">{{ language }}</div>
                                    <MarkdownField v-model="current.texts[code].note" :rows="2" :lang="code" :placeholder="t('e.g. **Never let go of the brake rope.**')" />
                                </div>
                            </div>

                            <label class="form-label small mt-2"><i class="bi bi-link-45deg"></i> {{ t('"More info" button: open another tip') }}</label>
                            <select v-model="current.link" class="form-select form-select-sm">
                                <option :value="null">—</option>
                                <option v-for="tip in tips" :key="tip.id" :value="tip.id">{{ tipTitle(tip) }}</option>
                            </select>
                        </div>

                        <div class="tipx-footer">
                            <button type="button" class="btn btn-primary" @click="addSlide(selected + 1)"><i class="bi bi-plus-lg"></i> {{ t('Add next slide') }}</button>
                            <button v-if="selected < state.slides.length - 1" type="button" class="btn btn-outline-secondary" @click="select(selected + 1)">
                                {{ t('Next slide') }} <i class="bi bi-chevron-right"></i>
                            </button>
                            <button class="btn btn-outline-dark ms-auto" :formaction="previewUrl" formtarget="tip-preview" formnovalidate>
                                <i class="bi bi-tablet-landscape"></i> {{ t('Preview this slide') }}
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    </div>
</template>
