<template>
  <sc-layout title="Delivery Proof Review">
  <div class="delivery-proof-review app-page-shell">
    <div class="proof-header">
      <h2 class="app-section-title">Proof of delivery</h2>
      <div class="parcel-info" v-if="parcel">
        <p><strong>Order:</strong> {{ parcel.order_id }}</p>
        <p><strong>Status:</strong> <span class="badge" :class="'badge-' + (parcel.status || 'unknown').toLowerCase()">{{ parcel.status }}</span></p>
        <p><strong>Address:</strong> {{ parcel.delivery_street }}, {{ parcel.delivery_house_number }}</p>
      </div>
    </div>

    <div v-if="loading" class="loading">Loading proofs...</div>
    <div v-else-if="errorMessage" role="alert">{{ errorMessage }}</div>
    <div v-else-if="proofs.length === 0" class="no-proofs"><p>No delivery proofs uploaded yet.</p></div>

    <div v-else class="proofs-list">
      <div v-for="proof in proofs" :key="proof.id" class="proof-card app-panel app-panel--padded">
        <div class="proof-header-item">
          <div class="proof-type">
            <span class="badge" :class="'badge-' + (proof.type || 'unknown').toLowerCase()">{{ proof.type }}</span>
          </div>
          <div class="proof-rider">
            <p><strong>Captured by:</strong> {{ proof.rider?.name }}</p>
            <p><strong>Time:</strong> {{ formatDate(proof.captured_at) }}</p>
          </div>
        </div>
        <div class="proof-content">
          <protected-proof-image :file-url="proof.file_url" :token="token" />
        </div>
        <div v-if="proof.notes" class="proof-notes">
          <p><strong>Notes:</strong> {{ proof.notes }}</p>
        </div>
      </div>
    </div>
  </div>
  </sc-layout>
</template>

<script>
import ProtectedProofImage from './ProtectedProofImage.vue';
import ScLayout from './ScLayout.vue';
export default {
  name: 'DeliveryProofReview',
  components: { ProtectedProofImage, ScLayout },
  props: {
    parcelId: { type: Number, required: true },
    apiUrl: { type: String, required: true },
    token: { type: String, required: true },
  },
  data() {
    return { proofs: [], parcel: null, loading: true, errorMessage: '' };
  },
  mounted() { this.fetchProofs(); },
  methods: {
    async fetchProofs() {
      this.loading = true;
      this.errorMessage = '';
      try {
        const response = await fetch(`${this.apiUrl}/sorting-center/parcels/${this.parcelId}/proofs`, { headers: { Authorization: `Bearer ${this.token}` } });
        if (!response.ok) throw new Error('Failed to fetch proofs');
        const data = await response.json();
        this.proofs = Array.isArray(data.data?.proofs) ? data.data.proofs : [];
        this.parcel = data.data?.parcel ?? null;
      } catch (error) {
        console.error('Error:', error);
        this.proofs = [];
        this.parcel = null;
        this.errorMessage = 'Delivery proofs could not be loaded. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    formatDate(dateString) {
      return dateString ? new Date(dateString).toLocaleString() : '';
    },
  },
};
</script>

<style scoped>
.parcel-info { font-size: 0.875rem; color: var(--app-text-muted); }
.proof-header { margin-bottom: 30px; }
.proof-header h2 { margin: 0 0 15px 0; font-size: 24px; color: #333; }
.parcel-info { background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #007bff; }
.parcel-info p { margin: 8px 0; color: #555; }
.loading, .no-proofs { text-align: center; padding: 40px; }
.proofs-list { display: grid; gap: 20px; }
.proof-card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.proof-header-item { display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0; }
.badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; color: white; }
.badge-photo { background-color: var(--app-primary); }
.badge-signature { background-color: var(--app-text-muted); }
.proof-image { max-width: 100%; max-height: 400px; border-radius: 6px; border: 1px solid #e0e0e0; }
.btn-view { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; width: 100%; }
</style>
