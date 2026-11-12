import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parse } from '@vue/compiler-sfc';
import { compile } from '@vue/compiler-dom';
import * as Vue from 'vue';
import { renderToString } from '@vue/server-renderer';

function loadPage(name, fetch, DateImpl = Date, runtime = {}) {
  const source = readFileSync(new URL(`../../resources/js/Pages/Logistics/${name}.vue`, import.meta.url), 'utf8');
  const { descriptor } = parse(source);
  const script = descriptor.script.content
    .replace(/^import .*;\r?\n/gm, '')
    .replace('export default', 'return');
  const unmountCallbacks = [];
  const mountCallbacks = [];
  const ScLayout = { setup: (_, { slots }) => () => Vue.h('main', slots.default?.()) };
  const events = { addEventListener() {}, removeEventListener() {} };
  const component = new Function('ref', 'reactive', 'onMounted', 'onUnmounted', 'computed', 'ScLayout', 'fetch', 'localStorage', 'Date',
    'window', 'document', 'setInterval', 'clearInterval', 'nextTick', 'onBeforeUnmount', 'Chart', 'URL', 'AbortController', 'DeliveryCoverageSelector', 'ParcelOperationsPanel', 'PickupDispatchPanel', 'ParcelHandoverPanel', 'CodCashPanel', 'ReturnToSellerPanel', script)(
    Vue.ref, Vue.reactive, callback => mountCallbacks.push(callback), callback => unmountCallbacks.push(callback), Vue.computed,
    ScLayout, fetch, { getItem: () => 'test-token' }, DateImpl,
    runtime.window ?? { ...events, location: { search: '' } }, runtime.document ?? { ...events, visibilityState: 'visible' },
    runtime.setInterval ?? setInterval, runtime.clearInterval ?? clearInterval,
    Vue.nextTick, callback => unmountCallbacks.push(callback), class { destroy() {} }, runtime.URL ?? URL, AbortController, {}, {}, {}, {}, {}, {},
  );
  component.render = new Function('Vue', compile(descriptor.template.content, { prefixIdentifiers: true }).code)(Vue);
  return { component, state: component.setup(), mount: () => mountCallbacks.forEach(fn => fn()), unmount: () => unmountCallbacks.forEach(fn => fn()) };
}

function deferred() {
  let resolve;
  const promise = new Promise(done => { resolve = done; });
  return { promise, resolve };
}

test('successful sorting keeps the parcel visible by region without depending on a second network read', async () => {
  const calls=[];
  const sorted={id:1,order_id:'NCR-PARCEL',status:'AT_SORTING_CENTER',sorted_area_id:4,sorted_at:'2026-10-20T00:00:00Z'};
  const {state}=loadPage('DeliveryManagement',async(url,options)=>{
    calls.push({url,options});if(options.method!=='POST')throw Error('Read temporarily unavailable');
    return {ok:true,json:async()=>({data:sorted,message:'Parcel sorted successfully.'})};
  });
  state.areas.value=[{id:4,region_code:'1300000000',region_label:'NCR',city_name:'Quezon City'}];
  state.parcels.value=[{id:1,order_id:'NCR-PARCEL'}];state.selectedAreas[1]=4;
  await state.sortParcel(1);
  assert.equal(state.parcels.value.length,0);assert.equal(calls.length,1);
  assert.equal(state.regionalGroups.value[0].label,'NCR');
  assert.equal(state.regionalGroups.value[0].parcels[0].order_id,'NCR-PARCEL');
  assert.equal(state.feedback.type,'success');
});

test('outbound scan never confirms an edited reference or starts delivery', async () => {
  const calls=[];
  const parcel={order_id:'OUT-1',status:'AT_SORTING_CENTER',sorted_area_id:1,assigned_rider_id:7};
  const {state}=loadPage('OrderManagement',async(url,options)=>{calls.push({url,options});return {ok:true,json:async()=>({data:options.method==='POST'?{parcel,confirmed_at:'2026-10-20T01:00:00Z'}:parcel,message:'Outbound readiness confirmed.'})};});
  state.outboundReference.value='OUT-1';await state.lookupOutbound();
  state.outboundReference.value='OUT-2';await state.confirmOutbound();assert.equal(calls.length,1);
  state.outboundReference.value='OUT-1';await state.confirmOutbound();await state.confirmOutbound();
  assert.equal(calls.length,2);assert.equal(state.outboundParcel.value.status,'AT_SORTING_CENTER');
  assert.equal(state.outboundConfirmed.value,true);assert.deepEqual(JSON.parse(calls[1].options.body),{order_id:'OUT-1'});
});

test('outbound scan discards stale results and displays arrival guard errors', async () => {
  const pending=deferred();const {state}=loadPage('OrderManagement',()=>pending.promise);
  state.outboundReference.value='OUT-1';const lookup=state.lookupOutbound();state.outboundReference.value='OUT-2';state.invalidateOutbound();
  pending.resolve({ok:true,json:async()=>({data:{order_id:'OUT-1',status:'AT_SORTING_CENTER'}})});await lookup;
  assert.equal(state.outboundParcel.value,null);
  const rejected=loadPage('OrderManagement',async()=>({ok:false,status:422,json:async()=>({message:'Physical arrival required.'})}));
  rejected.state.outboundReference.value='PICKED-1';await rejected.state.lookupOutbound();
  assert.match(rejected.state.outboundMessage.value,/Physical arrival required/);assert.equal(rejected.state.outboundBusy.value,false);
});

test('parcel summary lists preserve applied dates, ignore stale bucket results and refresh safely', async () => {
  const first=deferred();let reads=0;const calls=[];
  const {state}=loadPage('Dashboard',async url=>{calls.push(url);if(url.includes('/parcels?')){reads++;if(reads===1)return first.promise;return {ok:true,json:async()=>({data:{data:[{id:3,order_id:'DONE',status:'DELIVERED'}],current_page:1,last_page:1,total:1}})};}return dashboardResponse();});
  state.appliedDates.start='2026-10-17';state.appliedDates.end='2026-10-18';state.startDate.value='2000-01-01';
  const old=state.loadParcels('pending');await state.loadParcels('delivered');
  first.resolve({ok:true,json:async()=>({data:{data:[{id:1}],current_page:1,last_page:2,total:11}})});await old;
  assert.equal(state.summaryParcels.value[0].id,3);assert.equal(state.listTotal.value,1);
  assert.ok(calls[0].includes('start_date=2026-10-17'));assert.ok(!calls[0].includes('2000'));
  await state.loadDashboard();await state.loadDashboard();assert.equal(state.feedback.message,'');
  assert.equal(state.parcelTotals.delivered,2);
});

test('Rider suspension validates reasons, preserves job fields and explicitly reactivates', async () => {
  const calls = [];
  const {state} = loadPage('RiderManagement', async (url, options) => {
    calls.push({url,body:JSON.parse(options.body)});
    return {ok:true,json:async()=>({data:{id:7,approval_status:'approved',is_active:url.endsWith('/activate')},message:'Saved.'})};
  });
  state.riders.value=[{id:7,approval_status:'approved',is_active:true,active_jobs_count:2}];state.selectedRiderId.value=7;
  state.startReview('suspend');
  await state.submitReview();state.rejectionReason.value='123456789';await state.submitReview();
  assert.equal(calls.length,0);assert.match(state.reviewError.value,/suspension reason/);
  state.rejectionReason.value='  Repeated late deliveries this week.  ';await state.submitReview();
  assert.equal(calls[0].url,'/api/sorting-center/riders/7/deactivate');
  assert.deepEqual(calls[0].body,{reason:'Repeated late deliveries this week.'});
  assert.equal(state.selectedRider.value.is_active,false);assert.equal(state.selectedRider.value.active_jobs_count,2);
  state.startReview('reactivate');await state.submitReview();
  assert.equal(calls[1].url,'/api/sorting-center/riders/7/activate');assert.equal(state.selectedRider.value.is_active,true);
});

test('Rider suspension blocks duplicate submissions and retains state on server rejection', async () => {
  const request=deferred();let posts=0;
  const {state}=loadPage('RiderManagement',async()=>{posts++;return request.promise;});
  state.riders.value=[{id:7,approval_status:'approved',is_active:true}];state.selectedRiderId.value=7;
  state.startReview('suspend');state.rejectionReason.value='Repeated late deliveries this week.';
  const first=state.submitReview();await state.submitReview();assert.equal(posts,1);
  request.resolve({ok:false,status:409,json:async()=>({message:'Rider state changed. Refresh.'})});await first;
  assert.equal(state.selectedRider.value.is_active,true);assert.match(state.reviewError.value,/Refresh/);
  assert.equal(state.reviewLoading.value,false);
});

test('Rider review separates unknown presence from availability and renders actual workload without a cap', async () => {
  const {component,state}=loadPage('RiderManagement',async()=>{});
  state.riders.value=[{id:7,name:'Rider Seven',approval_status:'approved',is_active:true,presence:'Unknown',availability:'Eligible for assignments',
    active_jobs_count:4,pickup_jobs_count:1,delivery_jobs_count:3,active_parcels_count:8,completed_jobs_today:2}];
  state.selectedRiderId.value=7;component.setup=()=>state;
  const html=await renderToString(Vue.h(component));
  assert.match(html,/Live presence/);assert.match(html,/>Unknown</);assert.match(html,/Eligible for assignments/);
  assert.match(html,/4 \(1 pickup \/ 3 delivery\)/);assert.match(html,/Completed today \(Manila\)/);
  assert.match(html,/no signal means Unknown/);assert.doesNotMatch(html,/Online<|Offline</);
});

function exportPage(exportResponse) {
  const calls=[], downloads=[], revoked=[];
  const runtime={document:{visibilityState:'visible',createElement:()=>({click(){downloads.push({filename:this.download,url:this.href});},remove(){}}),body:{appendChild(){}}},
    URL:{createObjectURL:()=> 'blob:report',revokeObjectURL:url=>revoked.push(url)}};
  const page=loadPage('DeliveryReports',async(url,options)=>{
    calls.push({url,options});
    if(url.includes('/reports/export?')) return exportResponse(url,options);
    if(url.includes('/reports/transactions?')) return {ok:true,json:async()=>({data:[{parcel_id:2,order_id:'ITEM-2'}],pagination:{total:1,current_page:1,last_page:1}})};
    return {ok:true,json:async()=>({data:{from:'2026-10-01',to:'2026-10-20',total_parcels_processed:1,status_breakdown:{AT_SORTING_CENTER:1}}})};
  },Date,runtime);
  page.state.form.from='2026-10-01';page.state.form.to='2026-10-20';
  return {...page,calls,downloads,revoked};
}

test('delivery export uses applied dates, prevents duplicate clicks and cleans the download URL', async () => {
  const pending=deferred();const page=exportPage(()=>pending.promise);await page.state.generateReport();
  page.state.form.from='2030-01-01';page.state.form.to='2030-01-02';
  const first=page.state.exportReport('csv');await page.state.exportReport('csv');
  const requests=page.calls.filter(c=>c.url.includes('/export?'));assert.equal(requests.length,1);
  const url=new URL(requests[0].url,'http://local');assert.equal(url.searchParams.get('from'),'2026-10-01');assert.equal(url.searchParams.get('dataset'),'deliveries');
  pending.resolve({ok:true,headers:{get:()=> 'text/csv; charset=UTF-8'},blob:async()=>new Blob(['records'])});await first;
  assert.equal(page.downloads[0].filename,'deliveries-2026-10-01-to-2026-10-20.csv');assert.deepEqual(page.revoked,['blob:report']);
  assert.equal(page.state.exporting.value,false);
});

test('delivery export failures and login HTML never create a misleading file', async () => {
  let response={ok:false,json:async()=>({message:'Your account cannot export.'})};const page=exportPage(()=>response);await page.state.generateReport();
  await page.state.exportReport('xlsx');assert.equal(page.downloads.length,0);assert.match(page.state.feedback.message,/cannot export/);
  response={ok:true,headers:{get:()=> 'text/html'}};await page.state.exportReport('csv');
  assert.equal(page.downloads.length,0);assert.match(page.state.feedback.message,/Unexpected export response/);
  assert.equal(page.state.exporting.value,false);
});

test('delivery export aborts on unmount and does not download a late response', async () => {
  const pending=deferred();const page=exportPage(()=>pending.promise);await page.state.generateReport();
  const first=page.state.exportReport('csv');const request=page.calls.at(-1);page.unmount();assert.equal(request.options.signal.aborted,true);
  pending.resolve({ok:true,headers:{get:()=> 'text/csv'},blob:async()=>new Blob(['records'])});await first;
  assert.equal(page.downloads.length,0);assert.equal(page.revoked.length,0);
});

test('coverage manager loads owned choices and saves only an explicit selection, including removal', async () => {
  const calls = [];
  const { state } = loadPage('RiderManagement', async (url, options) => {
    calls.push({url, options});
    return {ok:true,json:async()=>({data: options.method === 'PUT' ? {rider_id:7,area_ids:JSON.parse(options.body).area_ids}
      : {rider_id:7,area_ids:[1],areas:[{id:1,name:'City A'},{id:2,name:'City B'}],can_manage:true}})};
  });
  state.riders.value=[{id:7,approval_status:'approved'}]; state.selectedRiderId.value=7;
  await state.openCoverage();
  assert.equal(calls.length,1); assert.equal(calls[0].options.method,undefined);
  state.coverageIds.value=[2]; await state.saveCoverage();
  assert.deepEqual(JSON.parse(calls[1].options.body),{area_ids:[2]});
  state.coverageIds.value=[]; await state.saveCoverage();
  assert.deepEqual(JSON.parse(calls[2].options.body),{area_ids:[]});
});

test('coverage manager ignores stale Rider responses and blocks duplicate saves', async () => {
  const pending=deferred(); let puts=0;
  const {state}=loadPage('RiderManagement',async (url,options)=>{
    if(options.method==='PUT'){puts++;return pending.promise;}
    return {ok:true,json:async()=>({data:{rider_id:7,areas:[],area_ids:[],can_manage:true}})};
  });
  state.riders.value=[{id:7}];state.selectedRiderId.value=7;await state.openCoverage();
  const save=state.saveCoverage();await state.saveCoverage();await state.loadRiders();assert.equal(puts,1);
  pending.resolve({ok:true,json:async()=>({data:{rider_id:7,area_ids:[]}})});await save;
  const old=deferred();
  const page=loadPage('RiderManagement',()=>old.promise);page.state.riders.value=[{id:7},{id:8}];page.state.selectedRiderId.value=7;
  const loading=page.state.openCoverage();page.state.selectedRiderId.value=8;page.state.closeCoverage();
  old.resolve({ok:true,json:async()=>({data:{rider_id:7,areas:[],area_ids:[],can_manage:true}})});await loading;
  assert.equal(page.state.coverageVisible.value,false);
});

test('coverage manager displays access errors and cannot save ineligible Riders', async () => {
  let canManage=false;
  const {state}=loadPage('RiderManagement',async()=> canManage ? {ok:false,status:403,json:async()=>({message:'Not authorized'})}
    : {ok:true,json:async()=>({data:{rider_id:7,areas:[],area_ids:[],can_manage:false,reason:'Rider inactive'}})});
  state.riders.value=[{id:7}];state.selectedRiderId.value=7;await state.openCoverage();
  assert.equal(state.coverageReason.value,'Rider inactive');await state.saveCoverage();assert.equal(state.coverageSaving.value,false);
  canManage=true;await state.openCoverage();assert.equal(state.coverageError.value,'Not authorized');
});

function response(records, total = records.length, page = 1, last = 1) {
  return { ok: true, json: async () => ({ data: {
    data: records, total, current_page: page, last_page: last,
    prev_page_url: null, next_page_url: null,
  } }) };
}

function incomingResponse(reference = 'ITEM-A', status = 'PICKED_UP') {
  return { ok: true, json: async () => ({ message: 'Parcel received.', data: { order_id: reference, status } }) };
}

function dashboardResponse(value = 2) {
  return { ok: true, json: async () => ({ data: {
    riders_pending_approval: value, pickup_requests_pending: value, parcels_awaiting_sorting: value,
    parcels_awaiting_rider_assignment: value, parcels_out_for_delivery: value, active_approved_riders: value,
    parcel_totals: {pending:value,in_transit:value,at_center:value,delivered:value,failed:value},
  } }) };
}

// LOG-DASH-003: creation-date range controls, safe recovery and explicit Manila Today.
test('dashboard applies both dates, refreshes the applied range despite drafts and clears to all dates', async () => {
  const calls = [];
  const { state, component } = loadPage('Dashboard', async url => { calls.push(url); return dashboardResponse(); });
  state.startDate.value = '2026-10-15'; state.endDate.value = '2026-10-17';
  await state.applyDateFilter();
  const first = new URL(calls[0], 'http://localhost');
  assert.equal(first.searchParams.get('start_date'), '2026-10-15');
  assert.equal(first.searchParams.get('end_date'), '2026-10-17');
  assert.equal(state.hasLoaded.value, true);
  state.startDate.value = '2026-10-01';
  await state.loadDashboard();
  assert.equal(calls[1], calls[0]);
  component.setup = () => state;
  const html = await renderToString(Vue.h(component));
  assert.match(html, /type="date"/);
  assert.match(html, /Applied: 2026-10-15 to 2026-10-17/);
  assert.match(html, /Asia\/Manila/);
  await state.clearDates();
  assert.equal(calls[2], '/api/sorting-center/dashboard');
  assert.equal(state.startDate.value, ''); assert.equal(state.endDate.value, '');
  assert.deepEqual(state.appliedDates, { start: '', end: '' });
});

test('dashboard rejects incomplete reversed and impossible date ranges before requests', async () => {
  let calls = 0;
  const { state } = loadPage('Dashboard', async () => { calls++; return dashboardResponse(); });
  for (const [start, end] of [
    ['2026-10-17', ''], ['', '2026-10-17'], ['2026-10-18', '2026-10-17'],
    ['2026-02-30', '2026-03-01'], ['10/17/2026', '2026-10-17'],
  ]) {
    state.startDate.value = start; state.endDate.value = end;
    await state.applyDateFilter();
    assert.equal(calls, 0); assert.ok(state.feedback.message); assert.equal(state.loading.value, false);
  }
  state.startDate.value = state.endDate.value = '2024-02-29';
  await state.applyDateFilter();
  assert.equal(calls, 1); assert.equal(state.feedback.message, '');
});

test('dashboard Today uses Manila even when the UTC date is still yesterday', async () => {
  class BoundaryClock extends Date {
    constructor(...args) { super(...(args.length ? args : ['2026-10-16T17:00:00Z'])); }
  }
  const calls = [];
  const { state } = loadPage('Dashboard', async url => { calls.push(url); return dashboardResponse(); }, BoundaryClock);
  await state.filterToday();
  assert.equal(state.startDate.value, '2026-10-17'); assert.equal(state.endDate.value, '2026-10-17');
  assert.match(calls[0], /start_date=2026-10-17&end_date=2026-10-17/);
});

test('dashboard date actions prevent overlapping submissions and recover the loading state', async () => {
  const pending = deferred(); let calls = 0;
  const { state } = loadPage('Dashboard', () => { calls++; return pending.promise; });
  state.startDate.value = state.endDate.value = '2026-10-17';
  const first = state.applyDateFilter();
  await state.applyDateFilter(); await state.filterToday(); await state.clearDates();
  assert.equal(calls, 1);
  pending.resolve(dashboardResponse()); await first;
  assert.equal(state.loading.value, false); assert.equal(state.hasLoaded.value, true);
});

test('dashboard shows API and network failures without labelling old counts with a failed new date range', async () => {
  for (const failingFetch of [
    async () => ({ ok: false, json: async () => ({ errors: { end_date: ['End date is invalid.'] } }) }),
    async () => ({ ok: false, json: async () => ({ message: 'Unauthenticated.' }) }),
    async () => { throw new Error('Failed to fetch'); },
    async () => ({ ok: true, json: async () => ({ data: {} }) }),
    async () => ({ ok: false, json: async () => { throw new Error('Unexpected response'); } }),
  ]) {
    let calls = 0;
    const { state, component } = loadPage('Dashboard', (...args) => ++calls === 1 ? Promise.resolve(dashboardResponse()) : failingFetch(...args));
    await state.loadDashboard(); assert.equal(state.hasLoaded.value, true);
    state.startDate.value = state.endDate.value = '2026-10-17';
    await state.applyDateFilter();
    assert.ok(state.feedback.message); assert.equal(state.feedback.type, 'error');
    assert.equal(state.loading.value, false); assert.equal(state.hasLoaded.value, false);
    component.setup = () => state;
    assert.doesNotMatch(await renderToString(Vue.h(component)), /app-card__value/);
  }
});

test('dashboard ignores stale and unmounted responses', async () => {
  const requests = [deferred(), deferred()]; let index = 0;
  const { state, unmount } = loadPage('Dashboard', () => requests[index++].promise);
  const first = state.loadDashboard(); const second = state.loadDashboard();
  requests[1].resolve(dashboardResponse(9)); await second;
  requests[0].resolve(dashboardResponse(1)); await first;
  assert.equal(state.counts.parcels_awaiting_sorting, 9);
  assert.equal(state.loading.value, false);
  const pending = deferred(); const other = loadPage('Dashboard', () => pending.promise);
  const last = other.state.loadDashboard(); other.unmount(); pending.resolve(dashboardResponse(5)); await last;
  assert.equal(other.state.hasLoaded.value, false); unmount();
});

// LOG-TRK-001: approved exact search, independent drafts, filter/page compatibility.
test('monitoring search sends a trimmed encoded term and keeps the applied search across pages and filters', async () => {
  const calls = [];
  const { state, component } = loadPage('DeliveryMonitoring', async url => {
    calls.push(new URL(url, 'http://localhost'));
    return response([{ id: 1, order_id: 'ITEM-SEARCH', status: 'DELIVERED' }]);
  });
  state.searchTerm.value = "  Maria O'Neil & family  ";
  await state.searchParcels();
  assert.equal(calls[0].searchParams.get('search'), "Maria O'Neil & family");
  assert.equal(calls[0].searchParams.get('page'), '1');
  state.searchTerm.value = 'unsubmitted draft';
  state.selectedStatus.value = 'DELIVERED';
  await state.loadParcels(2);
  assert.equal(calls[1].searchParams.get('search'), "Maria O'Neil & family");
  assert.equal(calls[1].searchParams.get('status'), 'DELIVERED');
  assert.equal(calls[1].searchParams.get('page'), '2');
  component.setup = () => state;
  const html = await renderToString(Vue.h(component));
  assert.match(html, /for="parcel-search"/);
  assert.match(html, /Clear search/);
  await state.clearSearch();
  assert.equal(state.searchTerm.value, '');
  assert.equal(state.appliedSearch.value, '');
  assert.equal(calls[2].searchParams.has('search'), false);
  assert.equal(calls[2].searchParams.get('page'), '1');
  assert.equal(calls[2].searchParams.get('status'), 'DELIVERED');
});

test('monitoring search blocks excess length and overlapping submissions', async () => {
  const request = deferred();
  let calls = 0;
  const { state } = loadPage('DeliveryMonitoring', () => { calls++; return request.promise; });
  state.searchTerm.value = 'x'.repeat(256);
  await state.searchParcels();
  assert.equal(calls, 0);
  assert.match(state.feedback.message, /255/);
  state.searchTerm.value = 'ITEM-SEARCH';
  const first = state.searchParcels();
  await state.searchParcels();
  await state.clearSearch();
  assert.equal(calls, 1);
  request.resolve(response([]));
  await first;
  assert.equal(state.loading.value, false);
  assert.equal(state.feedback.message, '');
});

test('monitoring search renders a clear empty state for unknown exact references', async () => {
  const { state, component } = loadPage('DeliveryMonitoring', async () => response([]));
  state.searchTerm.value = 'UNKNOWN';
  await state.searchParcels();
  component.setup = () => state;
  const html = await renderToString(Vue.h(component));
  assert.match(html, /No Parcels Match This Filter/);
  assert.match(html, /another exact reference/);
  assert.equal(state.pagination.total, 0);
  assert.equal(state.loading.value, false);
});

test('monitoring search displays backend validation, authentication, network and malformed-response errors', async () => {
  for (const fetch of [
    async () => ({ ok: false, json: async () => ({ errors: { search: ['Search must be a string.'] } }) }),
    async () => ({ ok: false, json: async () => ({ message: 'Unauthenticated.' }) }),
    async () => { throw new Error('Failed to fetch'); },
    async () => ({ ok: true, json: async () => ({ data: {} }) }),
    async () => ({ ok: false, json: async () => { throw new Error('Unexpected response'); } }),
  ]) {
    const { state } = loadPage('DeliveryMonitoring', fetch);
    state.searchTerm.value = 'ITEM-SEARCH';
    await state.searchParcels();
    assert.ok(state.feedback.message);
    assert.equal(state.feedback.type, 'error');
    assert.equal(state.loading.value, false);
  }
});

test('monitoring discards a stale search response after a newer status request', async () => {
  const requests = [deferred(), deferred()];
  let i = 0;
  const { state } = loadPage('DeliveryMonitoring', () => requests[i++].promise);
  state.searchTerm.value = 'ITEM-SEARCH';
  const search = state.searchParcels();
  state.selectedStatus.value = 'DELIVERED';
  const status = state.loadParcels(1);
  requests[1].resolve(response([{ id: 2, status: 'DELIVERED' }]));
  await status;
  requests[0].resolve(response([{ id: 1, status: 'AT_SORTING_CENTER' }]));
  await search;
  assert.deepEqual(state.parcels.value, [{ id: 2, status: 'DELIVERED' }]);
  assert.equal(state.loading.value, false);
});

// LOG-HUB-001/004/005/007, LOG-TRK-001: bounded inbound interaction coverage.
test('inbound cannot confirm an edited reference instead of the displayed parcel', async () => {
  const calls = [];
  const { state } = loadPage('OrderManagement', async (url, options) => {
    calls.push({ url, options });
    return incomingResponse();
  });
  state.orderId.value = 'ITEM-A';
  await state.searchParcel();
  state.orderId.value = 'ITEM-B';
  await state.confirmArrival();
  assert.equal(calls.length, 1);
  assert.match(state.feedback.message, /search/i);
  assert.equal(state.loading.value, false);
});

test('inbound discards a lookup when input changes before the response', async () => {
  const request = deferred();
  const { state } = loadPage('OrderManagement', () => request.promise);
  state.orderId.value = 'ITEM-A';
  const lookup = state.searchParcel();
  state.orderId.value = 'ITEM-B';
  request.resolve(incomingResponse());
  await lookup;
  assert.equal(state.parcel.value, null);
  assert.equal(state.loading.value, false);
});

test('inbound confirms the canonical lookup reference and blocks overlapping actions', async () => {
  const request = deferred();
  const calls = [];
  const { state } = loadPage('OrderManagement', async (url, options) => {
    calls.push({ url, options });
    return options.method === 'POST' ? request.promise : incomingResponse();
  });
  state.orderId.value = '  ITEM-A  ';
  await state.searchParcel();
  assert.ok(calls[0].url.endsWith('/ITEM-A'));
  const confirm = state.confirmArrival();
  const duplicate = state.confirmArrival();
  const lookup = state.searchParcel();
  request.resolve(incomingResponse('ITEM-A', 'AT_SORTING_CENTER'));
  await Promise.all([confirm, duplicate, lookup]);
  assert.equal(calls.length, 2);
  assert.equal(JSON.parse(calls[1].options.body).order_id, 'ITEM-A');
  assert.equal(state.parcel.value.status, 'AT_SORTING_CENTER');
  assert.equal(state.loading.value, false);
});

test('inbound keeps a failed confirmation recoverable and displays backend errors', async () => {
  const { state } = loadPage('OrderManagement', async (_, options) => options.method === 'POST'
    ? { ok: false, status: 409, json: async () => ({ message: 'This parcel is assigned to another sorting center.' }) }
    : incomingResponse());
  state.orderId.value = 'ITEM-A';
  await state.searchParcel();
  await state.confirmArrival();
  assert.equal(state.parcel.value.order_id, 'ITEM-A');
  assert.match(state.feedback.message, /another sorting center/);
  assert.equal(state.loading.value, false);
});

test('inbound handles authentication, validation and server failures without losing loading recovery', async () => {
  for (const status of [401, 403, 404, 409, 422, 500]) {
    const { state } = loadPage('OrderManagement', async () => ({ ok: false, status,
      json: async () => ({ message: `Synthetic error ${status}` }) }));
    state.orderId.value = 'ITEM-A';
    await state.searchParcel();
    assert.equal(state.feedback.message, `Synthetic error ${status}`);
    assert.equal(state.loading.value, false);
    assert.equal(state.parcel.value, null);
  }
  const { state } = loadPage('OrderManagement', async () => { throw new TypeError('Failed to fetch'); });
  state.orderId.value = 'ITEM-A';
  await state.searchParcel();
  assert.match(state.feedback.message, /reach the server/);
  assert.equal(state.loading.value, false);
  const blank = loadPage('OrderManagement', async () => ({ ok: false, status: 500, json: async () => ({ message: ' ' }) }));
  blank.state.orderId.value = 'ITEM-A';
  await blank.state.searchParcel();
  assert.ok(blank.state.feedback.message.trim().length > 10);
});

test('inbound rejects malformed success data and gives readable non-JSON errors', async () => {
  for (const result of [
    { ok: true, json: async () => ({ data: { status: 'PICKED_UP' } }) },
    { ok: false, status: 500, json: async () => { throw new SyntaxError('Unexpected token <'); } },
  ]) {
    const { state } = loadPage('OrderManagement', async () => result);
    state.orderId.value = 'ITEM-A';
    await state.searchParcel();
    assert.equal(state.parcel.value, null);
    assert.equal(state.feedback.type, 'error');
    assert.doesNotMatch(state.feedback.message, /Unexpected token|undefined/);
    assert.ok(state.feedback.message.length > 10);
    assert.equal(state.loading.value, false);
  }
});

test('inbound ignores responses after unmount and does not submit already-arrived records', async () => {
  const request = deferred();
  const { state, unmount } = loadPage('OrderManagement', () => request.promise);
  state.orderId.value = 'ITEM-A';
  const lookup = state.searchParcel();
  unmount();
  request.resolve(incomingResponse());
  await lookup;
  assert.equal(state.parcel.value, null);

  const calls = [];
  const page = loadPage('OrderManagement', async url => { calls.push(url); return incomingResponse('ITEM-A', 'AT_SORTING_CENTER'); });
  page.state.orderId.value = 'ITEM-A';
  await page.state.searchParcel();
  await page.state.confirmArrival();
  assert.equal(calls.length, 1);
});

function reviewRider(id = 1, userId = 101) {
  return { id, user_id: userId, name: 'Synthetic Rider', user: { name: 'Synthetic Rider', email: 'rider@example.test' },
    vehicle_type: 'bicycle', approval_status: 'pending', is_active: false,
    review_documents: [{ key: 'id_doc', label: 'Government ID', available: true, url: `/partner/documents/${userId}/id_doc` },
      { key: 'license_doc', label: "Driver's License", available: false, url: null }] };
}

function auditResponse(records = [], page = 1, last = 1) {
  return { ok: true, json: async () => ({ data: { data: records, current_page: page, last_page: last,
    prev_page_url: page > 1 ? 'previous' : null, next_page_url: page < last ? 'https://untrusted.invalid/next' : null } }) };
}

test('rider audit viewer requests only the owned selected history and safely renders exact reasons', async () => {
  const calls = [];
  const reason = '<script>alert(1)</script> The photo is unreadable.';
  const { state, component } = loadPage('RiderManagement', async (url, options) => {
    calls.push({ url, options });
    return auditResponse([{ id: 1, action: 'reject', actor_name: 'Fixture Logistics', reason,
      occurred_at: '2026-10-16T16:00:00.000000Z', old_state: { approval_status: 'pending', user_status: 'pending', is_active: false },
      new_state: { approval_status: 'rejected', user_status: 'rejected', is_active: false } }]);
  });
  state.riders.value = [reviewRider(7)]; state.selectedRiderId.value = 7;
  await state.loadAudit();
  assert.equal(calls[0].url, '/api/sorting-center/riders/7/audits?page=1');
  assert.equal(calls[0].options.method, undefined);
  assert.equal(calls[0].options.headers.Authorization, 'Bearer test-token');
  assert.equal(state.audits.value[0].reason, reason);
  component.setup = () => state;
  const html = await renderToString(Vue.h(component));
  assert.match(html, /Rejected by Fixture Logistics/);
  assert.match(html, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
  assert.doesNotMatch(html, /<script>/);
  assert.match(html, /Asia\/Manila/);
  assert.doesNotMatch(html, />Edit audit|>Delete audit/);
});

test('rider audit pagination builds local URLs instead of following server-supplied links', async () => {
  const calls = [];
  const { state } = loadPage('RiderManagement', async url => {
    calls.push(url); return auditResponse([{ id: 1 }], calls.length, 2);
  });
  state.riders.value = [reviewRider(7)]; state.selectedRiderId.value = 7;
  await state.loadAudit(); await state.loadAudit(2);
  assert.equal(calls[1], '/api/sorting-center/riders/7/audits?page=2');
  assert.equal(state.auditPagination.value.current_page, 2);
  assert.equal(state.auditPagination.value.next_page_url, null);
  await state.loadAudit(0); await state.loadAudit('2');
  assert.equal(calls.length, 2);
});

test('rider audit viewer shows empty history and actionable permission storage and network errors', async () => {
  for (const fetch of [
    async () => ({ ok: false, status: 503, json: async () => ({ message: 'Rider decision auditing is unavailable. Contact your administrator.' }) }),
    async () => ({ ok: false, status: 401, json: async () => ({}) }),
    async () => ({ ok: false, status: 403, json: async () => ({}) }),
    async () => { throw new TypeError('Failed to fetch'); },
    async () => ({ ok: true, json: async () => ({ data: [] }) }),
    async () => ({ ok: true, json: async () => { throw new SyntaxError('Unexpected token'); } }),
  ]) {
    const { state } = loadPage('RiderManagement', fetch);
    state.riders.value = [reviewRider(7)]; state.selectedRiderId.value = 7;
    await state.loadAudit();
    assert.ok(state.auditError.value); assert.equal(state.auditLoading.value, false); assert.deepEqual(state.audits.value, []);
  }
  const { state, component } = loadPage('RiderManagement', async () => auditResponse());
  state.riders.value = [reviewRider(7)]; state.selectedRiderId.value = 7;
  await state.loadAudit(); component.setup = () => state;
  assert.match(await renderToString(Vue.h(component)), /No recorded decisions for this rider/);
});

test('rider audit requests cannot expose a stale selected rider or closed history', async () => {
  for (const close of [false, true]) {
    const pending = deferred(); let calls = 0;
    const { state } = loadPage('RiderManagement', () => { calls++; return pending.promise; });
    state.riders.value = [reviewRider(7), reviewRider(8)]; state.selectedRiderId.value = 7;
    const first = state.loadAudit(); await state.loadAudit(); assert.equal(calls, 1);
    if (close) state.closeAudit(); else state.selectedRiderId.value = 8;
    pending.resolve(auditResponse([{ id: 99 }])); await first;
    assert.deepEqual(state.audits.value, []); assert.equal(state.auditLoading.value, false);
  }
});

test('rider audit list refresh and unmount invalidate history responses', async () => {
  for (const dispose of [false, true]) {
    const pending = deferred();
    const { state, unmount } = loadPage('RiderManagement', url => url.includes('/audits') ? pending.promise : Promise.resolve({ ok: true, json: async () => ({ data: [reviewRider(7)] }) }));
    state.riders.value = [reviewRider(7)]; state.selectedRiderId.value = 7;
    const history = state.loadAudit();
    if (dispose) unmount(); else await state.loadRiders();
    pending.resolve(auditResponse([{ id: 99 }])); await history;
    assert.equal(state.auditVisible.value, false); assert.deepEqual(state.audits.value, []);
  }
});

test('rider decision success invalidates old history while missing storage preserves the pending review', async () => {
  for (const available of [true, false]) {
    const rider = reviewRider(7);
    const { state } = loadPage('RiderManagement', async url => url.includes('/audits') ? auditResponse([{ id: 1 }])
      : available ? { ok: true, json: async () => ({ data: { ...rider, approval_status: 'approved', is_active: true } }) }
        : { ok: false, status: 503, json: async () => ({ message: 'Rider decision auditing is unavailable. Contact your administrator.' }) });
    state.riders.value = [rider]; state.selectedRiderId.value = 7;
    await state.loadAudit(); state.startReview('approve'); await state.submitReview();
    assert.equal(state.reviewLoading.value, false);
    if (available) {
      assert.equal(state.auditVisible.value, false); assert.deepEqual(state.audits.value, []);
      assert.equal(state.selectedRider.value.approval_status, 'approved');
    } else {
      assert.match(state.reviewError.value, /auditing is unavailable/);
      assert.equal(state.selectedRider.value.approval_status, 'pending'); assert.equal(state.success.value, '');
    }
  }
});

// LOG-RIDER-001 review subset / LOG-RIDER-007. No new approval/Online contract.
test('rider review filters retain only the newest owned response and clear old selection', async () => {
  const requests = [deferred(), deferred()];
  const calls = [];
  const { state } = loadPage('RiderManagement', url => { calls.push(url); return requests[calls.length - 1].promise; });
  const old = state.loadRiders();
  state.selectedStatus.value = 'approved';
  const current = state.loadRiders();
  requests[1].resolve({ ok: true, json: async () => ({ data: [reviewRider(2)] }) });
  await current;
  requests[0].resolve({ ok: true, json: async () => ({ data: [reviewRider(1)] }) });
  await old;
  assert.ok(calls[1].endsWith('?status=approved'));
  assert.equal(state.selectedRider.value.id, 2);
  assert.equal(state.loading.value, false);
});

test('rider review uses user ID document routes and rejects public or foreign URLs', async () => {
  const { state } = loadPage('RiderManagement', async () => ({ ok: true, json: async () => ({ data: [reviewRider(7, 101)] }) }));
  await state.loadRiders();
  const document = state.selectedRider.value.review_documents[0];
  assert.equal(state.documentUrl(document), '/partner/documents/101/id_doc');
  assert.equal(state.documentUrl({ ...document, url: '/partner/documents/7/id_doc' }), null);
  assert.equal(state.documentUrl({ ...document, url: 'https://example.test/private.pdf' }), null);
  assert.equal(state.documentUrl({ ...document, url: '/storage/private.pdf' }), null);
  assert.equal(state.documentUrl(state.selectedRider.value.review_documents[1]), null);
});

test('rider review exposes clear session errors and safe empty and malformed states', async () => {
  for (const response of [
    { ok: false, status: 401, json: async () => { throw new SyntaxError(); } },
    { ok: true, json: async () => ({ data: {} }) },
    { ok: false, status: 500, json: async () => ({ message: '' }) },
  ]) {
    const { state } = loadPage('RiderManagement', async () => response);
    await state.loadRiders();
    assert.ok(state.error.value.length > 10);
    assert.equal(state.selectedRider.value, null);
    assert.equal(state.loading.value, false);
  }
  const { state } = loadPage('RiderManagement', async () => ({ ok: true, json: async () => ({ data: [] }) }));
  await state.loadRiders();
  assert.equal(state.error.value, '');
  assert.equal(state.selectedRider.value, null);
});

test('document review reports missing and expired files without sending the API bearer token', async () => {
  for (const status of [401, 403, 404, 500]) {
    const calls = [];
    const { state } = loadPage('RiderManagement', async (url, options) => {
      calls.push({ url, options });
      return url.startsWith('/api/') ? { ok: true, json: async () => ({ data: [reviewRider()] }) }
        : { ok: false, status, json: async () => { throw new SyntaxError(); } };
    });
    await state.loadRiders();
    await state.viewDocument(state.selectedRider.value.review_documents[0]);
    assert.equal(calls[1].options.credentials, 'same-origin');
    assert.equal(calls[1].options.headers.Authorization, undefined);
    assert.ok(state.documentError.value.length > 10);
    assert.equal(state.documentLoading.value, false);
    assert.equal(state.preview.value, null);
  }
});

test('rider review closes previews on selection changes and ignores unmounted requests', async () => {
  const request = deferred();
  const page = loadPage('RiderManagement', () => request.promise);
  const loading = page.state.loadRiders();
  page.unmount();
  request.resolve({ ok: true, json: async () => ({ data: [reviewRider()] }) });
  await loading;
  assert.deepEqual(page.state.riders.value, []);

  const { state } = loadPage('RiderManagement', async url => url.startsWith('/api/')
    ? { ok: true, json: async () => ({ data: [reviewRider()] }) }
    : { ok: true, headers: { get: () => 'image/png' }, blob: async () => new Blob(['synthetic']) });
  await state.loadRiders();
  await state.viewDocument(state.selectedRider.value.review_documents[0]);
  assert.equal(state.preview.value.type, 'image/png');
  state.closeDocument();
  assert.equal(state.preview.value, null);
});

test('document review discards an old selection response and accepts PDF without rendering HTML', async () => {
  const request = deferred();
  const { state } = loadPage('RiderManagement', async url => url.startsWith('/api/')
    ? { ok: true, json: async () => ({ data: [reviewRider(1), reviewRider(2, 102)] }) } : request.promise);
  await state.loadRiders();
  const review = state.viewDocument(state.selectedRider.value.review_documents[0]);
  state.selectedRiderId.value = 2;
  state.closeDocument();
  request.resolve({ ok: true, headers: { get: () => 'application/pdf' }, blob: async () => new Blob(['synthetic PDF']) });
  await review;
  assert.equal(state.preview.value, null);
  assert.equal(state.documentLoading.value, false);

  for (const type of ['application/pdf', 'text/html']) {
    const page = loadPage('RiderManagement', async url => url.startsWith('/api/')
      ? { ok: true, json: async () => ({ data: [reviewRider()] }) }
      : { ok: true, headers: { get: () => type }, blob: async () => new Blob(['synthetic PDF']) });
    await page.state.loadRiders();
    await page.state.viewDocument(page.state.selectedRider.value.review_documents[0]);
    if (type === 'application/pdf') assert.equal(page.state.preview.value.type, type);
    else {
      assert.equal(page.state.preview.value, null);
      assert.match(page.state.documentError.value, /log in again/);
    }
    page.unmount();
  }
});

test('pending rider review offers existing approval and rejection, without suspension controls', async () => {
  const { component, state } = loadPage('RiderManagement', async () => ({ ok: true, json: async () => ({ data: [reviewRider()] }) }));
  await state.loadRiders();
  component.setup = () => state;
  const html = await renderToString(Vue.h(component));
  assert.match(html, /Synthetic Rider/);
  assert.match(html, /Account active/);
  assert.match(html, /Unavailable/);
  assert.match(html, />\s*Reject\s*</);
  assert.match(html, />\s*Approve\s*</);
  assert.doesNotMatch(html, />\s*(Suspend|Reactivate)\s*</);
});

test('rejection form blocks blank and short reasons before sending a mutation', async () => {
  const calls = [];
  const { state } = loadPage('RiderManagement', async (url, options) => { calls.push({ url, options }); return { ok: true, json: async () => ({ data: [reviewRider()] }) }; });
  await state.loadRiders();
  state.startReview('reject');
  for (const reason of ['', '123456789', ' '.repeat(10), 'x'.repeat(301)]) {
    state.rejectionReason.value = reason;
    await state.submitReview();
    assert.ok(state.reviewError.value.length > 0);
    assert.equal(calls.length, 1);
  }
});

test('rejection submits the reviewed Rider ID and exact reason, blocks duplicates and updates the pending filter', async () => {
  const request = deferred();
  const calls = [];
  const { state } = loadPage('RiderManagement', async (url, options) => {
    calls.push({ url, options });
    return options.method === 'POST' ? request.promise : { ok: true, json: async () => ({ data: [reviewRider(7, 101)] }) };
  });
  state.selectedStatus.value = 'pending';
  await state.loadRiders();
  state.startReview('reject');
  const reason = 'OR/CR photo is unreadable, please re-upload.';
  state.rejectionReason.value = reason;
  const pending = state.submitReview();
  await state.submitReview();
  await state.loadRiders();
  assert.equal(calls.length, 2);
  assert.equal(calls[1].url, '/api/sorting-center/riders/7/reject');
  assert.deepEqual(JSON.parse(calls[1].options.body), { reason });
  request.resolve({ ok: true, json: async () => ({ message: 'Rider rejected successfully.', data: { ...reviewRider(7, 101), approval_status: 'rejected' } }) });
  await pending;
  assert.equal(state.reviewLoading.value, false);
  assert.equal(state.selectedRider.value, null);
  assert.match(state.success.value, /rejected/);
});

test('failed Rider action retains the reason and releases loading for correction', async () => {
  const { state } = loadPage('RiderManagement', async (_, options) => options.method === 'POST'
    ? { ok: false, status: 422, json: async () => ({ message: 'The reason must be at least 10 characters.' }) }
    : { ok: true, json: async () => ({ data: [reviewRider()] }) });
  await state.loadRiders();
  state.startReview('reject');
  state.rejectionReason.value = 'A valid length reason.';
  await state.submitReview();
  assert.equal(state.rejectionReason.value, 'A valid length reason.');
  assert.match(state.reviewError.value, /10 characters/);
  assert.equal(state.reviewLoading.value, false);
  state.closeReview();
  assert.equal(state.reviewAction.value, null);
});

test('approval reuses its existing endpoint and rejected or approved Riders cannot reopen action forms', async () => {
  const calls = [];
  const { state } = loadPage('RiderManagement', async (url, options) => {
    calls.push(url);
    return { ok: true, json: async () => options.method === 'POST'
      ? { data: { ...reviewRider(), approval_status: 'approved', is_active: true } }
      : { data: [reviewRider()] } };
  });
  await state.loadRiders();
  state.startReview('approve');
  await state.submitReview();
  assert.equal(calls[1], '/api/sorting-center/riders/1/approve');
  assert.equal(state.selectedRider.value.approval_status, 'approved');
  state.startReview('reject');
  assert.equal(state.reviewAction.value, null);
});

test('monitoring retains the newest filter response when requests finish out of order', async () => {
  const requests = [deferred(), deferred()];
  let index = 0;
  const { state } = loadPage('DeliveryMonitoring', () => requests[index++].promise);
  const old = state.loadParcels();
  state.selectedStatus.value = 'DELIVERED';
  const current = state.loadParcels();
  requests[1].resolve(response([{ id: 2, status: 'DELIVERED' }]));
  await current;
  requests[0].resolve(response([{ id: 1, status: 'OUT_FOR_DELIVERY' }]));
  await old;
  assert.equal(state.parcels.value[0].id, 2);
  assert.equal(state.loading.value, false);
});

test('unmounted monitoring ignores an outstanding response', async () => {
  const request = deferred();
  const { state, unmount } = loadPage('DeliveryMonitoring', () => request.promise);
  const loading = state.loadParcels();
  unmount();
  request.resolve(response([{ id: 1 }]));
  await loading;
  assert.deepEqual(state.parcels.value, []);
});

test('pickup confirmation blocks overlapping actions and refreshes the page totals', async () => {
  const request = deferred();
  const calls = [];
  const { state } = loadPage('PickupRequestManagement', async (url, options) => {
    calls.push(url);
    if (options.method === 'POST') return request.promise;
    return response([{ id: 2, parcel: { order_id: 'SECOND' } }], 1);
  });
  const first = state.confirmPickup(1);
  await state.confirmPickup(2);
  await state.loadData();
  assert.equal(calls.length, 1);
  request.resolve({ ok: true, json: async () => ({ message: 'Confirmed' }) });
  await first;
  assert.equal(calls.length, 2);
  assert.equal(state.pagination.total, 1);
  assert.equal(state.pickupRequests.value[0].id, 2);
  assert.equal(state.processingId.value, null);
});

test('pickup pagination moves back when the last page becomes empty', async () => {
  const calls = [];
  const { state } = loadPage('PickupRequestManagement', async url => {
    calls.push(url);
    return url.endsWith('page=2') ? response([], 25, 2, 1) : response([{ id: 1 }], 25);
  });
  await state.loadData(2);
  assert.equal(calls.length, 2);
  assert.equal(state.pagination.current_page, 1);
});

test('pickup cards render safely with missing parcel and seller data', async () => {
  const { component, state } = loadPage('PickupRequestManagement', async () => response([]));
  assert.equal(state.formatAddress(null), 'Address unavailable');
  state.pickupRequests.value = [{ id: 1, parcel: null, seller: null, status: 'pending' }];
  component.setup = () => state;
  const html = await renderToString(Vue.createSSRApp(component));
  assert.match(html, /Unavailable/);
  assert.match(html, /Address unavailable/);
  assert.match(html, /disabled/);
});

test('pickup all-stages view retains the accepted record and hides its acceptance action', async () => {
  const calls = [];
  let confirmed = false;
  const { component, state } = loadPage('PickupRequestManagement', async (url, options) => {
    calls.push(url);
    if (options.method === 'POST') { confirmed = true; return incomingResponse(); }
    return response([{ id: 1, status: confirmed ? 'confirmed' : 'pending', seller: { name: 'Synthetic Seller' },
      parcel: { order_id: 'ITEM-1', status: 'PLACED', pickup_rider_id: null } }]);
  });
  await state.loadData();
  assert.match(calls[0], /status=all&stage=all/);
  await state.confirmPickup(1);
  assert.equal(state.pickupRequests.value[0].id, 1);
  assert.equal(state.pickupRequests.value[0].status, 'confirmed');
  assert.equal(state.statusLabel(state.pickupRequests.value[0]), 'Accepted');
  const before = calls.length;
  await state.confirmPickup(1);
  assert.equal(calls.length, before);
  component.setup = () => state;
  const html = await renderToString(Vue.createSSRApp(component));
  assert.match(html, /ITEM-1/);
  assert.match(html, /Accepted/);
  assert.doesNotMatch(html, /aria-label="Confirm pickup/);
});

test('pickup stage changes reset pagination and discard the old stage response', async () => {
  const previous = deferred(), calls = [];
  const { state } = loadPage('PickupRequestManagement', url => {
    calls.push(url);
    return calls.length === 1 ? previous.promise : response([{ id: 2, status: 'confirmed' }]);
  });
  const first = state.loadData(3);
  state.selectedStage.value = 'arrived';
  await state.changeStage();
  assert.match(calls[1], /stage=arrived&page=1$/);
  previous.resolve(response([{ id: 1, status: 'pending' }], 100, 3, 4));
  await first;
  assert.equal(state.pickupRequests.value[0].id, 2);
  assert.equal(state.pagination.current_page, 1);
});

test('pickup timeline distinguishes stored timestamps from missing historical times and records successful refresh time', async () => {
  const {component,state}=loadPage('PickupRequestManagement',async()=>response([{id:1,status:'confirmed',parcel:{order_id:'ITEM-TIMELINE',status:'PICKED_UP'},
    timeline:[{label:'Logistics accepted',occurred_at:null},{label:'Picked up / In transit to center',occurred_at:'2026-10-20T01:00:00Z'}]}]));
  assert.equal(state.lastUpdated.value,null);await state.loadData();assert.ok(state.lastUpdated.value);
  component.setup=()=>state;const html=await renderToString(Vue.createSSRApp(component));
  assert.match(html,/Time not recorded/);assert.match(html,/2026-10-20T01:00:00Z/);assert.match(html,/Last refreshed/);
});

test('pickup lifecycle labels use the pickup Rider and show real latest timestamps and safe arrival links', async () => {
  const { component, state } = loadPage('PickupRequestManagement', async () => response([]));
  const record = { id: 1, status: 'confirmed', updated_at: '2026-10-20T01:00:00Z', requested_at: '2026-10-20T00:00:00Z',
    seller: { name: 'Synthetic Seller' }, parcel: { order_id: 'ITEM A/?', status: 'PLACED', pickup_rider_id: 4,
      pickup_rider: { name: 'Pickup Rider' }, assigned_rider: { name: 'Last Mile Rider' }, updated_at: '2026-10-20T02:00:00Z' } };
  assert.equal(state.statusLabel({ status: 'pending' }), 'Pending');
  assert.equal(state.statusLabel(record), 'Rider Assigned');
  assert.equal(state.latestUpdate(record), record.parcel.updated_at);
  assert.equal(state.latestUpdate({ updated_at: 'invalid', parcel: null }), null);
  record.parcel.status = 'PICKED_UP';
  assert.match(state.statusLabel(record), /Picked Up from Seller \/ In Transit to Sorting Center/);
  state.pickupRequests.value = [record];
  component.setup = () => state;
  const html = await renderToString(Vue.createSSRApp(component));
  assert.match(html, /Pickup Rider/);
  assert.match(html, /Last Mile Rider/);
  assert.match(html, /reference=ITEM%20A%2F%3F/);
  assert.match(html, /2026-10-20T02:00:00Z/);
  record.parcel.status = 'AT_SORTING_CENTER';
  assert.equal(state.statusLabel(record), 'Arrived at Sorting Center');
  assert.doesNotMatch(await renderToString(Vue.createSSRApp(component)), /Confirm arrival in Order Management/);
});

test('pickup polling refreshes Rider updates, skips hidden or busy pages and stops after unmount', async () => {
  let poll, cleared = false, calls = 0, status = 'PLACED';
  const listeners = new Map();
  const document = { visibilityState: 'visible', addEventListener: (name, fn) => listeners.set(name, fn),
    removeEventListener: name => listeners.delete(name) };
  const runtime = { document, setInterval: (fn, ms) => { assert.equal(ms, 15000); poll = fn; return 7; },
    clearInterval: id => { cleared = id === 7; } };
  const { state, mount, unmount } = loadPage('PickupRequestManagement', async () => {
    calls++;
    return response([{ id: 1, status: 'confirmed', parcel: { status, pickup_rider_id: 2 } }]);
  }, Date, runtime);
  mount();
  await new Promise(done => setImmediate(done));
  assert.equal(calls, 1);
  status = 'PICKED_UP';
  await poll();
  assert.equal(state.pickupRequests.value[0].parcel.status, 'PICKED_UP');
  document.visibilityState = 'hidden';
  await poll();
  assert.equal(calls, 2);
  document.visibilityState = 'visible';
  state.processingId.value = 1;
  await poll();
  state.processingId.value = null;
  state.loading.value = true;
  await poll();
  assert.equal(calls, 2);
  state.loading.value = false;
  status = 'AT_SORTING_CENTER';
  await listeners.get('visibilitychange')();
  assert.equal(state.statusLabel(state.pickupRequests.value[0]), 'Arrived at Sorting Center');
  unmount();
  assert.equal(cleared, true);
  assert.equal(listeners.size, 0);
  await poll();
  assert.equal(calls, 3);
});

test('pickup errors stay actionable and a failed post-acceptance refresh never claims a clear queue', async () => {
  let recover = false;
  const { state } = loadPage('PickupRequestManagement', async (url, options) => {
    if (options.method === 'POST') return incomingResponse();
    return recover ? response([{ id: 1, status: 'confirmed' }]) : { ok: false, status: 401, json: async () => { throw Error('HTML'); } };
  });
  await state.confirmPickup(1);
  assert.equal(state.loadFailed.value, true);
  assert.match(state.feedback.message, /session has expired/);
  assert.equal(state.processingId.value, null);
  assert.equal(state.loading.value, false);
  recover = true;
  await state.loadData();
  assert.equal(state.loadFailed.value, false);
  assert.equal(state.pickupRequests.value[0].status, 'confirmed');
});

test('pickup unmount ignores outstanding reads and does not refresh after an outstanding acceptance', async () => {
  for (const mutation of [false, true]) {
    const request = deferred();
    let calls = 0;
    const { state, unmount } = loadPage('PickupRequestManagement', () => { calls++; return request.promise; });
    const operation = mutation ? state.confirmPickup(1) : state.loadData();
    unmount();
    request.resolve(mutation ? incomingResponse() : response([{ id: 1 }]));
    await operation;
    assert.equal(calls, 1);
    assert.deepEqual(state.pickupRequests.value, []);
  }
});

test('pickup arrival link prefills the authorized inbound lookup without confirming arrival automatically', async () => {
  const calls = [];
  const { state, mount } = loadPage('OrderManagement', async (url, options) => {
    calls.push({ url, options });
    return incomingResponse('ITEM A/?');
  }, Date, { window: { location: { search: '?reference=ITEM%20A%2F%3F' } } });
  mount();
  await new Promise(done => setImmediate(done));
  assert.equal(state.orderId.value, 'ITEM A/?');
  assert.equal(state.parcel.value.order_id, 'ITEM A/?');
  assert.equal(calls.length, 1);
  assert.match(calls[0].url, /lookup\/ITEM%20A%2F%3F$/);
  assert.equal(calls[0].options.method, undefined);
});
