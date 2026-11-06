<script setup>
/** One slide of a tech tip on the tablet, in its layout (App\Services\Tips\TipSlides::LAYOUTS). */
const props = defineProps({
    slide: { type: Object, required: true },
    lang: { type: String, required: true },
    t: { type: Function, required: true },
    findTip: { type: Function, required: true }, // id → { title, image, icon, color } of a tip on the tablet, or null
});
const emit = defineEmits(['zoom', 'open', 'activity']);

const NOTE_ICONS = { info: 'bi-info-circle-fill', tip: 'bi-lightbulb-fill', warning: 'bi-exclamation-triangle-fill', danger: 'bi-x-octagon-fill' };

/** Texts in the chosen language, or in another language when this slide isn't translated. */
function text() {
    return props.slide.texts[props.lang] ?? Object.values(props.slide.texts)[0] ?? { title: '', html: '', note: '', captions: {} };
}
function caption(id) {
    return props.slide.texts[props.lang]?.captions?.[id] ?? Object.values(props.slide.texts).map((t) => t.captions?.[id]).find(Boolean) ?? '';
}

/** Pictures inside the texts are plain HTML: one listener opens them big. */
function onClick(event) {
    const picture = event.target.closest('.tt-inline-pic img');
    if (picture) emit('zoom', picture.src);
}
</script>

<template>
    <div :class="['tt-slide', `layout-${slide.layout}`]" @click="onClick">
        <h2 v-if="slide.layout !== 'picture_text' && text().title" class="tt-slide-title">{{ text().title }}</h2>

        <!-- Pictures / video -->
        <div v-if="slide.media.length" class="tt-slide-media">
            <figure v-if="slide.layout === 'video'">
                <video :src="slide.media[0].src" controls playsinline preload="metadata" @timeupdate="emit('activity')"></video>
                <figcaption v-if="caption(slide.media[0].id)">{{ caption(slide.media[0].id) }}</figcaption>
            </figure>
            <template v-else v-for="(item, i) in slide.media" :key="item.id">
                <i v-if="slide.layout === 'sequence' && i > 0" class="bi bi-caret-right-fill tt-seq-arrow"></i>
                <figure :class="item.mark">
                    <button type="button" :class="['tt-pic', { light: slide.light }]" @click.stop="emit('zoom', item.src)">
                        <img :src="item.src" alt="" draggable="false">
                        <span v-if="slide.layout === 'sequence'" class="tt-seq-num">{{ i + 1 }}</span>
                        <span v-if="item.mark" class="tt-verdict"><i :class="['bi', item.mark === 'good' ? 'bi-check-lg' : 'bi-x-lg']"></i></span>
                        <i v-if="slide.layout !== 'sequence' && !item.mark" class="bi bi-zoom-in tt-zoom-hint"></i>
                    </button>
                    <figcaption v-if="item.mark || caption(item.id)">
                        <b v-if="item.mark">{{ item.mark === 'good' ? t('Correct') : t('Wrong') }}</b><template v-if="item.mark && caption(item.id)"> — </template>{{ caption(item.id) }}
                    </figcaption>
                </figure>
            </template>
        </div>

        <!-- Texts -->
        <div v-if="text().title || text().html || (slide.note && text().note) || (slide.link && findTip(slide.link))" class="tt-slide-text">
            <h2 v-if="slide.layout === 'picture_text' && text().title" class="tt-slide-title">{{ text().title }}</h2>
            <!-- Server-rendered Markdown, raw HTML stripped (TipSlides::markdown) -->
            <div v-if="text().html" class="tt-prose" v-html="text().html"></div>
            <aside v-if="slide.note && text().note" :class="['tt-b-callout', slide.note]">
                <i :class="['bi', NOTE_ICONS[slide.note]]"></i>
                <div class="tt-prose" v-html="text().note"></div>
            </aside>
            <button v-if="slide.link && findTip(slide.link)" type="button" class="tt-b-link" :style="{ '--c': findTip(slide.link).color }" @click.stop="emit('open', slide.link)">
                <img v-if="findTip(slide.link).image" :src="findTip(slide.link).image" alt="" draggable="false">
                <i v-else :class="['bi', findTip(slide.link).icon, 'tt-link-icon']"></i>
                <span><small>{{ t('More info') }}</small>{{ findTip(slide.link).title }}</span>
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>
</template>
