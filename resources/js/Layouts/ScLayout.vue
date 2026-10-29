<template>
  <div class="min-h-screen bg-[#F8FAF9] text-[#1F2937] antialiased">
    <!-- Mobile Backdrop Overlay -->
    <transition
      enter-active-class="transition-opacity duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"
        @click="sidebarOpen = false"
      ></div>
    </transition>

    <!-- Sidebar (Matches AdminLayout styling) -->
    <aside
      class="fixed inset-y-0 left-0 z-40 w-[240px] transform border-r border-[#065A63]/30 bg-gradient-to-b from-[#087F8C] via-[#076F7B] to-[#065A63] text-white shadow-xl transition-transform duration-200 lg:translate-x-0"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex h-full min-h-0 flex-col">
        <!-- Logo / Brand Header -->
        <div class="relative flex h-[80px] shrink-0 items-center border-b border-white/10 px-4 sm:px-5">
          <a
            :href="isHubStaff ? '/sorting-center/hub-workspace' : '/sorting-center/dashboard'"
            class="flex items-center gap-3 transition hover:opacity-95"
            @click="sidebarOpen = false"
          >
            <div
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white p-1.5 shadow-sm"
            >
              <img
                src="/images/alona-icon.png"
                alt="Alona"
                class="h-full w-full object-contain"
              />
            </div>

            <div class="flex flex-col min-w-0">
              <span class="text-base font-semibold tracking-tight text-white leading-tight">
                Alona
              </span>
              <span class="text-[10px] font-medium uppercase tracking-wider text-teal-100/90 leading-tight mt-0.5">
                Logistics Center
              </span>
            </div>
          </a>

          <!-- Mobile Close Button -->
          <button
            type="button"
            class="absolute right-3 rounded-lg p-2 text-white/70 hover:bg-white/10 hover:text-white lg:hidden"
            @click="sidebarOpen = false"
            aria-label="Close sidebar"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="1.8"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Scrollable Navigation -->
        <div class="sidebar-scrollbar min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pb-4">
          <div v-for="group in navGroups" :key="group.label" class="pt-5">
            <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.14em] text-teal-100/70">
              {{ group.label }}
            </p>

            <nav class="space-y-1">
              <a
                v-for="item in group.items"
                :key="item.path"
                :href="item.path"
                class="group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition"
                :class="
                  isActive(item.path)
                    ? 'bg-[#F5A711] text-[#043E44] font-bold shadow-md'
                    : 'text-white/80 hover:bg-white/10 hover:text-white'
                "
                @click="sidebarOpen = false"
              >
                <!-- Nav Icon -->
                <span class="flex h-[18px] w-[18px] shrink-0 items-center justify-center" aria-hidden="true">
                  <!-- Dashboard -->
                  <svg
                    v-if="item.key === 'dashboard'"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-[18px] w-[18px]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M3 13h8V3H3v10zm10 8h8V11h-8v10zM3 21h8v-4H3v4zm10-18v4h8V3h-8z"
                    />
                  </svg>

                  <!-- Pickup Requests -->
                  <svg
                    v-else-if="item.key === 'pickups'"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-[18px] w-[18px]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                  </svg>

                  <!-- Order Management -->
                  <svg
                    v-else-if="item.key === 'orders'"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-[18px] w-[18px]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                    />
                  </svg>

                  <!-- Delivery Management -->
                  <svg
                    v-else-if="item.key === 'delivery'"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-[18px] w-[18px]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"
                    />
                  </svg>

                  <!-- Rider Assignment -->
                  <svg
                    v-else-if="['riders', 'rider-management'].includes(item.key)"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-[18px] w-[18px]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                    />
                  </svg>

                  <!-- Delivery Monitoring -->
                  <svg
                    v-else-if="item.key === 'monitoring'"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-[18px] w-[18px]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"
                    />
                  </svg>

                  <!-- Delivery Reports -->
                  <svg
                    v-else-if="item.key === 'reports'"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-[18px] w-[18px]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                    />
                  </svg>

                  <!-- Messages -->
                  <svg
                    v-else-if="item.key === 'messages'"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-[18px] w-[18px]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                    />
                  </svg>
                </span>

                <span class="truncate">{{ item.label }}</span>
              </a>
            </nav>
          </div>
        </div>

        <!-- Sidebar Bottom: User Profile Card & Logout on the side -->
        <div class="shrink-0 border-t border-white/10 bg-black/10 p-3">
          <div
            class="flex items-center justify-between gap-2 rounded-xl border border-white/10 bg-white/10 p-2 backdrop-blur-xs transition hover:bg-white/15"
          >
            <!-- Profile Info -->
            <a
              href="/sorting-center/dashboard"
              class="group flex min-w-0 flex-1 items-center gap-2.5 transition"
              title="Sorting Center Profile"
              @click="sidebarOpen = false"
            >
              <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white text-xs font-black text-[#087F8C] shadow-xs">
                {{ userInitial }}
              </span>

              <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-bold text-white group-hover:text-teal-200">
                  {{ userName }}
                </p>
                <p class="text-[10px] text-teal-100/70">
                  {{ userRole }}
                </p>
              </div>
            </a>

            <!-- Logout Button on the side -->
            <button
              type="button"
              class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-rose-200 transition hover:bg-rose-500/20 hover:text-white active:scale-95"
              title="Sign out"
              aria-label="Sign out"
              @click="logout"
            >
              <svg
                class="h-4 w-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
              >
                <path
                  d="M10 17l5-5-5-5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
                <path
                  d="M15 12H3"
                  stroke-linecap="round"
                />
                <path
                  d="M19 4h1a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-1"
                  stroke-linecap="round"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="lg:pl-[240px]">
      <!-- Sticky Header (Height 68px, matching AdminLayout) -->
      <header class="sticky top-0 z-20 h-[68px] border-b border-[#E5E7EB] bg-white/95 backdrop-blur">
        <div class="flex h-full items-center justify-between gap-3 px-4 sm:px-6">
          <!-- Left: Mobile Menu Toggle + Eyebrow & Page Title / Date -->
          <div class="flex min-w-0 items-center gap-3">
            <button
              type="button"
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-slate-600 hover:border-[#16A6A0] hover:text-[#087F8C] lg:hidden"
              @click="sidebarOpen = true"
              aria-label="Open sidebar"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.8"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>

            <div class="min-w-0">
              <p class="truncate text-xs font-medium text-slate-400">
                Logistics Center &bull; {{ pageTitle }}
              </p>
              <p class="truncate text-sm font-semibold text-slate-700 sm:text-base">
                {{ currentDateTime }}
              </p>
            </div>
          </div>

          <!-- Right: Action Icons & Account Dropdown (Exact AdminLayout pattern) -->
          <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <!-- Quick Link / Refresh Button -->
            <a
              href="/sorting-center/dashboard"
              class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-slate-600 transition hover:border-[#16A6A0] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
              title="Dashboard Overview"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-[19px] w-[19px]"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.8"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M15 17h5l-1.4-1.8A2 2 0 0118 14V10a6 6 0 00-12 0v4a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 01-6 0"
                />
              </svg>
            </a>

            <!-- Center Status -->
            <div
              class="ml-1 flex items-center gap-2.5 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-1.5 transition sm:px-3.5 sm:py-2"
            >
              <span
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 shadow-xs"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
              </span>

              <div class="min-w-0">
                <p
                  class="text-[10px] font-semibold uppercase tracking-wider text-[#64748B]"
                >
                  Center status
                </p>

                <div class="flex items-center gap-1.5">
                  <span
                    class="h-1.5 w-1.5 rounded-full bg-emerald-500 ring-2 ring-emerald-100"
                  ></span>

                  <span
                    class="text-xs font-bold text-emerald-700"
                  >
                    Operational
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Slot Content Area -->
      <main class="min-h-[calc(100vh-68px)] px-4 py-5 sm:px-6 sm:py-6 lg:px-7 print:min-h-0 print:p-0 print:m-0">
        <slot></slot>
      </main>
    </div>
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
    const sidebarOpen = ref(false);
    const accountOpen = ref(false);

    const navItems = [
      { path: '/sorting-center/dashboard', label: 'Dashboard', key: 'dashboard' },
      { path: '/sorting-center/pickup-requests', label: 'Pickup Requests', key: 'pickups' },
      { path: '/sorting-center/order-management', label: 'Order Management', key: 'orders' },
      { path: '/sorting-center/delivery-management', label: 'Delivery Management', key: 'delivery' },
      { path: '/sorting-center/rider-assignment', label: 'Rider Assignment', key: 'riders' },
      { path: '/sorting-center/delivery-monitoring', label: 'Delivery Monitoring', key: 'monitoring' },
      { path: '/sorting-center/delivery-reports', label: 'Delivery Reports', key: 'reports' },
      { path: '/sorting-center/messages', label: 'Messages', key: 'messages' },
      { path: '/sorting-center/rider-management', label: 'Rider Management', key: 'rider-management' },
      { path: '/sorting-center/delivery-rates', label: 'Zones & Rates', key: 'delivery-rates' },
      { path: '/sorting-center/hubs', label: 'Hubs & Routes', key: 'hubs' },
    ];

    const isHubStaff = window.logisticsSession?.operator?.role === 'hub_staff';
    const hubWorkspace = { path: '/sorting-center/hub-workspace', label: 'Hub workspace', key: 'orders' };
    const navGroups = isHubStaff ? [
      { label: 'Operations', items: [hubWorkspace] },
    ] : [
      { label: 'Overview', items: [navItems[0]] },
      { label: 'Operations', items: [hubWorkspace, navItems[1], navItems[2], navItems[3], navItems[8], navItems[9], navItems[10], navItems[4], navItems[5], navItems[6]] },
      { label: 'Staff access', items: [{ path: '/sorting-center/hub-staff', label: 'Hub staff', key: 'rider-management' }] },
      { label: 'Communication', items: [navItems[7]] },
    ];

    const userName = ref('Sorting Center');
    const userRole = ref(isHubStaff ? 'Hub Staff' : 'Logistics Center');
    const userInitial = computed(() => userName.value.charAt(0).toUpperCase());
    const pageTitle = computed(() => props.title);
    const logoUrl = '/images/alona.png';

    const isActive = (path) => {
      const current = window.location.pathname;
      return current === path;
    };

    const updateDateTime = () => {
      const now = new Date();
      currentDateTime.value = now.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
      });
    };

    const handleOutsideClick = (e) => {
      accountOpen.value = false;
    };

    const logout = async () => {
      accountOpen.value = false;
      if (confirm('Are you sure you want to sign out?')) {
        try {
          const token = localStorage.getItem('logistics_auth_token');
          if (window.logisticsSession) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const response = await fetch('/logout', {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': csrfToken || '',
                'Accept': 'application/json',
              },
            });
            if (!response.ok) throw new Error('Unable to end your session. Please try again.');
          } else if (token) {
            const response = await fetch('/api/logistics/logout', {
              method: 'POST',
              headers: {
                Accept: 'application/json',
                Authorization: 'Bearer ' + token,
              },
            });
            if (!response.ok && response.status !== 401) throw new Error('Unable to sign out. Please try again.');
          }
          localStorage.removeItem('logistics_auth_token');
          localStorage.removeItem('logistics_auth_session');
          localStorage.removeItem('sortingCenterId');
          localStorage.removeItem('sortingCenterName');
          window.location.href = '/sorting-center/login';
        } catch (error) {
          console.error('Logout error:', error);
          window.alert('Unable to sign out. Please try again.');
        }
      }
    };

    let interval;
    onUnmounted(() => {
      clearInterval(interval);
      document.removeEventListener('click', handleOutsideClick);
    });

    onMounted(() => {
      updateDateTime();
      interval = setInterval(updateDateTime, 60000);
      document.addEventListener('click', handleOutsideClick);

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
          // ignore
        }
      }
      const storedName = localStorage.getItem('sortingCenterName');
      if (storedName) userName.value = storedName;
    });

    return {
      isHubStaff,
      navItems,
      navGroups,
      sidebarOpen,
      accountOpen,
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

<style scoped>
.sidebar-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.22) transparent;
}

.sidebar-scrollbar::-webkit-scrollbar {
    width: 5px;
}

.sidebar-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.22);
    border-radius: 9999px;
}

.sidebar-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.4);
}

.sidebar-scrollbar::-webkit-scrollbar-button {
    display: none;
    width: 0;
    height: 0;
}
</style>
