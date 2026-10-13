import './bootstrap';
import '../css/logistics.css';
import { createApp } from 'vue';

// The marketplace login supplies the same scoped Sanctum identity used by the existing portal.
if (window.logisticsSession) {
    localStorage.setItem('logistics_auth_token', window.logisticsSession.token);
    localStorage.setItem('logistics_auth_session', JSON.stringify(window.logisticsSession));
    localStorage.setItem('sortingCenterName', window.logisticsSession.sorting_center.business_name);
}

const components = {
    'sorting-center-registration': () => import('./Logistics/components/SortingCenterRegistration.vue'),
    'sorting-center-login': () => import('./Logistics/components/SortingCenterLogin.vue'),
    'order-management': () => import('./Logistics/components/OrderManagement.vue'),
    'delivery-management': () => import('./Logistics/components/DeliveryManagement.vue'),
    'rider-assignment': () => import('./Logistics/components/RiderAssignment.vue'),
    'delivery-monitoring': () => import('./Logistics/components/DeliveryMonitoring.vue'),
    dashboard: () => import('./Logistics/components/Dashboard.vue'),
    'delivery-reports': () => import('./Logistics/components/DeliveryReports.vue'),
    'delivery-proof-review': () => import('./Logistics/components/DeliveryProofReview.vue'),
    messages: () => import('./Logistics/components/Messages.vue'),
    'pickup-request-management': () => import('./Logistics/components/PickupRequestManagement.vue'),
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
