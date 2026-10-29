<template>
  <sc-layout title="Hub staff">
    <div class="space-y-6">
      <p class="text-sm text-slate-600">Create a staff login for a person working at one of your hubs. Your company account keeps control of hubs, routes and Riders.</p>
      <div v-if="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ error }}</div>
      <div v-if="notice" role="status" class="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900">{{ notice }}</div>
      <section class="app-panel app-panel--padded">
        <h2 class="app-section-title">Add hub staff</h2>
        <p v-if="loaded && !hubs.length" class="mt-3 text-sm">Add an active hub in <a href="/sorting-center/hubs" class="underline">Hubs &amp; Routes</a> first.</p>
        <form v-else class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="createStaff">
          <label class="text-sm">Staff name<input v-model.trim="form.name" required maxlength="100" autocomplete="name" class="app-input mt-1 w-full" :disabled="busy" /></label>
          <label class="text-sm">Email<input v-model.trim="form.email" type="email" required maxlength="255" autocomplete="off" class="app-input mt-1 w-full" :disabled="busy" /></label>
          <label class="text-sm sm:col-span-2">Assigned hub<select v-model="form.hub_id" required class="app-input mt-1 w-full" :disabled="busy"><option value="">Choose a hub</option><option v-for="hub in hubs" :key="hub.id" :value="hub.id">{{ hub.name }}</option></select></label>
          <label class="text-sm">Initial password<input v-model="form.password" type="password" required minlength="8" autocomplete="new-password" class="app-input mt-1 w-full" :disabled="busy" /></label>
          <label class="text-sm">Confirm password<input v-model="form.password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="app-input mt-1 w-full" :disabled="busy" /></label>
          <p class="text-xs text-slate-500 sm:col-span-2">Use at least 8 characters with uppercase, lowercase, a number and a symbol. Share the initial password privately with this staff member.</p>
          <div class="sm:col-span-2"><button class="app-button-primary" :disabled="busy || !loaded">{{ busy ? 'Saving…' : 'Create staff account' }}</button></div>
        </form>
      </section>
      <section class="app-panel app-panel--padded">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="app-section-title">Staff access</h2><button type="button" class="app-button-secondary" :disabled="busy" @click="load">Refresh</button></div>
        <p v-if="loaded && !staff.length" class="py-5 text-sm text-slate-600">No hub staff accounts yet. Add only the people who need their own login.</p>
        <article v-for="member in staff" :key="member.id" class="mt-4 border-t border-slate-200 pt-4">
          <p class="font-semibold">{{ member.user.name }}</p><p class="mt-1 break-all text-sm text-slate-600">{{ member.user.email }}</p>
          <div class="mt-3 flex flex-wrap items-end gap-3">
            <label class="min-w-48 flex-1 text-sm">Assigned hub<select v-model="edits[member.id].hub_id" class="app-input mt-1 w-full" :disabled="busy"><option v-if="!hubs.some(h => h.id === member.logistics_hub_id)" :value="member.logistics_hub_id">{{ member.hub?.name }} (inactive)</option><option v-for="hub in hubs" :key="hub.id" :value="hub.id">{{ hub.name }}</option></select></label>
            <label class="flex items-center gap-2 py-2 text-sm"><input v-model="edits[member.id].is_active" type="checkbox" :disabled="busy" /> Access enabled</label>
            <button type="button" class="app-button-secondary" :disabled="busy || !loaded" @click="saveStaff(member)">Save access</button>
          </div>
        </article>
      </section>
    </div>
  </sc-layout>
</template>

<script>
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import ScLayout from '@/Layouts/ScLayout.vue';

export default {
  components: { ScLayout },
  setup() {
    const hubs = ref([]), staff = ref([]), busy = ref(false), loaded = ref(false), error = ref(''), notice = ref('');
    const blank = () => ({ name: '', email: '', hub_id: '', password: '', password_confirmation: '' });
    const form = reactive(blank()), edits = reactive({});
    let disposed = false;
    onUnmounted(() => { disposed = true; form.password = ''; form.password_confirmation = ''; });
    async function request(path, method = 'GET', body) {
      const token = localStorage.getItem('logistics_auth_token');
      if (!token) throw new Error('Sign in to your company account first.');
      const response = await fetch(path, { method, headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, ...(body ? { 'Content-Type': 'application/json' } : {}) }, ...(body ? { body: JSON.stringify(body) } : {}) });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error(Object.values(data?.errors || {}).flat()[0] || data?.message || 'Staff access could not be saved. Refresh to check the recorded state.');
      if (!data?.data) throw new Error('The staff response could not be read. Refresh before continuing.');
      return data;
    }
    async function read() {
      const result = await request('/api/sorting-center/hub-staff');
      if (disposed) return;
      if (!Array.isArray(result.data.hubs) || !Array.isArray(result.data.staff)) throw new Error('Staff records could not be read.');
      hubs.value = result.data.hubs; staff.value = result.data.staff;
      staff.value.forEach(member => { edits[member.id] = { hub_id: member.logistics_hub_id, is_active: member.is_active }; });
      loaded.value = true;
    }
    async function load() {
      if (busy.value || disposed) return;
      busy.value = true; error.value = ''; loaded.value = false;
      try { await read(); } catch (failure) { if (!disposed) error.value = failure.message; }
      finally { if (!disposed) busy.value = false; }
    }
    async function mutate(method, path, body) {
      if (busy.value || !loaded.value || disposed) return;
      busy.value = true; error.value = ''; notice.value = '';
      try { const result = await request(path, method, body); if (disposed) return; notice.value = result.message; if (method === 'POST') Object.assign(form, blank()); await read(); }
      catch (failure) { if (!disposed) { loaded.value = false; error.value = failure.message; } }
      finally { if (!disposed) busy.value = false; }
    }
    const createStaff = () => mutate('POST', '/api/sorting-center/hub-staff', { ...form });
    const saveStaff = member => mutate('PUT', `/api/sorting-center/hub-staff/${member.id}`, { ...edits[member.id] });
    onMounted(load);
    return { hubs, staff, form, edits, busy, loaded, error, notice, load, createStaff, saveStaff };
  },
};
</script>
