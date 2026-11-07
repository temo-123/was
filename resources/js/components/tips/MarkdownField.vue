<script setup>
/**
 * Textarea with Markdown buttons (bold, list, numbered list, picture) for the text parts of the tech tip blocks.
 * A picture is uploaded at once and written into the text as ![caption](media:<file> "left|right")
 * (App\Services\Tips\TipBlocks::markdown).
 */
import { computed, nextTick, ref } from 'vue';
import { t } from '../../i18n';

const props = defineProps({
    modelValue: { type: String, default: '' },
    rows: { type: Number, default: 4 },
    placeholder: { type: String, default: '' },
    lang: { type: String, default: null },
    config: { type: Object, default: null }, // uploadUrl + mediaUrl; without it there is no picture button
});
const emit = defineEmits(['update:modelValue']);
const area = ref(null);
const fileInput = ref(null);
const uploading = ref(false);
const uploadError = ref(null);
let position = 'full';
const linkOpen = ref(false);
const link = ref('');
const linkPosition = ref('full');
const linkInput = ref(null);

function openLink() {
    linkOpen.value = true;
    uploadError.value = null;
    nextTick(() => linkInput.value?.focus());
}

/** Picture from a link: the server downloads it (own copy), then it goes into the text like an uploaded one. */
async function insertFromLink() {
    const address = link.value.trim();
    if (!address) return;
    position = linkPosition.value;
    await insertPicture(null, address);
    if (!uploadError.value) {
        linkOpen.value = false;
        link.value = '';
    }
}

function apply(change) {
    const el = area.value;
    const { value, selectionStart: s, selectionEnd: e } = el;
    const [text, start, end] = change(value, s, e);
    emit('update:modelValue', text);
    nextTick(() => { el.focus(); el.setSelectionRange(start, end); });
}

const wrap = (mark) => apply((v, s, e) => {
    const inner = v.slice(s, e) || t('text');
    return [v.slice(0, s) + mark + inner + mark + v.slice(e), s + mark.length, s + mark.length + inner.length];
});

function choosePicture(where) {
    position = where;
    fileInput.value.click();
}

async function insertPicture(file, url = null) {
    if (!file && !url) return;
    uploadError.value = null;
    uploading.value = true;
    const data = new FormData();
    if (file) data.append('file', file);
    else data.append('url', url);
    try {
        const response = await window.axios.post(props.config.uploadUrl, data);
        const title = position === 'full' ? '' : ` "${position}"`;
        apply((v, s, e) => {
            const before = v.slice(0, s).replace(/\n*$/, '');
            const after = v.slice(e).replace(/^\n*/, '');
            const tag = `![${t('caption')}](media:${response.data.src}${title})`;
            const start = before ? before.length + 2 : 0;
            return [(before ? `${before}\n\n` : '') + tag + (after ? `\n\n${after}` : '\n'), start + 2, start + 2 + t('caption').length];
        });
    } catch (e) {
        uploadError.value = e.response?.data?.errors?.file?.[0] ?? e.response?.data?.errors?.url?.[0] ?? e.response?.data?.message ?? t('Upload failed. Try again.');
    } finally {
        uploading.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}

/** Pictures already in the text, shown as thumbnails under it. */
const pictures = computed(() => [...(props.modelValue ?? '').matchAll(/!\[([^\]]*)\]\(media:([A-Za-z0-9]{32}\.\w+)(?:\s+"(left|right)")?\)/g)]
    .map((m) => ({ caption: m[1], url: props.config?.mediaUrl.replace('__FILE__', m[2]), position: m[3] ?? 'full' })));
const POSITIONS = { full: t('full width'), left: t('left of the text'), right: t('right of the text') };

const prefix = (make) => apply((v, s, e) => {
    const from = v.lastIndexOf('\n', s - 1) + 1;
    let to = v.indexOf('\n', e);
    if (to === -1) to = v.length;
    const lines = v.slice(from, to).split('\n').map((line, i) => make(i) + line.replace(/^([-*] |\d+\. )/, ''));
    const block = lines.join('\n');
    return [v.slice(0, from) + block + v.slice(to), from, from + block.length];
});
</script>

<template>
    <div class="tip-md">
        <div class="btn-group btn-group-sm tip-md-toolbar">
            <button type="button" class="btn btn-outline-secondary" :title="t('Bold')" @click="wrap('**')"><i class="bi bi-type-bold"></i></button>
            <button type="button" class="btn btn-outline-secondary" :title="t('List')" @click="prefix(() => '- ')"><i class="bi bi-list-ul"></i></button>
            <button type="button" class="btn btn-outline-secondary" :title="t('Numbered list')" @click="prefix((i) => `${i + 1}. `)"><i class="bi bi-list-ol"></i></button>
            <template v-if="config">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" :disabled="uploading" :title="t('Picture in the text')">
                    <span v-if="uploading" class="spinner-border spinner-border-sm"></span><i v-else class="bi bi-image"></i>
                </button>
                <ul class="dropdown-menu">
                    <li><button type="button" class="dropdown-item" @click="choosePicture('full')"><i class="bi bi-image"></i> {{ t('Picture, full width') }}</button></li>
                    <li><button type="button" class="dropdown-item" @click="choosePicture('left')"><i class="bi bi-text-indent-left"></i> {{ t('Small picture, left of the text') }}</button></li>
                    <li><button type="button" class="dropdown-item" @click="choosePicture('right')"><i class="bi bi-text-indent-right"></i> {{ t('Small picture, right of the text') }}</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button type="button" class="dropdown-item" @click="openLink"><i class="bi bi-link-45deg"></i> {{ t('Picture from a link') }}</button></li>
                </ul>
            </template>
        </div>
        <input v-if="config" ref="fileInput" type="file" class="d-none" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" @change="insertPicture($event.target.files[0])">
        <div v-if="config && linkOpen" class="input-group input-group-sm mb-1">
            <input ref="linkInput" v-model="link" type="url" class="form-control" placeholder="https://…/picture.jpg"
                   :disabled="uploading" @keydown.enter.prevent="insertFromLink" @keydown.esc.prevent="linkOpen = false">
            <select v-model="linkPosition" class="form-select flex-grow-0 w-auto" :disabled="uploading">
                <option v-for="(label, key) in POSITIONS" :key="key" :value="key">{{ label }}</option>
            </select>
            <button type="button" class="btn btn-primary" :disabled="uploading || !link.trim()" @click="insertFromLink">{{ t('Add') }}</button>
            <button type="button" class="btn btn-outline-secondary" :title="t('Cancel')" @click="linkOpen = false"><i class="bi bi-x-lg"></i></button>
        </div>
        <textarea ref="area" class="form-control" :rows="rows" :lang="lang" :placeholder="placeholder"
                  :value="modelValue" @input="emit('update:modelValue', $event.target.value)"></textarea>
        <div v-if="uploadError" class="small text-danger mt-1"><i class="bi bi-exclamation-circle"></i> {{ uploadError }}</div>
        <div v-if="pictures.length" class="tip-md-pictures">
            <figure v-for="(picture, i) in pictures" :key="i">
                <img :src="picture.url" alt="">
                <figcaption>{{ POSITIONS[picture.position] }}</figcaption>
            </figure>
            <span class="small text-body-secondary">{{ t('To remove a picture, delete its ![…](media:…) line from the text.') }}</span>
        </div>
    </div>
</template>
