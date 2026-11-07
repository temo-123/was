<script setup>
/**
 * Picture / video field of the tech tip editor: click or drop a file, it is uploaded at once; v-model = file name.
 * A picture can also come from a link: the server downloads it and keeps its own copy (TipMedia::download).
 */
import { computed, nextTick, ref } from 'vue';
import { t } from '../../i18n';

const props = defineProps({
    modelValue: { type: String, default: null },
    kind: { type: String, default: 'image' }, // image | video
    config: { type: Object, required: true },
    light: { type: Boolean, default: false }, // show on white, like on the tablet
    compact: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const input = ref(null);
const progress = ref(null);
const error = ref(null);
const dragging = ref(false);
const linkOpen = ref(false);
const link = ref('');
const linkInput = ref(null);
const loadingLink = ref(false);

const url = computed(() => (props.modelValue ? props.config.mediaUrl.replace('__FILE__', props.modelValue.split('/').pop()) : null));
const accept = computed(() => (props.kind === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp,image/gif,image/svg+xml'));

function openLink() {
    linkOpen.value = true;
    error.value = null;
    nextTick(() => linkInput.value?.focus());
}

async function fromLink() {
    const address = link.value.trim();
    if (!address) return;
    error.value = null;
    progress.value = null;
    loadingLink.value = true;
    try {
        const response = await window.axios.post(props.config.uploadUrl, { url: address });
        emit('update:modelValue', response.data.src);
        linkOpen.value = false;
        link.value = '';
    } catch (e) {
        error.value = e.response?.data?.errors?.url?.[0] ?? e.response?.data?.message ?? t('Upload failed. Try again.');
    } finally {
        loadingLink.value = false;
    }
}

async function upload(file) {
    if (!file) return;
    error.value = null;
    if (props.kind === 'video' && file.size > props.config.videoMaxMb * 1024 * 1024) {
        error.value = t('The file is too big (max :size MB).', { size: props.config.videoMaxMb });
        return;
    }
    const data = new FormData();
    data.append('file', file);
    data.append('kind', props.kind);
    progress.value = 0;
    try {
        const response = await window.axios.post(props.config.uploadUrl, data, {
            onUploadProgress: (e) => { progress.value = e.total ? Math.round((e.loaded / e.total) * 100) : null; },
        });
        emit('update:modelValue', response.data.src);
    } catch (e) {
        const status = e.response?.status;
        error.value = status === 413
            ? t('The file is too big for the server (php.ini upload_max_filesize / post_max_size).')
            : (e.response?.data?.errors?.file?.[0] ?? e.response?.data?.message ?? t('Upload failed. Try again.'));
    } finally {
        progress.value = null;
        if (input.value) input.value.value = '';
    }
}

function onDrop(event) {
    dragging.value = false;
    upload(event.dataTransfer.files[0]);
}
</script>

<template>
    <div>
        <div :class="['tip-media', { 'tip-media-light': light, 'tip-media-compact': compact, 'is-dragging': dragging, 'has-file': url }]"
             @click="!url && progress === null && input.click()"
             @dragover.prevent="dragging = true" @dragleave="dragging = false" @drop.prevent="onDrop">
            <template v-if="url">
                <video v-if="kind === 'video'" :src="url" controls preload="metadata"></video>
                <img v-else :src="url" alt="">
                <div class="tip-media-actions">
                    <button type="button" class="btn btn-sm btn-light" :title="t('Replace')" @click.stop="input.click()"><i class="bi bi-arrow-repeat"></i></button>
                    <button v-if="kind === 'image'" type="button" class="btn btn-sm btn-light" :title="t('Picture from a link')" @click.stop="openLink"><i class="bi bi-link-45deg"></i></button>
                    <button type="button" class="btn btn-sm btn-light text-danger" :title="t('Remove')" @click.stop="emit('update:modelValue', null)"><i class="bi bi-x-lg"></i></button>
                </div>
            </template>
            <div v-else class="tip-media-empty">
                <i :class="['bi', kind === 'video' ? 'bi-play-btn' : 'bi-cloud-arrow-up']"></i>
                <span>{{ kind === 'video' ? t('Click or drop a video (MP4)') : t('Click or drop a picture') }}</span>
                <button v-if="kind === 'image'" type="button" class="btn btn-sm btn-link p-0" @click.stop="openLink"><i class="bi bi-link-45deg"></i> {{ t('or paste a link') }}</button>
            </div>
            <div v-if="loadingLink" class="tip-media-progress"><span class="spinner-border spinner-border-sm text-primary"></span></div>
            <div v-if="progress !== null" class="tip-media-progress">
                <div class="progress w-75" style="height: 8px"><div class="progress-bar progress-bar-striped progress-bar-animated" :style="{ width: (progress ?? 100) + '%' }"></div></div>
            </div>
        </div>
        <div v-if="linkOpen" class="input-group input-group-sm mt-1">
            <input ref="linkInput" v-model="link" type="url" class="form-control" placeholder="https://…/picture.jpg"
                   :disabled="loadingLink" @keydown.enter.prevent="fromLink" @keydown.esc.prevent="linkOpen = false">
            <button type="button" class="btn btn-primary" :disabled="loadingLink || !link.trim()" @click="fromLink">{{ t('Add') }}</button>
            <button type="button" class="btn btn-outline-secondary" :title="t('Cancel')" @click="linkOpen = false"><i class="bi bi-x-lg"></i></button>
        </div>
        <div v-if="error" class="small text-danger mt-1"><i class="bi bi-exclamation-circle"></i> {{ error }}</div>
        <input ref="input" type="file" class="d-none" :accept="accept" @change="upload($event.target.files[0])">
    </div>
</template>
