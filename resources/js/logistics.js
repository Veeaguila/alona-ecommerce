import './bootstrap';
import '../css/logistics.css';
import { createApp } from 'vue';
import { logisticsSessionFetch } from './logisticsSession';

// The marketplace login supplies the same scoped Sanctum identity used by the existing portal.
if (window.logisticsSession) {
    localStorage.setItem('logistics_auth_token', window.logisticsSession.token);
    localStorage.setItem('logistics_auth_session', JSON.stringify(window.logisticsSession));
    localStorage.setItem('sortingCenterName', window.logisticsSession.sorting_center.business_name);
}

window.fetch = logisticsSessionFetch(window.fetch.bind(window), {
    origin: window.location.origin,
    getToken: () => localStorage.getItem('logistics_auth_token'),
    onExpired: () => {
        for (const key of ['logistics_auth_token', 'logistics_auth_session', 'sortingCenterId', 'sortingCenterName']) localStorage.removeItem(key);
        window.location.assign('/?auth=login');
    },
});

const components = {
    'rider-payouts': () => import('./Pages/Logistics/RiderPayouts.vue'),
    'hub-workspace': () => import('./Pages/Logistics/HubWorkspace.vue'),
    'hub-staff': () => import('./Pages/Logistics/HubStaff.vue'),
    'delivery-rates': () => import('./Pages/Logistics/DeliveryRates.vue'),
    'hub-network': () => import('./Pages/Logistics/HubNetwork.vue'),
    'sorting-center-registration': () => import('./Pages/Logistics/SortingCenterRegistration.vue'),
    'sorting-center-login': () => import('./Pages/Logistics/SortingCenterLogin.vue'),
    'order-management': () => import('./Pages/Logistics/OrderManagement.vue'),
    'delivery-management': () => import('./Pages/Logistics/DeliveryManagement.vue'),
    'rider-assignment': () => import('./Pages/Logistics/RiderAssignment.vue'),
    'rider-management': () => import('./Pages/Logistics/RiderManagement.vue'),
    'delivery-monitoring': () => import('./Pages/Logistics/DeliveryMonitoring.vue'),
    dashboard: () => import('./Pages/Logistics/Dashboard.vue'),
    'delivery-reports': () => import('./Pages/Logistics/DeliveryReports.vue'),
    'delivery-proof-review': () => import('./Pages/Logistics/DeliveryProofReview.vue'),
    messages: () => import('./Pages/Logistics/Messages.vue'),
    'pickup-request-management': () => import('./Pages/Logistics/PickupRequestManagement.vue'),
};

async function mountPage() {
    const root = document.querySelector('#app');
    if (!root) return;

    try {
        const page = root.firstElementChild;
        const componentName = page?.localName;
        if (!Object.hasOwn(components, componentName ?? '')) {
            throw new Error('Unknown or missing Vue page component.');
        }

        const props = {};
        if (componentName === 'delivery-proof-review') {
            // Validate the required props and keep API requests same-origin.
            const parcelId = Number(page.getAttribute('parcel-id') ?? page.dataset.parcelId);
            if (!Number.isSafeInteger(parcelId) || parcelId <= 0) {
                throw new Error('A valid parcel ID is required for delivery proof review.');
            }
            props.parcelId = parcelId;
            props.apiUrl = '/api';
            props.token = localStorage.getItem('logistics_auth_token');
            if (!props.token) {
                throw new Error('Please log in to review delivery proofs.');
            }
        }

        const { default: component } = await components[componentName]();
        const app = createApp(component, props);
        let failed = false;
        app.config.errorHandler = (error) => {
            console.error('Vue page error:', error);
            if (failed) return;
            failed = true;
            queueMicrotask(() => {
                app.unmount();
                const message = document.createElement('p');
                message.setAttribute('role', 'alert');
                message.textContent = 'Something went wrong. Please refresh the page.';
                root.replaceChildren(message);
            });
        };
        app.mount(root);
    } catch (error) {
        console.error('Unable to start the Vue page:', error);
        const message = document.createElement('p');
        message.setAttribute('role', 'alert');
        message.textContent = 'Unable to load this page. Please refresh or log in again.';
        root.replaceChildren(message);
    }
}

void mountPage();
