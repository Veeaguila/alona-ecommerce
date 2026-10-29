<template>
  <sc-layout title="Hubs & Routes" subtitle="Manage your company’s physical hubs and the routes between them.">
    <div class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="app-description max-w-2xl">Register real locations, assign local Riders, then connect the hubs your company can transport between.</p>
        <button type="button" class="app-button-secondary" :disabled="busy" @click="refresh()">{{ busy ? 'Loading…' : 'Refresh' }}</button>
      </div>
      <p v-if="error" role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ error }}</p>
      <p v-if="notice" role="status" class="rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900">{{ notice }}</p>
      <template v-if="network">
        <section class="app-panel app-panel--padded" aria-labelledby="company-hubs-heading">
          <h2 id="company-hubs-heading" class="app-section-title">{{ network.company?.business_name || 'Your company' }} hubs</h2>
          <p class="app-description">This Logistics account manages all your company hubs. Choose where you are operating before recording physical parcel movements.</p>
          <div class="mt-4 flex flex-wrap items-center gap-3" aria-label="Hub workspace">
            <button type="button" :class="activeTab === 'operations' ? 'app-button-primary' : 'app-button-secondary'" :aria-pressed="activeTab === 'operations'" @click="activeTab = 'operations'">Hub work queue</button>
            <button type="button" :class="activeTab === 'setup' ? 'app-button-primary' : 'app-button-secondary'" :aria-pressed="activeTab === 'setup'" @click="activeTab = 'setup'">Manage hubs, Riders & routes</button>
          </div>
          <label class="mt-4 block max-w-md text-sm">I am operating at<select v-model="operatingHub" class="app-input mt-1 w-full" :disabled="busy || !fresh" @change="changeHub"><option value="">All company hubs — view only</option><option v-for="hub in network.hubs" :key="hub.id" :value="hub.id">{{ hub.name }} ({{ hub.code }})</option></select></label>
          <p v-if="!operatingHub" class="mt-2 text-xs text-slate-600">Select a hub to receive or dispatch parcels. Each action records your account and the selected location.</p>
          <div v-if="network.hubs.length" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <button v-for="hub in network.hubs" :key="hub.id" type="button" class="rounded-lg border p-3 text-left text-sm" :class="Number(operatingHub) === hub.id ? 'border-teal-600 bg-teal-50' : 'border-slate-200 bg-white'" :disabled="busy || !fresh" @click="chooseHub(hub.id)">
              <strong class="block break-words">{{ hub.name }}</strong>
              <span class="mt-1 block text-xs text-slate-500">{{ hub.is_active ? 'Accepting new bookings' : 'Paused for new bookings' }}</span>
              <span class="mt-2 block">Pickup arrivals: {{ hub.operation_counts?.pickup_inbound ?? 0 }} · Ready to dispatch: {{ hub.operation_counts?.dispatch_ready ?? 0 }}</span>
              <span class="mt-1 block">Incoming transfers: {{ hub.operation_counts?.transfer_inbound ?? 0 }} · At hub: {{ hub.operation_counts?.at_hub ?? 0 }}</span>
            </button>
          </div>
        </section>
        <p class="text-sm text-slate-600">{{ network.booking_routes_required ? 'New delivery bookings require a complete pickup-to-delivery hub route.' : 'Hub routing is being prepared. Ask the ALONA administrator to activate booking validation after your hubs, Riders and routes are ready.' }}</p>

        <section v-show="activeTab === 'setup'" class="app-panel app-panel--padded" aria-labelledby="hubs-heading">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="hubs-heading" class="app-section-title">Physical hubs</h2>
            <button type="button" class="app-button-secondary" :disabled="busy || !fresh" @click="resetHub">Add hub</button>
          </div>
          <p class="app-description">A service area is a city you serve. A hub is the actual location where parcels are received and dispatched.</p>
          <div class="mt-5 grid gap-6 xl:grid-cols-2">
            <div>
              <p v-if="!network.hubs.length" class="py-6 text-sm text-slate-600">No hubs registered. Add your first actual operating location.</p>
              <ul class="divide-y divide-slate-200">
                <li v-for="hub in network.hubs" :key="hub.id" class="flex items-start justify-between gap-3 py-4">
                  <div><strong>{{ hub.name }}</strong> <span class="text-sm text-slate-500">({{ hub.code }})</span>
                    <p class="mt-1 text-sm text-slate-600">{{ hub.address }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ hub.is_active ? 'Active' : 'Paused for new bookings' }} · Pickup: {{ hub.pickup_city_codes.length }} cities · Delivery: {{ hub.delivery_city_codes.length }} cities</p>
                  </div>
                  <button type="button" class="app-button-secondary" :disabled="busy || !fresh" @click="editHub(hub)">Edit</button>
                </li>
              </ul>
            </div>
            <form class="space-y-3" @submit.prevent="saveHub">
              <h3 class="font-semibold">{{ hubForm.id ? 'Edit hub' : 'Add a hub' }}</h3>
              <div class="grid gap-3 sm:grid-cols-2">
                <label class="text-sm">Hub code<input v-model.trim="hubForm.code" required maxlength="24" pattern="[A-Z0-9][A-Z0-9-]*" class="app-input mt-1 w-full" placeholder="MNL-01" :disabled="busy" /></label>
                <label class="text-sm">Hub name<input v-model.trim="hubForm.name" required maxlength="100" class="app-input mt-1 w-full" placeholder="Manila hub" :disabled="busy" /></label>
              </div>
              <label class="block text-sm">Physical address<textarea v-model.trim="hubForm.address" required maxlength="500" rows="2" class="app-input mt-1 w-full" :disabled="busy"></textarea></label>
              <label class="block text-sm">Hub city / municipality<select v-model="hubForm.city_code" required class="app-input mt-1 w-full" :disabled="busy"><option value="">Choose a city</option><option v-for="city in cities" :key="city.code" :value="city.code">{{ cityLabel(city) }}</option></select></label>
              <label class="block text-sm">Find coverage cities<input v-model="citySearch" type="search" class="app-input mt-1 w-full" placeholder="Search city or province" /></label>
              <div class="grid gap-3 sm:grid-cols-2">
                <label class="text-sm">Pickup cities<select v-model="hubForm.pickup_city_codes" multiple size="5" class="app-input mt-1 w-full" :disabled="busy"><option v-for="city in coverageChoices" :key="city.code" :value="city.code">{{ cityLabel(city) }}</option></select></label>
                <label class="text-sm">Delivery cities<select v-model="hubForm.delivery_city_codes" multiple size="5" class="app-input mt-1 w-full" :disabled="busy"><option v-for="city in coverageChoices" :key="city.code" :value="city.code">{{ cityLabel(city) }}</option></select></label>
              </div>
              <p class="text-xs text-slate-500">Selected: {{ hubForm.pickup_city_codes.length }} pickup / {{ hubForm.delivery_city_codes.length }} delivery cities. Use Ctrl or Command to select several cities.</p>
              <label class="flex items-center gap-2 text-sm"><input v-model="hubForm.is_active" type="checkbox" :disabled="busy" /> Accept new bookings through this hub</label>
              <button class="app-button-primary" :disabled="busy || !fresh || !cities.length">Save hub</button>
            </form>
          </div>
        </section>

        <section v-show="activeTab === 'setup'" class="app-panel app-panel--padded" aria-labelledby="riders-heading">
          <h2 id="riders-heading" class="app-section-title">Riders at each hub</h2>
          <p class="app-description">Assign a Rider to their actual hub. Their <a href="/sorting-center/rider-management" class="underline">delivery areas</a> must also cover the cities they serve. Active jobs must finish or be explicitly handed over before moving hubs.</p>
          <p v-if="!network.riders.length" class="mt-4 text-sm">Add and approve Riders in Rider Management first.</p>
          <div v-for="rider in network.riders" :key="rider.id" class="mt-4 flex flex-wrap items-end gap-3 border-b border-slate-100 pb-4">
            <label class="min-w-48 flex-1 text-sm">{{ rider.name }}<select v-model="riderHubs[rider.id]" class="app-input mt-1 w-full" :disabled="busy"><option value="">No hub assigned</option><option v-for="hub in network.hubs.filter(h => h.is_active)" :key="hub.id" :value="hub.id">{{ hub.name }}</option></select></label>
            <button type="button" class="app-button-secondary" :disabled="busy || !fresh || !riderHubs[rider.id]" @click="assignRider(rider)">Assign hub</button>
          </div>
        </section>

        <section v-show="activeTab === 'setup'" class="app-panel app-panel--padded" aria-labelledby="routes-heading">
          <h2 id="routes-heading" class="app-section-title">Transport routes</h2>
          <p class="app-description">Each route has a direction. Add a separate reverse route when you can also transport the other way. Routes connect hubs in your own company.</p>
          <ul class="mt-3 divide-y divide-slate-200">
            <li v-for="route in network.routes" :key="route.id" class="flex flex-wrap items-center justify-between gap-3 py-3"><p>{{ route.origin_hub.name }} → {{ route.destination_hub.name }} <span class="text-sm text-slate-500">· {{ route.transport_mode }} · {{ route.is_active ? 'Active' : 'Paused' }}</span></p><button type="button" class="app-button-secondary" :disabled="busy || !fresh" @click="editRoute(route)">Edit</button></li>
          </ul>
          <form class="mt-4 grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-4" @submit.prevent="saveRoute">
            <label class="text-sm">Origin hub<select v-model="routeForm.origin_hub_id" required class="app-input mt-1 w-full" :disabled="busy"><option value="">Choose origin</option><option v-for="hub in network.hubs" :key="hub.id" :value="hub.id">{{ hub.name }}</option></select></label>
            <label class="text-sm">Destination hub<select v-model="routeForm.destination_hub_id" required class="app-input mt-1 w-full" :disabled="busy"><option value="">Choose destination</option><option v-for="hub in network.hubs" :key="hub.id" :value="hub.id">{{ hub.name }}</option></select></label>
            <label class="text-sm">Transport<select v-model="routeForm.transport_mode" required class="app-input mt-1 w-full" :disabled="busy"><option value="road">Road</option><option value="sea">Sea</option><option value="air">Air</option><option value="mixed">Road and cargo transport</option></select></label>
            <div class="space-y-2"><label class="flex items-center gap-2 text-sm"><input v-model="routeForm.is_active" type="checkbox" :disabled="busy" /> Accept new bookings</label><button class="app-button-primary" :disabled="busy || !fresh">{{ routeForm.id ? 'Save route changes' : 'Add route' }}</button></div>
          </form>
          <button v-if="routeForm.id" type="button" class="mt-3 text-sm underline" :disabled="busy" @click="resetRoute">Cancel editing</button>
        </section>

        <section v-show="activeTab === 'operations'" class="app-panel app-panel--padded" aria-labelledby="transfers-heading">
          <h2 id="transfers-heading" class="app-section-title">Hub work queue</h2>
          <p class="app-description">Receive pickups at origin, dispatch to the destination hub, then record destination receipt before local delivery assignment.</p>
          <label class="mt-4 block max-w-md text-sm">Work stage<select v-model="stage" class="app-input mt-1 w-full" :disabled="busy || !fresh || !operatingHub" @change="loadJourneys()"><option value="all">All linked bookings</option><option value="pickup_inbound">Pickup arrivals</option><option value="dispatch_ready">Ready to dispatch</option><option value="transfer_inbound">Incoming transfers</option><option value="at_hub">At this hub</option><option value="out_for_delivery">Out for delivery</option></select></label>
          <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="searchJourneys">
            <label class="min-w-48 flex-1 text-sm">Exact tracking number<input v-model.trim="search" maxlength="50" class="app-input mt-1 w-full" :disabled="busy" /></label>
            <button class="app-button-secondary" :disabled="busy || !fresh">Search</button>
          </form>
          <p v-if="!journeys.data.length" class="py-6 text-sm text-slate-600">No matching hub bookings. Existing parcels keep their original workflow; their physical hubs are not assumed.</p>
          <article v-for="journey in journeys.data" :key="journey.id" class="mt-5 border-t border-slate-200 pt-4">
            <div class="flex flex-wrap justify-between gap-2"><strong>{{ journey.parcel.order_id }}</strong><span class="text-sm">{{ journey.parcel.status.replaceAll('_', ' ') }}</span></div>
            <p class="mt-1 text-sm">{{ journey.route_snapshot.origin_hub_name }} → {{ journey.route_snapshot.destination_hub_name }}</p>
            <p class="mt-1 text-xs text-slate-500">Origin received: {{ time(journey.origin_received_at) }} · Dispatched: {{ time(journey.transfer?.dispatched_at) }} · Destination received: {{ time(journey.destination_received_at) }}</p>
            <template v-if="canReceiveOrigin(journey)">
              <label class="mt-3 flex items-start gap-2 text-sm"><input v-model="confirmations[journey.id]" type="checkbox" :disabled="busy" /> I physically received this pickup at its origin hub.</label>
              <button type="button" class="app-button-primary mt-3" :disabled="busy || !fresh || !confirmations[journey.id]" @click="receiveOrigin(journey)">Receive pickup at hub</button>
            </template>
            <template v-else-if="canDispatch(journey)">
              <label class="mt-3 block max-w-md text-sm">Transport / manifest reference<input v-model.trim="references[journey.id]" minlength="3" maxlength="100" class="app-input mt-1 w-full" :disabled="busy" /></label>
              <label class="mt-3 flex items-start gap-2 text-sm"><input v-model="confirmations[journey.id]" type="checkbox" :disabled="busy" /> I confirm the shipment is physically leaving this origin hub.</label>
              <button type="button" class="app-button-primary mt-3" :disabled="busy || !fresh || !confirmations[journey.id] || !references[journey.id]?.trim() || references[journey.id].trim().length < 3" @click="transfer(journey, 'dispatch')">Dispatch shipment</button>
            </template>
            <template v-else-if="canReceive(journey)">
              <label class="mt-3 flex items-start gap-2 text-sm"><input v-model="confirmations[journey.id]" type="checkbox" :disabled="busy" /> I physically received this parcel at its destination hub.</label>
              <button type="button" class="app-button-primary mt-3" :disabled="busy || !fresh || !confirmations[journey.id]" @click="transfer(journey, 'receive')">Receive parcel</button>
            </template>
            <p v-else-if="journey.parcel.status === 'IN_HUB_TRANSIT'" class="mt-2 text-sm text-slate-600">Awaiting physical receipt at the destination hub.</p>
            <p v-else-if="!operatingHub" class="mt-2 text-sm text-slate-600">Choose your operating hub to see available actions.</p>
            <a v-if="journey.destination_received_at && journey.parcel.status === 'AT_SORTING_CENTER'" href="/sorting-center/rider-assignment" class="mt-2 inline-block text-sm underline">Sort and assign the local delivery Rider</a>
          </article>
          <div v-if="journeys.last_page > 1" class="mt-5 flex items-center justify-between gap-3"><button type="button" class="app-button-secondary" :disabled="busy || journeys.current_page <= 1" @click="loadJourneys(journeys.current_page - 1)">Previous</button><span class="text-sm">Page {{ journeys.current_page }} of {{ journeys.last_page }}</span><button type="button" class="app-button-secondary" :disabled="busy || journeys.current_page >= journeys.last_page" @click="loadJourneys(journeys.current_page + 1)">Next</button></div>
        </section>
      </template>
    </div>
  </sc-layout>
</template>

<script>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
import ScLayout from '@/Layouts/ScLayout.vue';
import { createRequestKey } from '@/requestKey.js';

export default {
  components: { ScLayout },
  setup() {
    const network = ref(null), cities = ref([]), journeys = ref({ data: [], current_page: 1, last_page: 1 });
    const busy = ref(false), fresh = ref(false), error = ref(''), notice = ref(''), citySearch = ref(''), search = ref(''), appliedSearch = ref(''), operatingHub = ref('');
    const activeTab = ref('setup'), stage = ref('all');
    const riderHubs = reactive({}), references = reactive({}), confirmations = reactive({});
    const blankHub = () => ({ id: null, code: '', name: '', address: '', city_code: '', pickup_city_codes: [], delivery_city_codes: [], is_active: true });
    const blankRoute = () => ({ id: null, origin_hub_id: '', destination_hub_id: '', transport_mode: 'road', is_active: true });
    const hubForm = reactive(blankHub()), routeForm = reactive(blankRoute());
    let disposed = false, version = 0;
    onUnmounted(() => { disposed = true; version++; });
    watch(operatingHub, () => { Object.keys(confirmations).forEach(key => { confirmations[key] = false; }); });
    const cityLabel = city => [city.name, city.province_name || city.province_code, city.code].filter(Boolean).join(' · ');
    const coverageChoices = computed(() => cities.value.filter(city => hubForm.pickup_city_codes.includes(city.code)
      || hubForm.delivery_city_codes.includes(city.code) || cityLabel(city).toLowerCase().includes(citySearch.value.toLowerCase())));
    async function request(path, method = 'GET', body) {
      const token = localStorage.getItem('logistics_auth_token');
      if (!token) throw new Error('Sign in to your Logistics account to manage hubs.');
      const response = await fetch(path, { method, headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, ...(body ? { 'Content-Type': 'application/json' } : {}) }, ...(body ? { body: JSON.stringify(body) } : {}) });
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        const failure = new Error(response.status === 401 ? 'Your session expired. Sign in again.' : Object.values(data?.errors || {}).flat()[0] || data?.message || 'The request failed. Refresh to check the recorded state.');
        failure.status = response.status; throw failure;
      }
      if (!data || typeof data !== 'object') throw new Error('The server response could not be read. Refresh before continuing.');
      return data;
    }
    const journeyUrl = page => '/api/sorting-center/hub-journeys?' + new URLSearchParams({ search: appliedSearch.value, page: String(page), stage: stage.value, ...(operatingHub.value ? { hub_id: String(operatingHub.value) } : {}) });
    async function refresh(keepNotice = false) {
      if (busy.value || disposed) return;
      busy.value = true; fresh.value = false; error.value = ''; if (!keepNotice) notice.value = '';
      const current = ++version;
      try {
        const [result, locations, parcels] = await Promise.all([request('/api/sorting-center/hub-network'), cities.value.length ? Promise.resolve({ data: cities.value }) : request('/api/addresses/cities'), request(journeyUrl(1))]);
        if (disposed || current !== version) return;
        if (!Array.isArray(result.data?.hubs) || !Array.isArray(result.data?.routes) || !Array.isArray(result.data?.riders) || !Array.isArray(parcels.data?.data)) throw new Error('Hub records could not be read. Refresh before continuing.');
        const choices = locations.data || locations;
        if (!Array.isArray(choices)) throw new Error('City choices could not be read. Refresh before continuing.');
        if (!network.value) activeTab.value = result.data.hubs.length ? 'operations' : 'setup';
        network.value = result.data; cities.value = choices; journeys.value = parcels.data;
        result.data.riders.forEach(rider => { riderHubs[rider.id] = rider.logistics_hub_id || ''; });
        fresh.value = true;
      } catch (failure) { if (!disposed && current === version) error.value = failure.message; }
      finally { if (!disposed) busy.value = false; }
    }
    async function mutate(path, method, body, onSuccess = () => {}) {
      if (busy.value || !fresh.value || disposed) return;
      busy.value = true; error.value = ''; notice.value = '';
      let succeeded = false;
      try { const result = await request(path, method, body); if (disposed) return; if (!Object.hasOwn(result, 'data')) throw new Error('The result is uncertain. Refresh to check whether the action was recorded.'); notice.value = result.message || 'Saved.'; onSuccess(); succeeded = true; }
      catch (failure) { if (!disposed) { error.value = failure.message; if (!failure.status || failure.status >= 500 || failure.status === 401) fresh.value = false; } }
      finally { if (!disposed) busy.value = false; }
      if (succeeded && !disposed) await refresh(true);
    }
    const resetHub = () => { Object.assign(hubForm, blankHub()); citySearch.value = ''; };
    const editHub = hub => { Object.assign(hubForm, { ...hub, pickup_city_codes: [...hub.pickup_city_codes], delivery_city_codes: [...hub.delivery_city_codes] }); citySearch.value = ''; };
    const resetRoute = () => Object.assign(routeForm, blankRoute());
    const editRoute = route => Object.assign(routeForm, { id: route.id, origin_hub_id: route.origin_hub_id, destination_hub_id: route.destination_hub_id, transport_mode: route.transport_mode, is_active: route.is_active });
    const saveHub = () => mutate('/api/sorting-center/hubs' + (hubForm.id ? '/' + hubForm.id : ''), hubForm.id ? 'PUT' : 'POST', { ...hubForm }, resetHub);
    const saveRoute = () => {
      if (String(routeForm.origin_hub_id) === String(routeForm.destination_hub_id)) { error.value = 'Choose two different hubs.'; return; }
      return mutate('/api/sorting-center/hub-routes' + (routeForm.id ? '/' + routeForm.id : ''), routeForm.id ? 'PUT' : 'POST', { ...routeForm }, resetRoute);
    };
    const assignRider = rider => mutate(`/api/sorting-center/riders/${rider.id}/hub`, 'PUT', { hub_id: Number(riderHubs[rider.id]) });
    const canReceiveOrigin = journey => journey.parcel.status === 'PICKED_UP' && !journey.origin_received_at && Number(operatingHub.value) === journey.origin_hub_id;
    const receiveOrigin = journey => {
      if (!canReceiveOrigin(journey) || !confirmations[journey.id]) return;
      return mutate('/api/sorting-center/parcels/mark-arrived', 'POST', { order_id: journey.parcel.order_id, hub_id: Number(operatingHub.value), physical_confirmation: true }, () => { confirmations[journey.id] = false; });
    };
    const canDispatch = journey => journey.parcel.status === 'AT_SORTING_CENTER' && !journey.parcel.assigned_rider_id && journey.origin_received_at && !journey.transfer && journey.origin_hub_id !== journey.destination_hub_id && Number(operatingHub.value) === journey.origin_hub_id;
    const canReceive = journey => journey.parcel.status === 'IN_HUB_TRANSIT' && journey.transfer && !journey.transfer.received_at && Number(operatingHub.value) === journey.destination_hub_id;
    const transfer = (journey, action) => {
      if (!confirmations[journey.id] || !(action === 'dispatch' ? canDispatch(journey) : action === 'receive' && canReceive(journey))) return;
      const body = { hub_id: Number(operatingHub.value), physical_confirmation: true };
      if (action === 'dispatch') {
        const reference = references[journey.id]?.trim();
        if (!reference || reference.length < 3 || reference.length > 100) { error.value = 'Enter the actual transport or manifest reference.'; return; }
        body.transport_reference = reference;
        try { body.request_key = createRequestKey(); }
        catch (failure) { error.value = failure.message; return; }
      }
      return mutate(`/api/sorting-center/hub-journeys/${journey.parcel_id}/${action}`, 'POST', body, () => { confirmations[journey.id] = false; });
    };
    async function loadJourneys(page = 1) {
      if (busy.value || disposed || !fresh.value) return;
      busy.value = true; error.value = ''; const current = ++version;
      journeys.value = { data: [], current_page: 1, last_page: 1 };
      try { const result = await request(journeyUrl(page)); if (disposed || current !== version) return; if (!Array.isArray(result.data?.data)) throw new Error('Transfer records could not be read.'); journeys.value = result.data; }
      catch (failure) { if (!disposed && current === version) { error.value = failure.message; fresh.value = false; } }
      finally { if (!disposed) busy.value = false; }
    }
    const searchJourneys = () => { if (busy.value) return; appliedSearch.value = search.value; return loadJourneys(); };
    const changeHub = () => { stage.value = 'all'; return loadJourneys(); };
    const chooseHub = hubId => { if (busy.value || !fresh.value) return; operatingHub.value = hubId; activeTab.value = 'operations'; return changeHub(); };
    const time = value => value ? new Date(value).toLocaleString('en-PH', { timeZone: 'Asia/Manila' }) : 'Not recorded';
    onMounted(() => { refresh(); });
    return { network, cities, journeys, busy, fresh, error, notice, citySearch, search, operatingHub, riderHubs, references, confirmations, hubForm, routeForm, coverageChoices, cityLabel,
      activeTab, stage, changeHub, chooseHub, canReceiveOrigin, receiveOrigin, refresh, resetHub, editHub, saveHub, resetRoute, editRoute, saveRoute, assignRider, canDispatch, canReceive, transfer, loadJourneys, searchJourneys, time };
  },
};
</script>
