<template>
  <sc-layout title="Rider Management">
    <div class="app-page-shell">
      <div v-if="!token" class="app-panel app-empty-state">
        <h2 class="app-section-title">Authentication required</h2>
        <p class="mt-2 text-sm text-gray-600">Log in with an approved Logistics account to review your riders.</p>
        <a href="/sorting-center/login" class="mt-5 inline-flex app-button-primary">Go to login</a>
      </div>
      <template v-else>
        <div class="app-panel app-panel--padded app-toolbar flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 class="app-section-title">Rider and Document Review</h2>
            <p class="mt-1 text-sm text-gray-600">Review your company's riders and submitted documents.</p>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <label for="rider-status" class="text-sm font-semibold">Approval status</label>
            <select id="rider-status" v-model="selectedStatus" class="app-input" :disabled="reviewLoading || coverageSaving" @change="loadRiders">
              <option value="">All</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
            <button type="button" class="app-button-secondary disabled:opacity-50" :disabled="loading || reviewLoading" @click="loadRiders">Refresh</button>
          </div>
        </div>
        <p v-if="error" class="app-feedback app-feedback--error" role="alert">{{ error }}</p>
        <p v-if="success" class="app-feedback app-feedback--success" role="status">{{ success }}</p>
        <div v-if="loading" class="app-panel app-empty-state" role="status">Loading riders...</div>
        <div v-else-if="riders.length === 0" class="app-panel app-empty-state">No riders match this approval status.</div>
        <template v-else>
          <div class="app-panel app-panel--padded">
            <label for="review-rider" class="block text-sm font-semibold">Choose a rider</label>
            <select id="review-rider" v-model="selectedRiderId" class="app-input mt-2 w-full" :disabled="reviewLoading || coverageSaving" @change="closeDocument(); closeReview(); closeAudit(); closeCoverage()">
              <option v-for="rider in riders" :key="rider.id" :value="rider.id">{{ rider.name || rider.user?.name || 'Rider' }} — {{ rider.user?.email || 'Email unavailable' }} · {{ rider.availability || 'Availability unknown' }} · {{ rider.presence || 'Unknown' }} presence · {{ rider.active_jobs_count ?? 'Unknown' }} active jobs · {{ rider.completed_jobs_today ?? 'Unknown' }} completed today</option>
            </select>
          </div>
          <div v-if="selectedRider" class="app-panel app-panel--padded">
            <h3 class="app-section-title">{{ selectedRider.name || selectedRider.user?.name || 'Rider' }}</h3>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
              <div><dt class="font-semibold">Email</dt><dd>{{ selectedRider.user?.email || 'Not provided' }}</dd></div>
              <div><dt class="font-semibold">Contact</dt><dd>{{ selectedRider.contact || 'Not provided' }}</dd></div>
              <div><dt class="font-semibold">Vehicle</dt><dd>{{ selectedRider.vehicle_type || 'Not provided' }}</dd></div>
              <div><dt class="font-semibold">Plate number</dt><dd>{{ selectedRider.plate_number || 'Not provided' }}</dd></div>
              <div><dt class="font-semibold">Approval status</dt><dd>{{ selectedRider.approval_status || 'Not provided' }}</dd></div>
              <div><dt class="font-semibold">Account active</dt><dd>{{ selectedRider.is_active ? 'Yes' : 'No' }}</dd></div>
              <div><dt class="font-semibold">Availability</dt><dd>{{ selectedRider.availability || 'Unknown' }}</dd></div>
              <div><dt class="font-semibold">Live presence</dt><dd>{{ selectedRider.presence || 'Unknown' }}</dd></div>
              <div><dt class="font-semibold">Active jobs</dt><dd>{{ selectedRider.active_jobs_count ?? 'Unknown' }} ({{ selectedRider.pickup_jobs_count ?? 'Unknown' }} pickup / {{ selectedRider.delivery_jobs_count ?? 'Unknown' }} delivery)</dd></div>
              <div><dt class="font-semibold">Active parcels</dt><dd>{{ selectedRider.active_parcels_count ?? 'Unknown' }}</dd></div>
              <div><dt class="font-semibold">Completed today (Manila)</dt><dd>{{ selectedRider.completed_jobs_today ?? 'Unknown' }}</dd></div>
            </dl>
            <p class="mt-3 text-sm text-gray-600">Presence uses the Rider's explicit Online choice and recent heartbeats; no signal means Unknown. Jobs count shipments per leg; legacy parcels count individually.</p>
            <p v-if="selectedRider.last_heartbeat_at" class="mt-1 text-sm text-gray-600">Last Rider heartbeat: {{ selectedRider.last_heartbeat_at }}</p>
            <p class="mt-4 text-sm text-gray-600">Account activity does not indicate whether a rider is Online.</p>
            <section class="mt-6 border-t border-[var(--app-border)] pt-5">
              <button type="button" class="app-button-secondary" :disabled="coverageSaving || reviewLoading || coverageLoading" @click="openCoverage">Manage Delivery Areas</button>
              <p v-if="coverageLoading" role="status">Loading delivery areas...</p>
              <form v-if="coverageVisible" class="mt-4 space-y-3" @submit.prevent="saveCoverage">
                <p class="text-sm">Choose this Rider's delivery coverage. Changes apply to future assignments; existing deliveries remain assigned.</p>
                <p v-if="coverageReason" class="text-sm text-amber-800">{{ coverageReason }}</p>
                <p v-if="!coverageAreas.length" class="text-sm">No service areas exist yet. Add service areas in Delivery Management.</p>
                <fieldset :disabled="coverageSaving || !coverageCanManage" class="space-y-2">
                  <legend class="font-semibold">Delivery service areas</legend>
                  <label v-for="area in coverageAreas" :key="area.id" class="flex items-center gap-2 text-sm">
                    <input v-model="coverageIds" type="checkbox" :value="area.id" /> {{ area.name }}
                  </label>
                </fieldset>
                <p class="text-sm">{{ coverageIds.length }} selected. Saving no areas removes coverage for future deliveries.</p>
                <p v-if="coverageError" class="app-feedback app-feedback--error" role="alert">{{ coverageError }}</p>
                <button type="submit" class="app-button-primary" :disabled="coverageSaving || !coverageCanManage">{{ coverageSaving ? 'Saving...' : 'Save delivery areas' }}</button>
                <button type="button" class="app-button-secondary" :disabled="coverageSaving" @click="closeCoverage">Cancel</button>
              </form>
              <p v-else-if="coverageError" class="app-feedback app-feedback--error" role="alert">{{ coverageError }}</p>
            </section>
            <h4 class="mt-6 font-semibold">Submitted documents</h4>
            <p class="mt-1 text-sm text-gray-600">Motor-vehicle documents may be unavailable for Bicycle riders or legacy records.</p>
            <ul class="mt-3 space-y-3">
              <li v-for="document in selectedRider.review_documents || []" :key="document.key" class="flex flex-wrap items-center gap-3">
                <span class="text-sm font-semibold">{{ document.label }}</span>
                <button v-if="documentUrl(document)" type="button" class="app-button-secondary disabled:opacity-50" :disabled="documentLoading || reviewLoading" @click="viewDocument(document)">View {{ document.label }}</button>
                <span v-else class="text-sm text-gray-500">Unavailable</span>
              </li>
            </ul>
            <p v-if="!selectedRider.review_documents?.length" class="mt-3 text-sm text-gray-500">No review documents are available.</p>
            <p v-if="documentLoading" class="mt-4 text-sm" role="status">Loading document...</p>
            <p v-if="documentError" class="app-feedback app-feedback--error mt-4" role="alert">{{ documentError }}</p>
            <div v-if="preview" class="mt-5">
              <button type="button" class="app-button-secondary mb-3" @click="closeDocument">Close document</button>
              <iframe v-if="preview.type === 'application/pdf'" :src="preview.url" :title="preview.label" class="w-full min-h-[32rem] rounded-lg border" referrerpolicy="no-referrer"></iframe>
              <img v-else :src="preview.url" :alt="preview.label" class="max-w-full rounded-lg border" />
            </div>
            <div v-if="['pending', 'approved'].includes(selectedRider.approval_status)" class="mt-6 border-t border-[var(--app-border)] pt-5">
              <div v-if="!reviewAction" class="flex flex-wrap gap-3">
                <template v-if="selectedRider.approval_status === 'pending'">
                  <button type="button" class="app-button-primary" @click="startReview('approve')">Approve</button>
                  <button type="button" class="app-button-secondary" @click="startReview('reject')">Reject</button>
                </template>
                <button v-else-if="selectedRider.is_active" type="button" class="app-button-secondary" :disabled="coverageSaving" @click="startReview('suspend')">Suspend Rider</button>
                <button v-else type="button" class="app-button-primary" :disabled="coverageSaving" @click="startReview('reactivate')">Reactivate Rider</button>
              </div>
              <form v-else class="space-y-3" novalidate @submit.prevent="submitReview">
                <template v-if="['reject', 'suspend'].includes(reviewAction)">
                  <label for="rider-rejection-reason" class="block text-sm font-semibold">{{ reviewAction === 'suspend' ? 'Suspension reason' : 'Rejection reason' }}</label>
                  <textarea id="rider-rejection-reason" v-model="rejectionReason" class="app-input w-full" rows="3" required minlength="10" maxlength="300" :disabled="reviewLoading" :aria-invalid="Boolean(reviewError)" aria-describedby="reason-help review-error"></textarea>
                  <p id="reason-help" class="text-sm text-gray-600">Enter 10–300 characters. {{ reviewAction === 'suspend' ? 'Existing jobs are retained. Collected parcels require a supervised handover before reassignment.' : 'The rider will receive this reason.' }}</p>
                </template>
                <p v-else class="text-sm">{{ reviewAction === 'reactivate' ? 'Reactivate this Rider? Existing jobs will remain assigned.' : "Approve this rider's application?" }}</p>
                <p v-if="reviewError" id="review-error" class="app-feedback app-feedback--error" role="alert">{{ reviewError }}</p>
                <div class="flex flex-wrap gap-3">
                  <button type="submit" class="app-button-primary disabled:opacity-50" :disabled="reviewLoading">{{ reviewLoading ? 'Saving...' : ({ reject: 'Confirm Rejection', approve: 'Confirm Approval', suspend: 'Confirm Suspension', reactivate: 'Confirm Reactivation' })[reviewAction] }}</button>
                  <button type="button" class="app-button-secondary disabled:opacity-50" :disabled="reviewLoading" @click="closeReview">Cancel</button>
                </div>
              </form>
            </div>
            <section class="mt-6 border-t border-[var(--app-border)] pt-5" aria-labelledby="decision-history-heading">
              <h4 id="decision-history-heading" class="font-semibold">Decision history</h4>
              <p class="mt-1 text-sm text-gray-600">Recorded approvals, rejections, suspensions and reactivations by your company. Older decisions may have no recorded history.</p>
              <button v-if="!auditVisible" type="button" class="app-button-secondary mt-3 disabled:opacity-50" :disabled="reviewLoading" @click="loadAudit()">View decision history</button>
              <template v-else>
                <button type="button" class="app-button-secondary mt-3" @click="closeAudit">Close history</button>
                <p v-if="auditLoading" class="mt-3 text-sm" role="status">Loading decision history...</p>
                <p v-else-if="auditError" class="app-feedback app-feedback--error mt-3" role="alert">{{ auditError }}</p>
                <p v-else-if="audits.length === 0" class="mt-3 text-sm text-gray-600">No recorded decisions for this rider.</p>
                <template v-else>
                  <ol class="mt-4 space-y-4">
                    <li v-for="audit in audits" :key="audit.id" class="rounded-lg border border-[var(--app-border)] p-4 text-sm">
                      <p class="font-semibold">{{ ({ approve: 'Approved', reject: 'Rejected', suspend: 'Suspended', reactivate: 'Reactivated' })[audit.action] || audit.action }} by {{ audit.actor_name || `Actor #${audit.actor_user_id}` }}</p>
                      <p class="mt-1">{{ formatAuditTime(audit.occurred_at) }} (Asia/Manila)</p>
                      <p class="mt-1">Approval: {{ audit.old_state?.approval_status || 'Unknown' }} → {{ audit.new_state?.approval_status || 'Unknown' }}</p>
                      <p class="mt-1">Account: {{ audit.old_state?.user_status || 'Unknown' }} → {{ audit.new_state?.user_status || 'Unknown' }}</p>
                      <p class="mt-1">Active: {{ audit.old_state?.is_active ? 'Yes' : 'No' }} → {{ audit.new_state?.is_active ? 'Yes' : 'No' }}</p>
                      <p v-if="audit.reason" class="mt-2 whitespace-pre-wrap">Reason: {{ audit.reason }}</p>
                    </li>
                  </ol>
                  <div class="mt-4 flex flex-wrap items-center gap-3">
                    <span class="text-sm">History page {{ auditPagination.current_page }} of {{ auditPagination.last_page }}</span>
                    <button type="button" class="app-button-secondary disabled:opacity-50" :disabled="!auditPagination.prev_page_url || reviewLoading" @click="loadAudit(auditPagination.current_page - 1)">Previous history</button>
                    <button type="button" class="app-button-secondary disabled:opacity-50" :disabled="!auditPagination.next_page_url || reviewLoading" @click="loadAudit(auditPagination.current_page + 1)">Next history</button>
                  </div>
                </template>
              </template>
            </section>
          </div>
        </template>
      </template>
    </div>
  </sc-layout>
</template>

<script>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import ScLayout from '@/Layouts/ScLayout.vue';

export default {
  name: 'RiderManagement',
  components: { ScLayout },
  setup() {
    const token = ref(localStorage.getItem('logistics_auth_token'));
    const selectedStatus = ref('');
    const riders = ref([]);
    const selectedRiderId = ref(null);
    const selectedRider = computed(() => riders.value.find(rider => rider.id === selectedRiderId.value) ?? null);
    const loading = ref(false);
    const error = ref('');
    const success = ref('');
    const reviewAction = ref(null);
    const rejectionReason = ref('');
    const reviewError = ref('');
    const reviewLoading = ref(false);
    let reviewedRiderId = null;
    const documentLoading = ref(false);
    const documentError = ref('');
    const preview = ref(null);
    const audits = ref([]);
    const auditVisible = ref(false);
    const auditLoading = ref(false);
    const auditError = ref('');
    const auditPagination = ref({ current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null });
    let auditVersion = 0;
    let requestVersion = 0;
    let documentVersion = 0;
    let disposed = false;
    const coverageVisible = ref(false), coverageLoading = ref(false), coverageSaving = ref(false);
    const coverageAreas = ref([]), coverageIds = ref([]), coverageError = ref(''), coverageReason = ref(''), coverageCanManage = ref(false);
    let coverageVersion = 0, coverageRiderId = null;

    function closeCoverage() {
      if (coverageSaving.value) return;
      coverageVersion++;
      coverageVisible.value = false;
      coverageLoading.value = false;
      coverageAreas.value = []; coverageIds.value = []; coverageError.value = '';
      coverageRiderId = null;
    }

    async function openCoverage() {
      if (disposed || !selectedRider.value || coverageSaving.value || reviewLoading.value) return;
      closeCoverage();
      const id = selectedRider.value.id, version = ++coverageVersion;
      coverageLoading.value = true;
      try {
        const response = await fetch(`/api/sorting-center/riders/${id}/delivery-areas`, { headers: { Accept: 'application/json', Authorization: `Bearer ${token.value}` } });
        if (!response.ok) throw new Error(await responseError(response, 'Unable to load delivery coverage.'));
        const data = (await response.json()).data;
        if (data?.rider_id !== id || !Array.isArray(data.areas) || !Array.isArray(data.area_ids)) throw new Error('Unexpected delivery coverage response. Refresh and try again.');
        if (disposed || version !== coverageVersion || selectedRider.value?.id !== id) return;
        coverageRiderId = id; coverageAreas.value = data.areas; coverageIds.value = data.area_ids;
        coverageCanManage.value = data.can_manage === true; coverageReason.value = data.reason || '';
        coverageVisible.value = true;
      } catch (failure) {
        if (!disposed && version === coverageVersion) coverageError.value = failure instanceof TypeError ? 'Unable to reach the server. Please try again.' : failure.message;
      } finally { if (!disposed && version === coverageVersion) coverageLoading.value = false; }
    }

    async function saveCoverage() {
      if (disposed || coverageSaving.value || !coverageCanManage.value || !coverageVisible.value || selectedRider.value?.id !== coverageRiderId) return;
      const id = coverageRiderId;
      coverageSaving.value = true; coverageError.value = ''; success.value = '';
      try {
        const response = await fetch(`/api/sorting-center/riders/${id}/delivery-areas`, {
          method: 'PUT', headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token.value}` },
          body: JSON.stringify({ area_ids: coverageIds.value }),
        });
        if (!response.ok) throw new Error(await responseError(response, 'Unable to save delivery coverage.'));
        const data = await response.json();
        if (data?.data?.rider_id !== id || !Array.isArray(data.data.area_ids)) throw new Error('Unexpected save response. Refresh to check coverage.');
        if (disposed) return;
        coverageIds.value = data.data.area_ids;
        success.value = data.message || 'Delivery areas saved.';
      } catch (failure) {
        if (!disposed) coverageError.value = failure instanceof TypeError ? 'Unable to reach the server. Reload coverage to check whether it was saved.' : failure.message;
      } finally { if (!disposed) coverageSaving.value = false; }
    }

    function closeDocument() {
      documentVersion++;
      if (preview.value) URL.revokeObjectURL(preview.value.url);
      preview.value = null;
      documentError.value = '';
      documentLoading.value = false;
    }

    async function responseError(response, fallback) {
      const data = await response.json().catch(() => null);
      return typeof data?.message === 'string' && data.message.trim() ? data.message
        : response.status === 401 ? 'Your session has expired. Please log in again.'
          : response.status === 403 ? 'You do not have permission to review this record.'
            : response.status === 404 ? 'This document is unavailable. Refresh the rider list or contact your administrator.' : fallback;
    }

    async function loadRiders() {
      if (disposed || !token.value || reviewLoading.value || coverageSaving.value) return;
      const version = ++requestVersion;
      closeDocument();
      closeReview();
      closeAudit();
      closeCoverage();
      success.value = '';
      riders.value = [];
      selectedRiderId.value = null;
      error.value = '';
      loading.value = true;
      try {
        const query = selectedStatus.value ? `?status=${encodeURIComponent(selectedStatus.value)}` : '';
        const response = await fetch(`/api/sorting-center/riders${query}`, {
          headers: { Accept: 'application/json', Authorization: `Bearer ${token.value}` },
        });
        if (!response.ok) throw new Error(await responseError(response, 'Unable to load riders. Please try again.'));
        const data = await response.json().catch(() => null);
        if (!Array.isArray(data?.data)) throw new Error('The server returned an unexpected rider response. Please refresh.');
        if (disposed || version !== requestVersion) return;
        riders.value = data.data;
        selectedRiderId.value = riders.value[0]?.id ?? null;
      } catch (failure) {
        if (!disposed && version === requestVersion) error.value = failure instanceof TypeError ? 'Unable to reach the server. Please try again.' : failure.message;
      } finally {
        if (!disposed && version === requestVersion) loading.value = false;
      }
    }

    function documentUrl(document) {
      const rider = selectedRider.value;
      if (!rider || !['id_doc', 'license_doc', 'or_cr_doc'].includes(document.key) || document.available !== true) return null;
      const expected = `/partner/documents/${rider.user_id}/${document.key}`;
      return document.url === expected ? expected : null;
    }

    function closeReview() {
      if (reviewLoading.value) return;
      reviewAction.value = null;
      reviewedRiderId = null;
      rejectionReason.value = '';
      reviewError.value = '';
    }

    function startReview(action) {
      if (loading.value || reviewLoading.value || coverageSaving.value || disposed || !allowedReview(action)) return;
      closeReview();
      closeCoverage();
      success.value = '';
      reviewedRiderId = selectedRider.value.id;
      reviewAction.value = action;
    }

    function allowedReview(action) {
      const rider = selectedRider.value;
      return rider?.approval_status === 'pending' ? ['approve', 'reject'].includes(action)
        : rider?.approval_status === 'approved' && action === (rider.is_active ? 'suspend' : 'reactivate');
    }

    async function submitReview() {
      if (reviewLoading.value || disposed || !reviewAction.value) return;
      if (selectedRider.value?.id !== reviewedRiderId || !allowedReview(reviewAction.value)) {
        closeReview();
        error.value = 'Select the Rider and review the action again.';
        return;
      }
      const reason = rejectionReason.value.trim();
      const length = Array.from(reason).length;
      if (['reject', 'suspend'].includes(reviewAction.value) && (length < 10 || length > 300)) {
        reviewError.value = `Enter a ${reviewAction.value === 'suspend' ? 'suspension' : 'rejection'} reason of 10–300 characters.`;
        return;
      }
      const action = reviewAction.value;
      const id = reviewedRiderId;
      reviewLoading.value = true;
      reviewError.value = '';
      try {
        const endpoint = { suspend: 'deactivate', reactivate: 'activate' }[action] || action;
        const response = await fetch(`/api/sorting-center/riders/${id}/${endpoint}`, {
          method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token.value}` },
          body: JSON.stringify(['reject', 'suspend'].includes(action) ? { reason } : {}),
        });
        if (!response.ok) throw new Error(await responseError(response, 'Unable to save the review. Please refresh and try again.'));
        const data = await response.json().catch(() => null);
        const status = action === 'reject' ? 'rejected' : 'approved';
        if (data?.data?.id !== id || data.data.approval_status !== status
          || (action === 'suspend' && data.data.is_active !== false) || (action === 'reactivate' && data.data.is_active !== true)) {
          throw new Error('The review response was unexpected. Refresh the rider list before trying again.');
        }
        if (disposed) return;
        closeDocument();
        closeAudit();
        riders.value = riders.value.map(rider => rider.id === id ? { ...rider, ...data.data } : rider)
          .filter(rider => !selectedStatus.value || rider.approval_status === selectedStatus.value);
        if (!riders.value.some(rider => rider.id === selectedRiderId.value)) selectedRiderId.value = riders.value[0]?.id ?? null;
        reviewAction.value = null;
        reviewedRiderId = null;
        rejectionReason.value = '';
        success.value = typeof data.message === 'string' && data.message.trim() ? data.message : `Rider ${status} successfully.`;
      } catch (failure) {
        if (!disposed) reviewError.value = failure instanceof TypeError ? 'Unable to reach the server. Refresh the list to check the review status.' : failure.message;
      } finally {
        reviewLoading.value = false;
      }
    }

    function closeAudit() {
      auditVersion++;
      audits.value = [];
      auditVisible.value = false;
      auditLoading.value = false;
      auditError.value = '';
      auditPagination.value = { current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null };
    }

    async function loadAudit(page = 1) {
      if (disposed || !token.value || !selectedRider.value || reviewLoading.value || auditLoading.value || !Number.isInteger(page) || page < 1) return;
      const riderId = selectedRider.value.id;
      const version = ++auditVersion;
      auditVisible.value = true;
      auditLoading.value = true;
      auditError.value = '';
      audits.value = [];
      try {
        const response = await fetch(`/api/sorting-center/riders/${riderId}/audits?page=${page}`, {
          headers: { Accept: 'application/json', Authorization: `Bearer ${token.value}` },
        });
        if (!response.ok) throw new Error(await responseError(response, 'Unable to load decision history. Please try again.'));
        const data = await response.json().catch(() => null);
        if (!Array.isArray(data?.data?.data) || !Number.isInteger(data.data.current_page) || !Number.isInteger(data.data.last_page)) {
          throw new Error('The server returned an unexpected history response. Please try again.');
        }
        if (disposed || version !== auditVersion || selectedRider.value?.id !== riderId) return;
        audits.value = data.data.data;
        auditPagination.value = { current_page: data.data.current_page, last_page: data.data.last_page,
          prev_page_url: data.data.prev_page_url, next_page_url: data.data.next_page_url };
      } catch (failure) {
        if (!disposed && version === auditVersion && selectedRider.value?.id === riderId) {
          auditError.value = failure instanceof TypeError ? 'Unable to reach the server. Please try again.' : failure.message;
        }
      } finally {
        if (!disposed && version === auditVersion) auditLoading.value = false;
      }
    }

    function formatAuditTime(value) {
      const time = new Date(value);
      return value && !Number.isNaN(time.getTime()) ? time.toLocaleString('en-PH', { timeZone: 'Asia/Manila' }) : 'Time unavailable';
    }

    async function viewDocument(document) {
      const url = documentUrl(document);
      if (!url || documentLoading.value || reviewLoading.value || disposed) return;
      closeDocument();
      const version = documentVersion;
      documentLoading.value = true;
      try {
        // This is a session-protected web route. Never send the API bearer token to a document URL.
        const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(await responseError(response, 'Unable to open the document. Please try again.'));
        const type = response.headers.get('Content-Type')?.split(';')[0].trim().toLowerCase();
        if (response.redirected || !['application/pdf', 'image/jpeg', 'image/png'].includes(type)) {
          throw new Error('The document could not be displayed. Please log in again or contact your administrator.');
        }
        const blob = await response.blob();
        if (disposed || version !== documentVersion) return;
        preview.value = { url: URL.createObjectURL(blob), type, label: document.label };
      } catch (failure) {
        if (!disposed && version === documentVersion) documentError.value = failure instanceof TypeError ? 'Unable to reach the server. Please try again.' : failure.message;
      } finally {
        if (!disposed && version === documentVersion) documentLoading.value = false;
      }
    }

    onMounted(loadRiders);
    onUnmounted(() => { disposed = true; requestVersion++; coverageVersion++; closeDocument(); closeAudit(); });
    return { token, selectedStatus, riders, selectedRiderId, selectedRider, loading, error, success, loadRiders,
      coverageVisible, coverageLoading, coverageSaving, coverageAreas, coverageIds, coverageError, coverageReason, coverageCanManage, openCoverage, saveCoverage, closeCoverage,
      reviewAction, rejectionReason, reviewError, reviewLoading, startReview, submitReview, closeReview,
      audits, auditVisible, auditLoading, auditError, auditPagination, loadAudit, closeAudit, formatAuditTime,
      documentLoading, documentError, preview, closeDocument, documentUrl, viewDocument };
  },
};
</script>
