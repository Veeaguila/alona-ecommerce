<template>
  <sc-layout title="Hub workspace">
    <div class="space-y-6">
      <section class="app-panel app-panel--padded">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div><h2 class="app-section-title">Receive and move parcels</h2><p class="mt-2 text-sm text-slate-600">Enter or scan the existing waybill code, review its destination, then confirm the physical movement.</p></div>
          <button type="button" class="app-button-secondary" :disabled="busy" @click="refresh">Refresh</button>
        </div>
        <p v-if="isStaff" class="mt-4 text-sm"><strong>Assigned hub:</strong> {{ context?.operating_hub?.name }}</p>
        <label v-else class="mt-4 block max-w-md text-sm">Your operating hub<select v-model="hubId" class="app-input mt-1 w-full" :disabled="busy" @change="switchHub"><option value="">Choose a hub</option><option v-for="hub in context?.hubs || []" :key="hub.id" :value="hub.id">{{ hub.name }}</option></select></label>
        <p v-if="context && !context.hubs.length" class="mt-3 text-sm">No active hub is available. <a v-if="!isStaff" href="/sorting-center/hubs" class="underline">Set up an actual hub first.</a></p>
        <form class="mt-5 flex flex-wrap items-end gap-3" @submit.prevent="lookup">
          <label class="min-w-0 flex-1 text-sm">Waybill / tracking code<input v-model.trim="reference" required maxlength="50" autocomplete="off" spellcheck="false" placeholder="ITEM-123" class="app-input mt-1 w-full" :disabled="busy || !hubId" @input="invalidate" /></label>
          <button class="app-button-primary" :disabled="busy || !fresh || !hubId || !reference">{{ busy ? 'Checking…' : 'Find parcel' }}</button>
        </form>
        <p class="mt-2 text-xs text-slate-500">A USB barcode scanner can type into the same field. Confirming a scan does not record a parcel movement.</p>
      </section>
      <div v-if="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ error }}</div>
      <div v-if="notice" role="status" class="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900">{{ notice }}</div>
      <section v-if="result" class="app-panel app-panel--padded" aria-label="Parcel details">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="app-section-title">{{ result.parcel.order_id }}</h2><span class="text-sm">{{ result.parcel.status.replaceAll('_', ' ') }}</span></div>
        <dl class="mt-5 grid gap-5 sm:grid-cols-2">
          <div><dt class="text-xs text-slate-500">Collection address</dt><dd class="mt-1 break-words text-sm">{{ result.parcel.collection_address || 'Not recorded' }}</dd></div>
          <div><dt class="text-xs text-slate-500">Delivery address</dt><dd class="mt-1 break-words text-sm">{{ result.parcel.delivery_address || 'Not recorded' }}</dd></div>
          <div><dt class="text-xs text-slate-500">Current location</dt><dd class="mt-1 font-semibold">{{ result.location_label }}</dd></div>
          <div><dt class="text-xs text-slate-500">Next destination</dt><dd class="mt-1 font-semibold">{{ result.next_destination?.name || 'No further movement recorded' }}</dd></div>
        </dl>
        <p class="mt-5 text-sm text-slate-600">{{ result.instruction }}</p>
        <template v-if="result.action">
          <label v-if="result.action === 'dispatch'" class="mt-4 block max-w-md text-sm">Transport / manifest reference<input v-model.trim="transportReference" minlength="3" maxlength="100" class="app-input mt-1 w-full" :disabled="busy" /></label>
          <label class="mt-4 flex items-start gap-2 text-sm"><input v-model="confirmed" type="checkbox" :disabled="busy || !fresh" /><span>{{ result.action === 'dispatch' ? 'I confirm the shipment is physically leaving this hub.' : 'I confirm this parcel is physically here at my operating hub.' }}</span></label>
          <button type="button" class="app-button-primary mt-4" :disabled="busy || !fresh || !confirmed || (result.action === 'dispatch' && transportReference.length < 3)" @click="performAction">{{ result.action_label }}</button>
        </template>
        <a v-if="result.can_open_delivery_assignment" href="/sorting-center/rider-assignment" class="mt-4 inline-block text-sm underline">Open delivery assignment</a>
      </section>
      <section v-if="hubId" class="app-panel app-panel--padded">
        <h2 class="app-section-title">Work at this hub</h2>
        <label class="mt-4 block max-w-xs text-sm">Show<select v-model="stage" class="app-input mt-1 w-full" :disabled="busy" @change="changeStage"><option v-for="choice in stages" :key="choice.value" :value="choice.value">{{ choice.label }}</option></select></label>
        <p v-if="fresh && !queue.data.length" class="py-5 text-sm text-slate-600">No matching parcels at this hub.</p>
        <article v-for="journey in queue.data" :key="journey.id" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
          <div class="min-w-0"><p class="font-semibold">{{ journey.parcel.order_id }}</p><p class="mt-1 break-words text-sm text-slate-600">{{ journey.origin_hub.name }} → {{ journey.destination_hub.name }}</p><p class="mt-1 text-xs text-slate-500">{{ journey.parcel.status.replaceAll('_', ' ') }}</p></div>
          <button type="button" class="app-button-secondary" :disabled="busy || !fresh" @click="openParcel(journey.parcel.order_id)">Review parcel</button>
        </article>
        <div v-if="queue.last_page > 1" class="mt-5 flex items-center justify-between gap-3"><button type="button" class="app-button-secondary" :disabled="busy || queue.current_page <= 1" @click="loadPage(queue.current_page - 1)">Previous</button><span class="text-sm">Page {{ queue.current_page }} of {{ queue.last_page }}</span><button type="button" class="app-button-secondary" :disabled="busy || queue.current_page >= queue.last_page" @click="loadPage(queue.current_page + 1)">Next</button></div>
      </section>
    </div>
  </sc-layout>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import ScLayout from '@/Layouts/ScLayout.vue';
import { createRequestKey } from '@/requestKey.js';

export default {
  components: { ScLayout },
  setup() {
    const context = ref(null), hubId = ref(''), reference = ref(''), result = ref(null), queue = ref({ data: [], current_page: 1, last_page: 1 });
    const busy = ref(false), fresh = ref(false), error = ref(''), notice = ref(''), confirmed = ref(false), transportReference = ref(''), stage = ref('all');
    const isStaff = computed(() => context.value?.operator?.role === 'hub_staff');
    const stages = [{ value: 'all', label: 'All active bookings' }, { value: 'pickup_inbound', label: 'Incoming pickups' }, { value: 'dispatch_ready', label: 'Ready to transfer' }, { value: 'transfer_inbound', label: 'Incoming transfers' }, { value: 'at_hub', label: 'At this hub' }, { value: 'out_for_delivery', label: 'Out for delivery' }];
    let disposed = false, generation = 0, requestKey = '';
    onUnmounted(() => { disposed = true; generation++; });
    function invalidate() { generation++; result.value = null; confirmed.value = false; transportReference.value = ''; requestKey = ''; notice.value = ''; }
    async function request(path, body) {
      const token = localStorage.getItem('logistics_auth_token');
      if (!token) throw new Error('Sign in to your Logistics account first.');
      const response = await fetch('/api/sorting-center/hub-workspace' + path, { method: body ? 'POST' : 'GET', headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, ...(body ? { 'Content-Type': 'application/json' } : {}) }, ...(body ? { body: JSON.stringify(body) } : {}) });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error(Object.values(data?.errors || {}).flat()[0] || data?.message || 'The request could not be confirmed. Refresh the recorded parcel state.');
      if (!data?.data) throw new Error('The response could not be read. Refresh before continuing.');
      return data.data;
    }
    const query = values => '?' + new URLSearchParams({ hub_id: String(hubId.value), ...values });
    async function readQueue(page = 1) {
      const data = await request('/queue' + query({ stage: stage.value, page: String(page) }));
      if (!Array.isArray(data.data)) throw new Error('The work queue could not be read.');
      return data;
    }
    async function refresh() {
      if (busy.value || disposed) return;
      invalidate(); busy.value = true; fresh.value = false; error.value = '';
      const stamp = generation;
      try {
        const data = await request(hubId.value ? query({}) : '');
        if (!Array.isArray(data.hubs)) throw new Error('Hub details could not be read.');
        if (disposed || stamp !== generation) return;
        context.value = data;
        hubId.value = data.operating_hub?.id || (data.hubs.length === 1 ? data.hubs[0].id : '');
        const parcels = hubId.value ? await readQueue() : { data: [], current_page: 1, last_page: 1 };
        if (disposed || stamp !== generation) return;
        queue.value = parcels; fresh.value = true;
      } catch (failure) { if (!disposed && stamp === generation) error.value = failure.message; }
      finally { if (!disposed) busy.value = false; }
    }
    async function lookup() {
      if (busy.value || !fresh.value || !hubId.value || disposed) return;
      const refValue = reference.value.trim();
      if (!/^[A-Za-z0-9][A-Za-z0-9._-]{0,49}$/.test(refValue)) { error.value = 'Enter the tracking code printed on the waybill, such as ITEM-123.'; return; }
      invalidate(); busy.value = true; error.value = '';
      const stamp = generation, selectedHub = String(hubId.value);
      try {
        const data = await request('/lookup' + query({ reference: refValue }));
        if (disposed || stamp !== generation || selectedHub !== String(hubId.value) || reference.value.trim() !== refValue) return;
        if (!data.parcel?.order_id || !data.operating_hub || String(data.operating_hub.id) !== selectedHub) throw new Error('Parcel details do not match your current operating hub. Refresh before continuing.');
        const key = createRequestKey(); result.value = data; requestKey = key;
      } catch (failure) { if (!disposed && stamp === generation) { result.value = null; error.value = failure.message; } }
      finally { if (!disposed) busy.value = false; }
    }
    async function performAction() {
      if (busy.value || !fresh.value || !result.value?.action || !confirmed.value || disposed) return;
      if (result.value.action === 'dispatch' && transportReference.value.length < 3) return;
      const body = { reference: result.value.parcel.order_id, hub_id: Number(hubId.value), action: result.value.action, physical_confirmation: true,
        ...(result.value.action === 'dispatch' ? { request_key: requestKey, transport_reference: transportReference.value } : {}) };
      busy.value = true; error.value = ''; notice.value = ''; const stamp = generation;
      try {
        const data = await request('/actions', body);
        if (disposed || stamp !== generation) return;
        if (data.parcel?.order_id !== body.reference || Number(data.operating_hub?.id) !== body.hub_id) throw new Error('The result is uncertain. Refresh the recorded parcel state.');
        result.value = data; confirmed.value = false; transportReference.value = ''; requestKey = createRequestKey(); notice.value = 'Parcel movement recorded.';
        const parcels = await readQueue(); if (!disposed && stamp === generation) queue.value = parcels;
      } catch (failure) {
        if (!disposed && stamp === generation) { error.value = failure.message; fresh.value = false; result.value = null; confirmed.value = false; }
      } finally { if (!disposed) busy.value = false; }
    }
    async function loadPage(page = 1) {
      if (busy.value || !hubId.value || disposed) return;
      busy.value = true; error.value = ''; const stamp = generation;
      try { const data = await readQueue(page); if (!disposed && stamp === generation) queue.value = data; }
      catch (failure) { if (!disposed) { error.value = failure.message; fresh.value = false; invalidate(); } }
      finally { if (!disposed) busy.value = false; }
    }
    const switchHub = () => { reference.value = ''; stage.value = 'all'; return refresh(); };
    const changeStage = () => { invalidate(); return loadPage(); };
    const openParcel = code => { reference.value = code; return lookup(); };
    onMounted(refresh);
    return { context, hubId, reference, result, queue, busy, fresh, error, notice, confirmed, transportReference, stage, stages, isStaff,
      refresh, lookup, performAction, invalidate, switchHub, changeStage, openParcel, loadPage };
  },
};
</script>
