<template>
  <div class="app-shell">
    <aside class="app-sidebar">
      <div class="app-sidebar__brand">
        <img :src="logoUrl" alt="Alona Logo" />
        <div class="app-sidebar__brand-text">
          <span class="app-sidebar__brand-name">Alona</span>
          <span class="app-sidebar__brand-subtitle">Logistics</span>
        </div>
      </div>
      <button type="button" class="app-sidebar__toggle" :aria-expanded="navigationOpen" aria-controls="sorting-center-navigation" @click="navigationOpen = !navigationOpen">
        {{ navigationOpen ? 'Close navigation' : 'Open navigation' }}
      </button>

      <nav id="sorting-center-navigation" class="app-sidebar__nav" :class="{ 'is-open': navigationOpen }" aria-label="Sidebar navigation">
        <div v-for="group in navGroups" :key="group.label" class="app-nav-group">
          <p class="app-nav-group__label">{{ group.label }}</p>
          <a
          v-for="item in group.items"
          :key="item.path"
          :href="item.path"
          :class="['app-nav-item', { 'is-active': isActive(item.path) }]"
          :aria-current="isActive(item.path) ? 'page' : undefined"
        >
          <span class="app-nav-item__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path :d="item.icon" /></svg></span>
          <span>{{ item.label }}</span>
        </a>
        </div>
      </nav>

      <div class="app-sidebar__user">
        <div class="app-user">
          <div class="app-user__avatar">{{ userInitial }}</div>
          <div class="app-user__meta">
            <span class="app-user__name">{{ userName }}</span>
            <span class="app-user__role">{{ userRole }}</span>
          </div>
        </div>

        <button type="button" class="app-logout-button" @click="logout">Logout</button>
      </div>
    </aside>

    <main class="app-main">
      <header class="app-header">
        <div class="app-header__title">
          <span class="app-header__accent"></span>
          <h1>{{ pageTitle }}</h1>
        </div>
        <div class="app-header__meta">{{ currentDateTime }}</div>
      </header>

      <div class="app-content">
        <slot></slot>
      </div>
    </main>
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';

export default {
  name: 'ScLayout',
  props: {
    title: {
      type: String,
      required: true,
    },
  },
  setup(props) {
    const currentDateTime = ref('');
    const navigationOpen = ref(false);

    const navItems = [
      { path: '/sorting-center/dashboard', label: 'Dashboard' },
      { path: '/sorting-center/order-management', label: 'Order Management' },
      { path: '/sorting-center/delivery-management', label: 'Delivery Management' },
      { path: '/sorting-center/pickup-requests', label: 'Pickup Requests' },
      { path: '/sorting-center/rider-assignment', label: 'Rider Assignment' },
      { path: '/sorting-center/delivery-monitoring', label: 'Delivery Monitoring' },
      { path: '/sorting-center/delivery-reports', label: 'Delivery Reports' },
      { path: '/sorting-center/messages', label: 'Messages' },
    ];
    const icons = [
      'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
      'M6 4h12v17H6z M9 4V2h6v2 M9 10h6 M9 14h6',
      'M3 7l9-4 9 4v10l-9 4-9-4z M3 7l9 4 9-4 M12 11v10',
      'M3 9h11v9H3z M14 12h4l3 3v3h-7 M6 18v2 M18 18v2 M6 5h5 M9 2v6',
      'M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6 M2 20v-3a6 6 0 0 1 12 0v3 M16 9h6 M19 6v6',
      'M3 4h18v13H3z M8 21h8 M12 17v4 M6 11h3l2-4 3 7 2-3h2',
      'M4 3h16v18H4z M8 16v-4 M12 16V8 M16 16v-6',
      'M3 4h18v13H8l-5 4z M7 8h10 M7 12h7',
    ];
    navItems.forEach((item, index) => { item.icon = icons[index]; });
    const navGroups = [
      { label: 'Overview', items: [navItems[0]] },
      { label: 'Operations', items: [navItems[3], navItems[1], navItems[2], navItems[4], navItems[5], navItems[6]] },
      { label: 'Communication', items: [navItems[7]] },
    ];

    const userName = ref('Sorting Center');
    const userRole = ref('Administrator');
    const userInitial = computed(() => userName.value.charAt(0).toUpperCase());
    const pageTitle = computed(() => props.title);
    const logoUrl = '/images/alona.png';

    const isActive = (path) => {
      const current = window.location.pathname;
      if (current === path) return true;
      return false;
    };

    const updateDateTime = () => {
      const now = new Date();
      currentDateTime.value = now.toLocaleString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    };

    const logout = async () => {
      if (confirm('Are you sure you want to logout?')) {
        try {
          const token = localStorage.getItem('logistics_auth_token');
          if (token) {
            const response = await fetch('/api/logistics/logout', { method: 'POST', headers: { Accept: 'application/json', Authorization: 'Bearer ' + token } });
            if (!response.ok) throw new Error('Unable to sign out. Please try again.');
          }
          localStorage.removeItem('logistics_auth_token');
          localStorage.removeItem('logistics_auth_session');
          
          localStorage.removeItem('sortingCenterId');
          localStorage.removeItem('sortingCenterName');
          window.location.href = '/sorting-center/login';
        } catch (error) {
          console.error('Logout error:', error);
        }
      }
    };

    let interval;
    onUnmounted(() => clearInterval(interval));

    onMounted(() => {
      updateDateTime();
      interval = setInterval(updateDateTime, 60000);

      const sessionStr = localStorage.getItem('logistics_auth_session');
      if (sessionStr) {
        try {
          const session = JSON.parse(sessionStr);
          if (session.sorting_center?.business_name) {
            userName.value = session.sorting_center.business_name;
          } else if (session.user?.name) {
            userName.value = session.user.name;
          }
        } catch (e) {
          // ignore json parse error
        }
      }
      const storedName = localStorage.getItem('sortingCenterName');
      if (storedName) userName.value = storedName;

    });

    return {
      navItems,
      navGroups,
      navigationOpen,
      userName,
      userRole,
      userInitial,
      pageTitle,
      currentDateTime,
      isActive,
      logout,
      logoUrl,
    };
  },
};
</script>
