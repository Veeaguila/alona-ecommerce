<template>
  <sc-layout title="Messages">
    <div class="app-page-shell">
      <div v-if="!token" class="app-panel app-empty-state">
        <h2 class="text-amber-950 app-section-title">Authentication required</h2>
        <p class="mt-2 text-sm text-amber-800">Log in with an approved sorting center account to access messages.</p>
        <a href="/sorting-center/login" class="mt-5 inline-flex app-button-primary">
          Go to login
        </a>
      </div>

      <template v-else>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between app-panel app-panel--padded app-toolbar">
          <div>
            <h2 class="app-section-title">Sorting Center Communications</h2>
            <p class="mt-1 text-sm text-gray-600">Message approved riders connected to this sorting center.</p>
          </div>
          <button
            type="button"
            class="app-button-primary"
            @click="composeMessage"
          >
            New Message
          </button>
        </div>

        <div v-if="errorMessage" class="app-feedback app-feedback--error" role="alert">
          {{ errorMessage }}
        </div>

        <div
          v-if="composing"
          class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4"
          @click.self="closeComposer"
        >
          <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="new-message-title"
            class="w-full max-w-lg app-panel app-panel--padded"
          >
            <div class="flex items-start justify-between gap-4">
              <div>
                <h2 id="new-message-title" class="app-section-title">New message</h2>
                <p class="mt-1 text-sm text-gray-600">Start a conversation with an approved rider from your sorting center.</p>
              </div>
              <button
                type="button"
                aria-label="Close new message"
                class="rounded-lg px-3 py-1 text-xl text-gray-500 hover:bg-gray-100"
                @click="closeComposer"
              >
                &times;
              </button>
            </div>

            <p v-if="composerError" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
              {{ composerError }}
            </p>

            <form class="mt-5 space-y-4" @submit.prevent="sendMessage">
              <label class="block text-sm font-semibold text-gray-700" for="new-message-recipient">
                To
                <select
                  id="new-message-recipient"
                  v-model="selectedRecipientId"
                  required
                  :disabled="loadingRecipients || recipients.length === 0"
                  class="mt-1 block w-full disabled:bg-gray-100 app-input"
                >
                  <option value="">
                    {{ loadingRecipients ? 'Loading riders...' : 'Choose a rider' }}
                  </option>
                  <option v-for="recipient in recipients" :key="recipient.id" :value="recipient.id">
                    {{ recipient.name }}
                  </option>
                </select>
              </label>
              <p v-if="!loadingRecipients && recipients.length === 0 && !composerError" class="text-sm text-amber-700">
                There are no approved, active riders available to message.
              </p>

              <label class="block text-sm font-semibold text-gray-700" for="new-message-body">
                Message
                <textarea
                  id="new-message-body"
                  v-model="newMsg"
                  required
                  maxlength="4000"
                  rows="4"
                  placeholder="Write your message..."
                  class="mt-1 block w-full resize-y app-input"
                ></textarea>
              </label>
              <div class="flex justify-end gap-3">
                <button
                  type="button"
                  class="text-gray-700 app-button-secondary"
                  @click="closeComposer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="sending || loadingRecipients || !selectedRecipientId || !newMsg.trim()"
                  class="disabled:opacity-50 app-button-primary"
                >
                  {{ sending ? 'Sending...' : 'Send message' }}
                </button>
              </div>
            </form>
          </section>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
          <section class="flex flex-col overflow-hidden app-panel">
            <div class="border-b border-[var(--app-border)] bg-gray-50/70 p-4">
              <input
                v-model="searchQuery"
                type="search"
                placeholder="Search conversations..."
                class="w-full app-input"
              />
            </div>
            <div v-if="loadingConversations" class="p-6 text-sm text-gray-500">Loading conversations...</div>
            <div v-else-if="filteredConversations.length === 0" class="p-6 text-sm text-gray-500">
              No conversations yet. Start a message with an approved rider.
            </div>
            <div v-else class="max-h-[420px] divide-y divide-[#DCE7E2] overflow-y-auto">
              <button
                v-for="conv in filteredConversations"
                :key="conv.id"
                type="button"
                :class="selectedConvId === conv.id ? 'border-l-4 border-[#FFA500] bg-teal-50/70' : 'hover:bg-gray-50'"
                class="flex w-full items-start justify-between gap-3 p-4 text-left transition"
                @click="openConversation(conv.id)"
              >
                <span class="flex min-w-0 items-center gap-3">
                  <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--app-primary)] text-sm font-bold text-white">
                    {{ initials(conv.recipient?.name) }}
                  </span>
                  <span class="min-w-0">
                    <span class="block truncate text-sm font-bold text-[#333333]">{{ conv.recipient?.name || 'Account' }}</span>
                    <span class="block truncate text-xs text-[var(--app-text-muted)]">{{ conv.latest_message?.body || 'No messages' }}</span>
                  </span>
                </span>
                <span class="shrink-0 text-right">
                  <span class="block text-[10px] text-gray-400">{{ formatTime(conv.updated_at) }}</span>
                  <span v-if="conv.unread_count" class="mt-1 inline-block rounded-full bg-[#FFA500] px-2 py-0.5 text-[10px] font-bold text-white">
                    {{ conv.unread_count }}
                  </span>
                </span>
              </button>
            </div>
          </section>

          <section class="flex min-h-[420px] flex-col justify-between lg:col-span-2 app-panel">
            <div class="flex items-center justify-between border-b border-[var(--app-border)] bg-gray-50/70 p-4">
              <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--app-primary)] text-sm font-bold text-white">
                  {{ initials(activeConversation?.recipient?.name) }}
                </span>
                <div>
                  <h3 class="app-section-title">{{ activeConversation?.recipient?.name || 'Select a conversation' }}</h3>
                  <p class="text-xs capitalize text-[var(--app-text-muted)]">{{ activeConversation?.recipient?.role?.replaceAll('_', ' ') || 'Direct message' }}</p>
                </div>
              </div>
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto p-6">
              <p v-if="!selectedConvId && !composing" class="py-16 text-center text-sm text-gray-500">
                Select a conversation or start a new message.
              </p>
              <p v-else-if="loadingMessages" class="py-16 text-center text-sm text-gray-500">Loading messages...</p>
              <p v-else-if="composing && messages.length === 0" class="py-16 text-center text-sm text-gray-500">
                Choose a rider below and send the first message.
              </p>
              <div
                v-for="msg in messages"
                :key="msg.id"
                :class="msg.is_mine ? 'items-end' : 'items-start'"
                class="flex flex-col"
              >
                <div
                  :class="msg.is_mine ? 'rounded-br-none bg-[#008B8B] text-white' : 'rounded-bl-none bg-gray-100 text-[#333333]'"
                  class="max-w-md rounded-xl px-4 py-2.5 text-sm shadow-xs"
                >
                  <p class="whitespace-pre-wrap break-words">{{ msg.body }}</p>
                </div>
                <span class="mt-1 text-[10px] font-medium text-gray-400">{{ formatTime(msg.created_at) }}</span>
              </div>
            </div>

            <form v-if="!composing" class="flex flex-col gap-3 border-t border-[var(--app-border)] bg-gray-50/50 p-4 sm:flex-row" @submit.prevent="sendMessage">
              <input
                v-model="newMsg"
                type="text"
                maxlength="4000"
                :disabled="!selectedConvId"
                placeholder="Type your message..."
                class="min-w-0 flex-1 disabled:bg-gray-100 app-input"
              />
              <button
                type="submit"
                :disabled="sending || !selectedConvId || !newMsg.trim()"
                class="disabled:opacity-50 app-button-primary"
              >
                {{ sending ? 'Sending...' : 'Send' }}
              </button>
            </form>
          </section>
        </div>
      </template>
    </div>
  </sc-layout>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import ScLayout from '@/Layouts/ScLayout.vue';

export default {
  name: 'Messages',
  components: { ScLayout },
  setup() {
    const token = ref(localStorage.getItem('logistics_auth_token'));
    const searchQuery = ref('');
    const selectedConvId = ref(null);
    const selectedRecipientId = ref('');
    const newMsg = ref('');
    const conversations = ref([]);
    const recipients = ref([]);
    const messages = ref([]);
    const composing = ref(false);
    const sending = ref(false);
    const loadingRecipients = ref(false);
    const loadingConversations = ref(false);
    const loadingMessages = ref(false);
    const errorMessage = ref('');
    const composerError = ref('');
    const activeConversation = computed(() =>
      conversations.value.find((conversation) => conversation.id === selectedConvId.value) || null
    );
    const filteredConversations = computed(() => {
      const query = searchQuery.value.trim().toLowerCase();
      if (!query) return conversations.value;
      return conversations.value.filter((conversation) =>
        conversation.recipient?.name?.toLowerCase().includes(query)
      );
    });

    async function requestJson(path, options = {}) {
      const response = await fetch(`/api/sorting-center/messages${path}`, {
        ...options,
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token.value}`,
          ...(options.body ? { 'Content-Type': 'application/json' } : {}),
          ...(options.headers || {}),
        },
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || 'The messaging request failed.');
      return payload;
    }

    async function loadConversations() {
      loadingConversations.value = true;
      errorMessage.value = '';
      try {
        const payload = await requestJson('/conversations');
        conversations.value = payload.data || [];
      } catch (error) {
        errorMessage.value = error.message;
      } finally {
        loadingConversations.value = false;
      }
    }

    async function loadRecipients() {
      loadingRecipients.value = true;
      composerError.value = '';
      try {
        const payload = await requestJson('/recipients');
        recipients.value = payload.data || [];
      } catch (error) {
        composerError.value = error.message;
      } finally {
        loadingRecipients.value = false;
      }
    }

    async function openConversation(id) {
      composing.value = false;
      selectedConvId.value = id;
      loadingMessages.value = true;
      errorMessage.value = '';
      try {
        const payload = await requestJson(`/conversations/${id}`);
        messages.value = payload.data || [];
        const selected = conversations.value.find((conversation) => conversation.id === id);
        if (selected) selected.unread_count = 0;
      } catch (error) {
        errorMessage.value = error.message;
      } finally {
        loadingMessages.value = false;
      }
    }

    async function composeMessage() {
      errorMessage.value = '';
      selectedConvId.value = null;
      selectedRecipientId.value = '';
      newMsg.value = '';
      messages.value = [];
      composing.value = true;
      await loadRecipients();
    }

    function closeComposer() {
      composing.value = false;
      composerError.value = '';
      newMsg.value = '';
    }

    async function sendMessage() {
      const body = newMsg.value.trim();
      if (!body || sending.value) return;
      sending.value = true;
      errorMessage.value = '';
      try {
        const payload = await requestJson(
          composing.value ? '' : `/conversations/${selectedConvId.value}`,
          {
            method: 'POST',
            body: JSON.stringify({
              body,
              ...(composing.value ? { recipient_id: selectedRecipientId.value } : {}),
            }),
          }
        );
        newMsg.value = '';
        composing.value = false;
        await loadConversations();
        await openConversation(payload.conversation_id);
      } catch (error) {
        if (composing.value) {
          composerError.value = error.message;
        } else {
          errorMessage.value = error.message;
        }
      } finally {
        sending.value = false;
      }
    }

    function initials(name) {
      return (name || '?').split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    }

    function formatTime(value) {
      if (!value) return '';
      return new Date(value).toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
      });
    }

    onMounted(loadConversations);

    return {
      token,
      searchQuery,
      selectedConvId,
      selectedRecipientId,
      newMsg,
      conversations,
      filteredConversations,
      recipients,
      messages,
      composing,
      sending,
      loadingRecipients,
      loadingConversations,
      loadingMessages,
      errorMessage,
      composerError,
      activeConversation,
      openConversation,
      composeMessage,
      closeComposer,
      sendMessage,
      initials,
      formatTime,
    };
  },
};
</script>
