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
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="app-section-title">{{ result.parcel.order_id }}</h2><span class="text-xs font-bold uppercase tracking-wider rounded-lg px-2.5 py-1 border border-teal-200 bg-teal-50 text-teal-800">{{ result.status_label || (result.current_hub ? 'At ' + result.current_hub.name : result.parcel.status.replaceAll('_', ' ')) }}</span></div>
        <dl class="mt-5 grid gap-5 sm:grid-cols-2">
          <div><dt class="text-xs text-slate-500">Collection address</dt><dd class="mt-1 break-words text-sm">{{ result.parcel.collection_address || 'Not recorded' }}</dd></div>
          <div><dt class="text-xs text-slate-500">Delivery address</dt><dd class="mt-1 break-words text-sm">{{ result.parcel.delivery_address || 'Not recorded' }}</dd></div>
          <div><dt class="text-xs text-slate-500">Current location</dt><dd class="mt-1 font-semibold">{{ result.location_label }}</dd></div>
          <div><dt class="text-xs text-slate-500">Next destination</dt><dd class="mt-1 font-semibold">{{ result.next_destination?.name || 'No further movement recorded' }}</dd></div>
        </dl>
        <p class="mt-5 text-sm text-slate-600">{{ result.instruction }}</p>

        <!-- Direct Hub Switch Helpers for Admin -->
        <div v-if="!isStaff && result.current_hub && Number(result.current_hub.id) !== Number(hubId)" class="mt-4 rounded-xl border border-teal-200 bg-teal-50/80 p-4">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-teal-950">This parcel is currently at {{ result.current_hub.name }}</p>
              <p class="text-xs text-teal-800 mt-0.5">Switch your workspace to {{ result.current_hub.name }} to dispatch or move this parcel.</p>
            </div>
            <button type="button" class="shrink-0 app-button-primary !text-xs !py-2 !px-3.5 cursor-pointer" :disabled="busy" @click="switchToHub(result.current_hub.id, result.parcel.order_id)">
              Switch to {{ result.current_hub.name }} & Dispatch
            </button>
          </div>
        </div>

        <div v-if="!isStaff && result.parcel.status === 'IN_HUB_TRANSIT' && result.route?.destination_hub_id && Number(result.route.destination_hub_id) !== Number(hubId)" class="mt-4 rounded-xl border border-teal-200 bg-teal-50/80 p-4">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-teal-950">Shipment is in transit to {{ result.route.destination_hub_name || 'destination hub' }}</p>
              <p class="text-xs text-teal-800 mt-0.5">Switch your workspace to {{ result.route.destination_hub_name || 'destination hub' }} to confirm receiving this parcel.</p>
            </div>
            <button type="button" class="shrink-0 app-button-primary !text-xs !py-2 !px-3.5 cursor-pointer" :disabled="busy" @click="switchToHub(result.route.destination_hub_id, result.parcel.order_id)">
              Switch to {{ result.route.destination_hub_name || 'Destination Hub' }} & Receive
            </button>
          </div>
        </div>

        <template v-if="result.action">
          <label v-if="result.action === 'dispatch'" class="mt-4 block max-w-md text-sm">Transport / manifest reference<input v-model.trim="transportReference" minlength="3" maxlength="100" placeholder="e.g. TRUCK-01 or Van Plate" class="app-input mt-1 w-full" :disabled="busy" /></label>
          <label class="mt-4 flex items-start gap-2 text-sm"><input v-model="confirmed" type="checkbox" :disabled="busy || !fresh" /><span>{{ result.action === 'dispatch' ? 'I confirm the shipment is physically leaving this hub.' : 'I confirm this parcel is physically here at my operating hub.' }}</span></label>
          <button type="button" class="app-button-primary mt-4" :disabled="busy || !fresh || !confirmed || (result.action === 'dispatch' && transportReference.length < 3)" @click="performAction">{{ result.action_label }}</button>
        </template>
        <div v-if="result.can_open_delivery_management" class="mt-4 p-4 rounded-xl border border-teal-200 bg-teal-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <p class="font-bold text-teal-950 text-sm">📦 Parcel is Arrived & Ready to Sort</p>
            <p class="text-xs text-teal-800 mt-0.5">Assign this parcel to its City Delivery Area so a delivery rider can take it.</p>
          </div>
          <a href="/sorting-center/delivery-management" class="app-button-primary !text-xs !py-2 !px-4 text-center">
            Go to Parcel Sorting (Delivery Management) →
          </a>
        </div>
        <div v-if="result.can_open_delivery_assignment" class="mt-4 p-4 rounded-xl border border-indigo-200 bg-indigo-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <p class="font-bold text-indigo-950 text-sm">🛵 Ready for Delivery Assignment</p>
            <p class="text-xs text-indigo-800 mt-0.5">Assign this sorted parcel to an online delivery rider.</p>
          </div>
          <a href="/sorting-center/rider-assignment" class="app-button-primary !text-xs !py-2 !px-4 text-center !bg-indigo-700 hover:!bg-indigo-800">
            Assign Delivery Rider →
          </a>
        </div>
      </section>
      <section v-if="hubId" class="app-panel app-panel--padded">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="app-section-title">Work at this hub</h2>
          <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="app-button-secondary !text-xs !py-1.5 !px-3 font-semibold text-teal-800 border-teal-300 bg-teal-50 hover:bg-teal-100 cursor-pointer" :disabled="busy" @click="openBatchDispatchModal">
              🚚 Batch Transfer Dispatch
            </button>
            <button type="button" class="app-button-secondary !text-xs !py-1.5 !px-3 font-semibold text-indigo-800 border-indigo-300 bg-indigo-50 hover:bg-indigo-100 cursor-pointer" :disabled="busy" @click="openBatchReceiveModal">
              📥 Batch Transfer Receive
            </button>
          </div>
        </div>

        <!-- Batch Dispatch Modal -->
        <div v-if="showBatchDispatch" class="mt-4 rounded-xl border border-teal-300 bg-teal-50/90 p-4">
          <div class="flex items-center justify-between pb-2 border-b border-teal-200">
            <h3 class="font-bold text-teal-950 text-sm">🚚 Dispatch Linehaul Transfer (Batch Manifest)</h3>
            <button type="button" class="text-xs text-slate-500 hover:text-slate-800 font-bold" @click="showBatchDispatch = false">✕ Close</button>
          </div>
          <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="text-xs font-semibold text-slate-700">Destination Hub
              <select v-model="batchDestHubId" class="app-input mt-1 w-full text-xs" :disabled="busy">
                <option value="">Choose Destination Hub</option>
                <option v-for="h in destinationHubs" :key="h.id" :value="h.id">{{ h.name }}</option>
              </select>
            </label>
            <label class="text-xs font-semibold text-slate-700">Truck / Manifest Reference
              <input v-model.trim="batchTransportRef" placeholder="e.g. TRUCK-MNL-LAG-01" class="app-input mt-1 w-full text-xs" :disabled="busy" />
            </label>
          </div>
          <div class="mt-4 flex items-center justify-between">
            <p class="text-xs text-teal-800">Parcels at this hub will be batched into the specified transport vehicle and moved to In Transit.</p>
            <button type="button" class="app-button-primary !text-xs !py-2 !px-4 cursor-pointer" :disabled="busy || !batchDestHubId || batchTransportRef.length < 3" @click="executeBatchDispatch">
              {{ busy ? 'Dispatching…' : 'Dispatch All to Destination' }}
            </button>
          </div>
        </div>

        <!-- Batch Receive Modal -->
        <div v-if="showBatchReceive" class="mt-4 rounded-xl border border-indigo-300 bg-indigo-50/90 p-4">
          <div class="flex items-center justify-between pb-2 border-b border-indigo-200">
            <h3 class="font-bold text-indigo-950 text-sm">📥 Receive Arrived Linehaul Truck (Batch Receive)</h3>
            <button type="button" class="text-xs text-slate-500 hover:text-slate-800 font-bold" @click="showBatchReceive = false">✕ Close</button>
          </div>
          <div class="mt-3">
            <label class="text-xs font-semibold text-slate-700">Truck / Manifest Reference (Optional filter)
              <input v-model.trim="batchReceiveRef" placeholder="e.g. TRUCK-MNL-LAG-01 (leave empty to receive all arriving parcels)" class="app-input mt-1 w-full text-xs" :disabled="busy" />
            </label>
          </div>
          <div class="mt-4 flex items-center justify-between">
            <p class="text-xs text-indigo-800">All parcels en route to this hub matching the manifest will be marked as physically arrived.</p>
            <button type="button" class="app-button-primary !text-xs !py-2 !px-4 !bg-indigo-700 hover:!bg-indigo-800 cursor-pointer" :disabled="busy" @click="executeBatchReceive">
              {{ busy ? 'Receiving…' : 'Confirm Batch Receipt' }}
            </button>
          </div>
        </div>

        <label class="mt-4 block max-w-xs text-sm">Show<select v-model="stage" class="app-input mt-1 w-full" :disabled="busy" @change="changeStage"><option v-for="choice in stages" :key="choice.value" :value="choice.value">{{ choice.label }}</option></select></label>
        <p v-if="fresh && !queue.data.length" class="py-5 text-sm text-slate-600">No matching parcels at this hub.</p>
        <article v-for="journey in queue.data" :key="journey.id" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
          <div class="min-w-0">
            <p class="font-semibold">{{ journey.parcel.order_id }}</p>
            <p class="mt-1 break-words text-sm text-slate-600">{{ journey.origin_hub.name }} → {{ journey.destination_hub.name }}</p>
            <p v-if="['PLACED', 'PREPARING'].includes(journey.parcel.status)" class="mt-1 text-xs text-amber-700 font-medium">
              🏪 Seller: {{ journey.parcel.collection_address }}
              <span class="ml-1 text-slate-500">· Rider: {{ journey.parcel.pickup_rider?.name ? journey.parcel.pickup_rider.name : 'Unassigned (Waiting for pickup)' }}</span>
            </p>
            <p class="mt-1 text-xs font-semibold text-teal-700">{{ journey.parcel.status === 'AT_SORTING_CENTER' ? 'At ' + (journey.origin_hub?.name || 'Hub') : journey.parcel.status.replaceAll('_', ' ') }}</p>
          </div>
          <div class="flex items-center gap-2">
            <a v-if="['PLACED', 'PREPARING'].includes(journey.parcel.status)" href="/sorting-center/pickup-requests" class="app-button-primary !text-xs !py-1.5 !px-3 font-semibold">
              Assign Rider
            </a>
            <button type="button" class="app-button-secondary" :disabled="busy || !fresh" @click="openParcel(journey.parcel.order_id)">Review parcel</button>
          </div>
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
    const showBatchDispatch = ref(false), showBatchReceive = ref(false);
    const batchDestHubId = ref(''), batchTransportRef = ref(''), batchReceiveRef = ref('');
    const destinationHubs = computed(() => (context.value?.hubs || []).filter(h => Number(h.id) !== Number(hubId.value)));
    const stages = [
      { value: 'all', label: 'All active bookings' },
      { value: 'pending_pickup', label: '🏪 Pending Seller Pickups (Tindahan)' },
      { value: 'pickup_inbound', label: 'Incoming pickups' },
      { value: 'dispatch_ready', label: 'Ready to transfer' },
      { value: 'transfer_inbound', label: 'Incoming transfers' },
      { value: 'at_hub', label: 'At this hub' },
      { value: 'out_for_delivery', label: 'Out for delivery' },
    ];
    let disposed = false, generation = 0, requestKey = '';
    const handleRealtimeRefresh = async () => {
      if (disposed || busy.value || !hubId.value) return;
      try {
        queue.value = await readQueue(queue.value?.current_page || 1);
      } catch (_) {}
    };
    onUnmounted(() => {
      disposed = true;
      generation++;
      window.removeEventListener('alona:logistics-realtime', handleRealtimeRefresh);
      window.removeEventListener('alona:realtime-reconnected', handleRealtimeRefresh);
    });
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
    async function switchToHub(targetHubId, parcelRef) {
      if (busy.value || disposed) return;
      hubId.value = targetHubId;
      await refresh();
      if (parcelRef) {
        reference.value = parcelRef;
        await lookup();
      }
    }
    const changeStage = () => { invalidate(); return loadPage(); };
    const openParcel = code => { reference.value = code; return lookup(); };

    function openBatchDispatchModal() {
      showBatchDispatch.value = true;
      showBatchReceive.value = false;
      batchTransportRef.value = '';
      batchDestHubId.value = destinationHubs.value.length === 1 ? destinationHubs.value[0].id : '';
      error.value = '';
      notice.value = '';
    }

    function openBatchReceiveModal() {
      showBatchReceive.value = true;
      showBatchDispatch.value = false;
      batchReceiveRef.value = '';
      error.value = '';
      notice.value = '';
    }

    async function executeBatchDispatch() {
      if (busy.value || !hubId.value || !batchDestHubId.value || batchTransportRef.value.length < 3) return;
      busy.value = true; error.value = ''; notice.value = '';
      try {
        const res = await request('/batch-dispatch', {
          hub_id: Number(hubId.value),
          destination_hub_id: Number(batchDestHubId.value),
          transport_reference: batchTransportRef.value,
        });
        showBatchDispatch.value = false;
        notice.value = res.message || 'Batch dispatch successful.';
        const parcels = await readQueue();
        queue.value = parcels;
      } catch (failure) {
        error.value = failure.message;
      } finally {
        busy.value = false;
      }
    }

    async function executeBatchReceive() {
      if (busy.value || !hubId.value) return;
      busy.value = true; error.value = ''; notice.value = '';
      try {
        const res = await request('/batch-receive', {
          hub_id: Number(hubId.value),
          transport_reference: batchReceiveRef.value || null,
        });
        showBatchReceive.value = false;
        notice.value = res.message || 'Batch receipt successful.';
        const parcels = await readQueue();
        queue.value = parcels;
      } catch (failure) {
        error.value = failure.message;
      } finally {
        busy.value = false;
      }
    }

    onMounted(async () => {
      window.addEventListener('alona:logistics-realtime', handleRealtimeRefresh);
      window.addEventListener('alona:realtime-reconnected', handleRealtimeRefresh);
      await refresh();
      try {
        const urlParams = new URLSearchParams(window.location.search);
        const refParam = urlParams.get('reference');
        if (refParam && refParam.trim()) {
          reference.value = refParam.trim();
          await lookup();
        }
      } catch (e) {
        // Ignore URL lookup failure
      }
    });
    return { context, hubId, reference, result, queue, busy, fresh, error, notice, confirmed, transportReference, stage, stages, isStaff,
      showBatchDispatch, showBatchReceive, batchDestHubId, batchTransportRef, batchReceiveRef, destinationHubs,
      openBatchDispatchModal, openBatchReceiveModal, executeBatchDispatch, executeBatchReceive,
      refresh, lookup, performAction, invalidate, switchHub, switchToHub, changeStage, openParcel, loadPage };
  },
};
</script>
