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
      class="fixed inset-y-0 left-0 z-40 w-[252px] transform border-r border-[#065A63]/30 bg-gradient-to-b from-[#087F8C] via-[#076F7B] to-[#065A63] text-white shadow-xl transition-transform duration-200 lg:translate-x-0"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex h-full min-h-0 flex-col">
        <!-- Logo / Brand Header -->
        <div class="relative flex h-[80px] shrink-0 items-center justify-center border-b border-white/10 px-5">
          <a
            href="/sorting-center/dashboard"
            class="flex items-center justify-center rounded-xl bg-white px-3.5 py-1.5 shadow-sm transition hover:bg-slate-50"
            @click="sidebarOpen = false"
          >
            <img
              :src="logoUrl"
              alt="Alona"
              class="block h-7 w-auto max-w-[150px] object-contain"
            />
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
                    v-else-if="item.key === 'riders'"
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

        <!-- Center Status / Footer Card (Matches AdminLayout status card) -->
        <div class="shrink-0 border-t border-white/10 bg-black/10 p-3">
          <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/10 px-3 py-2.5 backdrop-blur-xs">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-[#087F8C] shadow-xs">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.8"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </span>

            <div class="min-w-0">
              <p class="truncate text-xs font-semibold text-white">Logistics Center</p>
              <div class="mt-0.5 flex items-center gap-1.5">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                <span class="truncate text-[11px] font-medium text-emerald-300">Operational</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="lg:pl-[252px]">
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

            <!-- Account Dropdown -->
            <div class="relative ml-1 border-l border-[#E5E7EB] pl-2 sm:pl-3">
              <button
                type="button"
                class="flex h-10 items-center gap-2 rounded-xl border border-transparent px-2.5 text-left transition hover:border-[#E5E7EB] hover:bg-[#E8F7F6]"
                :class="{ 'border-[#E5E7EB] bg-[#E8F7F6]': accountOpen }"
                @click.stop="accountOpen = !accountOpen"
              >
                <!-- Avatar circle with initial -->
                <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#E8F7F6] text-xs font-black text-[#087F8C]">
                  {{ userInitial }}
                </span>

                <span class="hidden max-w-[130px] sm:block">
                  <span class="block truncate text-xs font-extrabold text-[#1F2937]">
                    {{ userName }}
                  </span>
                  <span class="block text-[9px] text-[#64748B]">
                    {{ userRole }}
                  </span>
                </span>

                <svg
                  class="hidden h-3.5 w-3.5 text-[#64748B] transition-transform duration-200 sm:block"
                  :class="{ 'rotate-180': accountOpen }"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </button>

              <!-- Account Dropdown Menu Panel -->
              <transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="translate-y-1 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-1 opacity-0"
              >
                <div
                  v-if="accountOpen"
                  class="absolute right-0 top-[calc(100%+10px)] z-50 w-[280px] overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl shadow-black/10"
                  @click.stop
                >
                  <!-- Account Info Header -->
                  <div class="border-b border-[#E5E7EB] bg-[#F8FAF9] px-4 py-4">
                    <div class="flex items-center gap-3">
                      <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#E8F7F6] text-sm font-black text-[#087F8C]">
                        {{ userInitial }}
                      </span>
                      <div class="min-w-0">
                        <p class="truncate text-sm font-extrabold text-[#1F2937]">
                          {{ userName }}
                        </p>
                        <p class="truncate text-xs text-[#64748B]">
                          {{ userRole }}
                        </p>
                      </div>
                    </div>
                  </div>

                  <!-- Account Action Links -->
                  <div class="p-2">
                    <a
                      href="/sorting-center/dashboard"
                      class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                      @click="accountOpen = false"
                    >
                      <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h8V3H3v10zm10 8h8V11h-8v10zM3 21h8v-4H3v4zm10-18v4h8V3h-8z" />
                        </svg>
                      </span>
                      Dashboard
                    </a>

                    <a
                      href="/sorting-center/order-management"
                      class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                      @click="accountOpen = false"
                    >
                      <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                      </span>
                      Order Operations
                    </a>
                  </div>

                  <!-- Logout Button -->
                  <div class="border-t border-[#E5E7EB] p-2">
                    <button
                      type="button"
                      class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-red-600 transition hover:bg-red-50 hover:text-red-700"
                      @click="logout"
                    >
                      <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-red-600">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                      </span>
                      Sign out
                    </button>
                  </div>
                </div>
              </transition>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Slot Content Area -->
      <main class="p-4 sm:p-6 lg:p-8">
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
    ];

    const navGroups = [
      { label: 'Overview', items: [navItems[0]] },
      { label: 'Operations', items: [navItems[1], navItems[2], navItems[3], navItems[4], navItems[5], navItems[6]] },
      { label: 'Communication', items: [navItems[7]] },
    ];

    const userName = ref('Sorting Center');
    const userRole = ref('Logistics Center');
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
            await fetch('/logout', {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': csrfToken || '',
                'Accept': 'application/json',
              },
            });
          } else if (token) {
            await fetch('/api/logistics/logout', {
              method: 'POST',
              headers: {
                Accept: 'application/json',
                Authorization: 'Bearer ' + token,
              },
            });
          }
        } catch (error) {
          console.error('Logout error:', error);
        } finally {
          localStorage.removeItem('logistics_auth_token');
          localStorage.removeItem('logistics_auth_session');
          localStorage.removeItem('sortingCenterId');
          localStorage.removeItem('sortingCenterName');
          window.location.href = '/sorting-center/login';
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
