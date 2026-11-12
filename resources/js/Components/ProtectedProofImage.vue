<template>
  <p v-if="error" role="alert">{{ error }}</p>
  <div v-else-if="imageUrl">
    <img :src="imageUrl" alt="Delivery proof" class="proof-image" />
    <a :href="imageUrl" target="_blank" rel="noopener">View full proof</a>
  </div>
  <p v-else>Loading proof image...</p>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  fileUrl: { type: String, required: true },
  token: { type: String, required: true },
});
const imageUrl = ref('');
const error = ref('');

watch(() => [props.fileUrl, props.token], async ([fileUrl, token], _, onCleanup) => {
  const controller = new AbortController();
  let objectUrl;
  onCleanup(() => {
    controller.abort();
    if (objectUrl) URL.revokeObjectURL(objectUrl);
  });
  imageUrl.value = '';
  error.value = '';
  try {
    const url = new URL(fileUrl, window.location.origin);
    if (!fileUrl || url.origin !== window.location.origin) throw new Error('Invalid proof URL.');
    const response = await fetch(url, {
      headers: { Authorization: `Bearer ${token}` }, signal: controller.signal,
    });
    if (!response.ok) throw new Error('Proof image could not be loaded.');
    const blob = await response.blob();
    if (controller.signal.aborted) return;
    if (!blob.type.startsWith('image/')) throw new Error('Invalid proof image.');
    objectUrl = URL.createObjectURL(blob);
    imageUrl.value = objectUrl;
  } catch (failure) {
    if (!controller.signal.aborted) error.value = failure.message;
  }
}, { immediate: true });
</script>

<style scoped>
.proof-image { max-width: 100%; max-height: 400px; }
</style>
