<script setup>
/**
 * Tech tips on the gym tablet (KioskController). Start screen with topics and tip cards → a tip's start screen
 * (cover, title, short description, "Start") → its slides one by one → "Well done". All tips come in the props in
 * every language, so tapping and switching language need no server. Untouched for `idleSeconds`, it goes back to
 * the start and shows the tips as a slideshow until somebody touches the screen.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import TipSlide from './tips/TipSlide.vue';

const props = defineProps({
    tips: { type: Array, required: true },
    categories: { type: Array, required: true },
    levels: { type: Object, required: true },
    texts: { type: Object, required: true },
    locales: { type: Object, required: true },
    locale: { type: String, required: true },
    gym: { type: String, required: true },
    idleSeconds: { type: Number, default: 90 },
    refreshSeconds: { type: Number, default: 300 },
    dataUrl: { type: String, required: true },
    viewedUrl: { type: String, required: true },
    preview: { type: Boolean, default: false }, // admin "Preview on the tablet": not saved, no idle / refresh / counting
    open: { type: Number, default: null }, // tip to open at start (preview)
    openSlide: { type: Number, default: 0 }, // …at this slide
});

const FONT_SCALES = [1, 1.15, 1.32];
const ATTRACT_SLIDE_MS = 8000;

const tips = ref(props.tips);
const lang = ref(props.locale);
const category = ref(null);
const level = ref(null);
const openId = ref(null);
const view = ref('intro'); // intro | slides | finish (while a tip is open)
const slideIndex = ref(0);
const direction = ref(1); // for the slide animation
const attract = ref(false);
const attractIndex = ref(0);
const fontStep = ref(readFontStep());
const fullscreen = ref(false);
const now = ref(new Date());
const homeEl = ref(null);
const zoomSrc = ref(null);

// --- Texts -------------------------------------------------------------------------------------------------

/** Interface text in the language chosen on the tablet (the server sends all languages, see KioskController::UI_TEXTS). */
function t(key) {
    return props.texts[lang.value]?.[key] ?? key;
}

/** Start screen of the tip in the chosen language, or in another one when it isn't translated. */
function art(tip) {
    return tip.articles[lang.value] ?? Object.values(tip.articles)[0];
}

const categoryByKey = computed(() => Object.fromEntries(props.categories.map((c) => [c.key, c])));
const cat = (tip) => categoryByKey.value[tip.category] ?? { key: tip.category, icon: 'bi-lightbulb', color: '#ff7a1a', label: {} };
const catLabel = (c) => c.label[lang.value] ?? c.key;
const levelLabel = (value) => (value ? props.levels[value]?.[lang.value] : t('For everyone'));
const minutes = (tip) => Math.max(1, Math.round((art(tip).words || 0) / 180 + tip.slides.length * 0.15));

/** Name of a slide in "What's inside": its title, or the beginning of its text. */
function slideTitle(slide) {
    const text = slide.texts[lang.value] ?? Object.values(slide.texts)[0];
    if (!text) return '';
    if (text.title) return text.title;
    const plain = (text.html ?? '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    return plain.length > 48 ? `${plain.slice(0, 48)}…` : plain;
}

/** For "more info" buttons: only tips that are on the tablet. */
function findTip(id) {
    const tip = tips.value.find((item) => item.id === id);
    return tip ? { id, title: art(tip).title, image: tip.image, icon: cat(tip).icon, color: cat(tip).color } : null;
}

// --- Lists -------------------------------------------------------------------------------------------------

const countByCategory = computed(() => {
    const counts = {};
    tips.value.forEach((tip) => { counts[tip.category] = (counts[tip.category] ?? 0) + 1; });
    return counts;
});

const topics = computed(() => props.categories.filter((c) => countByCategory.value[c.key]));
const levelsInUse = computed(() => Object.keys(props.levels).filter((l) => tips.value.some((tip) => String(tip.level) === l)));

const shown = computed(() => tips.value.filter((tip) =>
    (!category.value || tip.category === category.value)
    && (!level.value || String(tip.level) === level.value)));

const current = computed(() => tips.value.find((tip) => tip.id === openId.value) ?? null);
const slide = computed(() => current.value?.slides[slideIndex.value] ?? null);
const related = computed(() => (current.value
    ? tips.value.filter((tip) => tip.category === current.value.category && tip.id !== current.value.id).slice(0, 3)
    : []));

const attractSlides = computed(() => {
    const withImage = tips.value.filter((tip) => tip.image);
    return withImage.length ? withImage : tips.value;
});
const attractTip = computed(() => attractSlides.value[attractIndex.value % Math.max(1, attractSlides.value.length)] ?? null);

function chooseCategory(key) {
    category.value = key;
    homeEl.value?.scrollTo({ top: 0, behavior: 'smooth' });
}

// --- Navigation: card → start screen → slides → finish (the Android back button goes one step back) --------

let depth = 0; // history entries added since the tip list

function remember(state, replace = false) {
    if (replace) {
        history.replaceState(state, '');
    } else {
        history.pushState(state, '');
        depth++;
    }
}

/** A tip's start screen (from a card, or a "more info" button inside another tip). */
function openTip(tip) {
    const fromSlide = openId.value !== null && view.value === 'slides';
    if (fromSlide) history.replaceState({ ...history.state, slide: slideIndex.value }, ''); // "more info": back returns to this slide
    remember({ tip: tip.id, view: 'intro' }, openId.value !== null && !fromSlide);
    openId.value = tip.id;
    view.value = 'intro';
    slideIndex.value = 0;
    zoomSrc.value = null;
}

function openTipById(id) {
    const tip = tips.value.find((item) => item.id === id);
    if (tip) openTip(tip);
}

function start(index = 0) {
    direction.value = 1;
    slideIndex.value = index;
    view.value = 'slides';
    remember({ tip: openId.value, view: 'slides' });
    if (!props.preview) {
        fetch(props.viewedUrl.replace('__ID__', openId.value), { method: 'POST', keepalive: true }).catch(() => {});
    }
}

function goTo(index) {
    if (!current.value) return;
    if (index >= current.value.slides.length) {
        view.value = 'finish';
        remember({ tip: openId.value, view: 'finish' }, true);
        return;
    }
    if (index < 0) return;
    direction.value = index > slideIndex.value ? 1 : -1;
    slideIndex.value = index;
    zoomSrc.value = null;
}

/** From the slides back to the tip's start screen. */
function closeSlides() {
    if (depth > 0) {
        history.back(); // → onPopState
    } else {
        view.value = 'intro';
    }
}

/** Back to the tip list from anywhere. */
function closeTip() {
    zoomSrc.value = null;
    if (openId.value === null) return;
    openId.value = null;
    if (depth > 0) {
        const steps = depth;
        depth = 0;
        history.go(-steps); // → onPopState with no state
    }
}

function onPopState(event) {
    if (zoomSrc.value) zoomSrc.value = null;
    const state = event.state;
    depth = Math.max(0, depth - 1);
    if (!state?.tip) {
        openId.value = null;
        depth = 0;
        return;
    }
    openId.value = state.tip;
    view.value = state.view === 'finish' ? 'intro' : state.view;
    if (view.value === 'slides') slideIndex.value = Math.min(state.slide ?? 0, (current.value?.slides.length ?? 1) - 1);
}

// Swipe left / right on the slides = next / previous.
let touchStart = null;
function onTouchStart(event) {
    const touch = event.touches[0];
    touchStart = { x: touch.clientX, y: touch.clientY, time: Date.now() };
}
function onTouchEnd(event) {
    if (!touchStart) return;
    const touch = event.changedTouches[0];
    const dx = touch.clientX - touchStart.x;
    const dy = touch.clientY - touchStart.y;
    const quick = Date.now() - touchStart.time < 600;
    touchStart = null;
    if (quick && Math.abs(dx) > 80 && Math.abs(dx) > Math.abs(dy) * 2) goTo(slideIndex.value + (dx < 0 ? 1 : -1));
}

// --- Text size (remembered on this tablet) -----------------------------------------------------------------

function readFontStep() {
    try {
        return Math.min(FONT_SCALES.length - 1, Number(localStorage.getItem('tips.fontStep')) || 0);
    } catch {
        return 0;
    }
}
function cycleFont() {
    fontStep.value = (fontStep.value + 1) % FONT_SCALES.length;
    try { localStorage.setItem('tips.fontStep', fontStep.value); } catch { /* private mode */ }
}

// --- Full screen -------------------------------------------------------------------------------------------

function toggleFullscreen() {
    if (document.fullscreenElement) {
        document.exitFullscreen?.();
    } else {
        document.documentElement.requestFullscreen?.({ navigationUI: 'hide' }).catch(() => {});
    }
}
const onFullscreenChange = () => { fullscreen.value = !!document.fullscreenElement; };

// --- Idle: back to the start, then the slideshow -----------------------------------------------------------

let lastTouch = Date.now();
const touched = () => { lastTouch = Date.now(); };
const ACTIVITY_EVENTS = ['pointerdown', 'touchstart', 'wheel', 'keydown'];

function goIdle() {
    closeTip();
    category.value = null;
    level.value = null;
    lang.value = props.locale;
    homeEl.value?.scrollTo({ top: 0 });
    if (tips.value.length) {
        attractIndex.value = Math.floor(Math.random() * attractSlides.value.length);
        attract.value = true;
    }
}

function wake() {
    attract.value = false;
    touched();
}

// --- Fresh data from the admin panel -----------------------------------------------------------------------

async function refresh() {
    try {
        const response = await fetch(props.dataUrl, { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();
        tips.value = data.tips;
        if (openId.value !== null && !current.value) closeTip(); // the open tip was hidden or deleted
        if (current.value && slideIndex.value >= current.value.slides.length) slideIndex.value = 0;
        if (category.value && !countByCategory.value[category.value]) category.value = null;
    } catch {
        // No network for a moment: keep showing what we have.
    }
}

const clock = computed(() => `${String(now.value.getHours()).padStart(2, '0')}:${String(now.value.getMinutes()).padStart(2, '0')}`);

watch(lang, (value) => { document.documentElement.lang = value; });

const timers = [];
onMounted(() => {
    ACTIVITY_EVENTS.forEach((name) => window.addEventListener(name, touched, { passive: true, capture: true }));
    window.addEventListener('popstate', onPopState);
    document.addEventListener('fullscreenchange', onFullscreenChange);
    timers.push(setInterval(() => {
        now.value = new Date();
        if (!props.preview && !attract.value && Date.now() - lastTouch > props.idleSeconds * 1000) goIdle();
    }, 1000));
    timers.push(setInterval(() => { if (attract.value) attractIndex.value += 1; }, ATTRACT_SLIDE_MS));
    if (!props.preview) timers.push(setInterval(refresh, props.refreshSeconds * 1000));
    if (props.open !== null) {
        openTipById(props.open);
        if (props.openSlide > 0 && current.value) nextTick(() => start(Math.min(props.openSlide, current.value.slides.length - 1)));
    }
});
onBeforeUnmount(() => {
    ACTIVITY_EVENTS.forEach((name) => window.removeEventListener(name, touched, { capture: true }));
    window.removeEventListener('popstate', onPopState);
    document.removeEventListener('fullscreenchange', onFullscreenChange);
    timers.forEach(clearInterval);
});
</script>

<template>
    <div class="tt" :style="{ '--tt-scale': FONT_SCALES[fontStep] }">
        <!-- Top bar -->
        <header class="tt-bar">
            <button v-if="current && view === 'slides'" type="button" class="tt-bar-back" @click="closeSlides">
                <i class="bi bi-x-lg"></i><span>{{ t('Close') }}</span>
            </button>
            <button v-else-if="current" type="button" class="tt-bar-back" @click="closeTip">
                <i class="bi bi-arrow-left"></i><span>{{ t('Back') }}</span>
            </button>
            <div v-else class="tt-brand">
                <i class="bi bi-triangle-fill tt-brand-mark"></i>
                <div>
                    <div class="tt-brand-name">{{ gym }}</div>
                    <div class="tt-brand-sub">{{ t('Tech tips') }}</div>
                </div>
            </div>

            <!-- Progress through the slides: tap a segment to jump -->
            <div v-if="current && view === 'slides'" class="tt-progress">
                <div class="tt-progress-title">{{ art(current).title }}</div>
                <div class="tt-progress-bar">
                    <button v-for="(s, i) in current.slides" :key="i" type="button" :class="{ done: i < slideIndex, on: i === slideIndex }" @click="goTo(i)"></button>
                </div>
            </div>

            <div class="tt-bar-tools">
                <span class="tt-clock">{{ clock }}</span>
                <button v-if="current" type="button" class="tt-icon-btn" :title="t('Text size')" @click="cycleFont">
                    <i class="bi bi-fonts"></i><span class="tt-dots"><i v-for="(s, i) in FONT_SCALES" :key="i" :class="{ on: i <= fontStep }"></i></span>
                </button>
                <div class="tt-lang" role="group">
                    <button v-for="(name, code) in locales" :key="code" type="button" :class="{ active: lang === code }" @click="lang = code">
                        {{ code === 'ka' ? 'ქარ' : code.toUpperCase() }}
                    </button>
                </div>
                <button type="button" class="tt-icon-btn" :title="t('Full screen')" @click="toggleFullscreen">
                    <i :class="['bi', fullscreen ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen']"></i>
                </button>
            </div>
        </header>

        <!-- Tip list: topics + cards -->
        <main ref="homeEl" class="tt-home" :aria-hidden="!!current">
            <section class="tt-hero">
                <h1>{{ t('Climbing tips') }}</h1>
                <p>{{ t('Choose a topic and learn something new') }}</p>
            </section>

            <nav v-if="topics.length" class="tt-topics">
                <button type="button" :class="['tt-topic', { active: !category }]" style="--c: #f3efe6" @click="chooseCategory(null)">
                    <i class="bi bi-grid-fill"></i><span>{{ t('All topics') }}</span><b>{{ tips.length }}</b>
                </button>
                <button v-for="c in topics" :key="c.key" type="button" :class="['tt-topic', { active: category === c.key }]"
                        :style="{ '--c': c.color }" @click="chooseCategory(c.key)">
                    <i :class="['bi', c.icon]"></i><span>{{ catLabel(c) }}</span><b>{{ countByCategory[c.key] }}</b>
                </button>
            </nav>

            <div v-if="levelsInUse.length > 1" class="tt-levels">
                <button type="button" :class="{ active: !level }" @click="level = null">{{ t('All levels') }}</button>
                <button v-for="l in levelsInUse" :key="l" type="button" :class="{ active: level === l }" @click="level = l">
                    <span class="tt-level-bars" :data-level="l"><i></i><i></i><i></i></span>{{ levelLabel(l) }}
                </button>
            </div>

            <TransitionGroup v-if="shown.length" tag="section" name="tt-card" class="tt-grid">
                <button v-for="tip in shown" :key="tip.id" type="button" class="tt-card" :style="{ '--c': cat(tip).color }" @click="openTip(tip)">
                    <div class="tt-card-media">
                        <img v-if="tip.image" :src="tip.image" alt="" loading="lazy" decoding="async" draggable="false">
                        <div v-else class="tt-card-placeholder"><i :class="['bi', cat(tip).icon]"></i></div>
                        <span class="tt-pill"><i :class="['bi', cat(tip).icon]"></i> {{ catLabel(cat(tip)) }}</span>
                        <span class="tt-card-count"><i class="bi bi-collection-play"></i> {{ tip.slides.length }}</span>
                    </div>
                    <div class="tt-card-body">
                        <h2>{{ art(tip).title }}</h2>
                        <p v-if="art(tip).short">{{ art(tip).short }}</p>
                        <div class="tt-card-meta">
                            <span><span class="tt-level-bars" :data-level="tip.level || 0"><i></i><i></i><i></i></span>{{ levelLabel(tip.level) }}</span>
                            <span><i class="bi bi-clock"></i> {{ minutes(tip) }} {{ t('min read') }}</span>
                        </div>
                    </div>
                </button>
            </TransitionGroup>

            <div v-else class="tt-empty">
                <i class="bi bi-lightbulb"></i>
                <p>{{ tips.length ? t('No tips on this topic yet.') : t('Tips will appear here soon.') }}</p>
            </div>
        </main>

        <!-- A tip's start screen -->
        <Transition name="tt-slide">
            <section v-if="current && view === 'intro'" :key="`intro-${current.id}`" class="tt-intro" :style="{ '--c': cat(current).color }">
                <img v-if="current.image" class="tt-intro-bg" :src="current.image" alt="" draggable="false">
                <div v-else class="tt-intro-bg tt-card-placeholder"><i :class="['bi', cat(current).icon]"></i></div>
                <div class="tt-intro-shade"></div>

                <div class="tt-intro-content">
                    <div class="tt-intro-main">
                        <div class="tt-article-tags">
                            <span class="tt-pill static"><i :class="['bi', cat(current).icon]"></i> {{ catLabel(cat(current)) }}</span>
                            <span class="tt-tag"><span class="tt-level-bars" :data-level="current.level || 0"><i></i><i></i><i></i></span>{{ levelLabel(current.level) }}</span>
                            <span class="tt-tag"><i class="bi bi-collection-play"></i> {{ current.slides.length }} {{ t('slides') }}</span>
                            <span class="tt-tag"><i class="bi bi-clock"></i> {{ minutes(current) }} {{ t('min read') }}</span>
                        </div>
                        <h1>{{ art(current).title }}</h1>
                        <p v-if="art(current).short" class="tt-lead">{{ art(current).short }}</p>

                        <aside v-if="current.warning" class="tt-safety">
                            <i class="bi bi-shield-exclamation"></i>
                            <div><strong>{{ t('Safety first') }}</strong> {{ t('These tips do not replace training with an instructor. If you are not sure, ask our staff.') }}</div>
                        </aside>

                        <div class="tt-intro-actions">
                            <button type="button" class="tt-start" @click="start(0)"><i class="bi bi-play-fill"></i> {{ t('Start the tip') }}</button>
                            <button type="button" class="tt-nav-btn" @click="closeTip"><i class="bi bi-arrow-left"></i><span>{{ t('Back') }}</span></button>
                        </div>
                    </div>

                    <!-- What's inside: tap a slide to start there -->
                    <nav v-if="current.slides.some((s) => slideTitle(s))" class="tt-toc">
                        <h3>{{ t("What's inside") }}</h3>
                        <button v-for="(s, i) in current.slides" :key="i" type="button" @click="start(i)">
                            <span class="tt-toc-num">{{ i + 1 }}</span><span>{{ slideTitle(s) || '…' }}</span>
                        </button>
                    </nav>
                </div>
            </section>
        </Transition>

        <!-- The slides -->
        <section v-if="current && view === 'slides'" class="tt-show" :style="{ '--c': cat(current).color }"
                 @touchstart.passive="onTouchStart" @touchend.passive="onTouchEnd">
            <Transition :name="direction > 0 ? 'tt-next' : 'tt-prev'">
                <div :key="`${current.id}-${slideIndex}`" class="tt-show-slide">
                    <TipSlide v-if="slide" :slide="slide" :lang="lang" :t="t" :find-tip="findTip"
                              @zoom="(src) => (zoomSrc = src)" @open="openTipById" @activity="touched" />
                </div>
            </Transition>

            <nav class="tt-article-nav">
                <button type="button" class="tt-nav-btn" :disabled="slideIndex === 0" @click="goTo(slideIndex - 1)">
                    <i class="bi bi-chevron-left"></i><span>{{ t('Previous') }}</span>
                </button>
                <span class="tt-nav-count">{{ slideIndex + 1 }} / {{ current.slides.length }}</span>
                <button type="button" class="tt-nav-home" @click="goTo(slideIndex + 1)">
                    <span>{{ slideIndex === current.slides.length - 1 ? t('Finish') : t('Next') }}</span>
                    <i :class="['bi', slideIndex === current.slides.length - 1 ? 'bi-check-lg' : 'bi-chevron-right']"></i>
                </button>
            </nav>
        </section>

        <!-- Done -->
        <Transition name="tt-fade-quick">
            <section v-if="current && view === 'finish'" class="tt-finish" :style="{ '--c': cat(current).color }">
                <div class="tt-finish-badge"><i class="bi bi-check-lg"></i></div>
                <h1>{{ t('Well done!') }}</h1>
                <p>{{ t('You have finished this tip.') }} <b>{{ art(current).title }}</b></p>
                <div class="tt-intro-actions">
                    <button type="button" class="tt-nav-btn" @click="start(0)"><i class="bi bi-arrow-counterclockwise"></i><span>{{ t('Start again') }}</span></button>
                    <button type="button" class="tt-start" @click="closeTip"><i class="bi bi-grid-fill"></i> {{ t('Back to all tips') }}</button>
                </div>
                <section v-if="related.length" class="tt-related">
                    <h3>{{ t('More on this topic') }}</h3>
                    <div class="tt-related-list">
                        <button v-for="tip in related" :key="tip.id" type="button" class="tt-related-item" @click="openTip(tip)">
                            <img v-if="tip.image" :src="tip.image" alt="" loading="lazy" draggable="false">
                            <span v-else class="tt-related-icon"><i :class="['bi', cat(tip).icon]"></i></span>
                            <span>{{ art(tip).title }}</span>
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </section>
            </section>
        </Transition>

        <!-- A picture as big as the screen -->
        <Transition name="tt-fade-quick">
            <div v-if="zoomSrc" class="tt-zoom" @click="zoomSrc = null">
                <img :src="zoomSrc" alt="" draggable="false">
                <button type="button" class="tt-icon-btn tt-zoom-close"><i class="bi bi-x-lg"></i></button>
            </div>
        </Transition>

        <div v-if="preview" class="tt-preview-ribbon"><i class="bi bi-eye"></i> {{ t('Preview — not saved yet') }}</div>

        <!-- Slideshow while nobody uses the tablet -->
        <Transition name="tt-fade">
            <div v-if="attract && attractTip" class="tt-attract" @click="wake">
                <Transition name="tt-fade">
                    <div :key="attractTip.id" class="tt-attract-slide" :style="{ '--c': cat(attractTip).color }">
                        <img v-if="attractTip.image" :src="attractTip.image" alt="" draggable="false">
                        <div v-else class="tt-card-placeholder"><i :class="['bi', cat(attractTip).icon]"></i></div>
                    </div>
                </Transition>
                <div class="tt-attract-shade"></div>
                <div class="tt-attract-brand"><i class="bi bi-triangle-fill tt-brand-mark"></i> {{ gym }} · {{ t('Tech tips') }}</div>
                <div class="tt-attract-text">
                    <Transition name="tt-rise" mode="out-in">
                        <div :key="attractTip.id">
                            <span class="tt-pill static" :style="{ '--c': cat(attractTip).color }"><i :class="['bi', cat(attractTip).icon]"></i> {{ catLabel(cat(attractTip)) }}</span>
                            <h2>{{ art(attractTip).title }}</h2>
                            <p v-if="art(attractTip).short">{{ art(attractTip).short }}</p>
                        </div>
                    </Transition>
                    <div class="tt-attract-cta"><i class="bi bi-hand-index"></i> {{ t('Touch the screen to explore climbing tips') }}</div>
                </div>
            </div>
        </Transition>
    </div>
</template>
