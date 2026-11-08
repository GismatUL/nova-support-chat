<template>
  <div class="sc-thread">
    <Heading :level="1" class="sc-thread__heading">{{ panel?.name || i18n.messages }}</Heading>

    <Card class="sc-card">
      <div v-if="loading" class="sc-empty">…</div>

      <div v-else-if="loadError" class="sc-empty sc-empty--error">{{ loadError }}</div>

      <template v-else>
        <div ref="list" class="sc-messages" role="log" aria-live="polite">
          <div v-if="!messages.length" class="sc-empty">{{ i18n.no_messages }}</div>

          <div v-for="message in messages" :key="message.id" :class="['sc-msg', 'sc-msg--' + message.sender]">
            <!-- Text interpolation only: customer input is always escaped. -->
            <div class="sc-bubble">{{ message.message }}</div>
            <div class="sc-meta">
              <span v-if="senderLabel(message)">{{ senderLabel(message) }} · </span>
              <span>{{ formatTime(message.created_at) }}</span>
              <span v-if="deliveryLabel(message)" :class="['sc-delivery', { 'sc-delivery--failed': message.telegram_failed }]">
                · {{ deliveryLabel(message) }}
              </span>
            </div>
          </div>
        </div>

        <div v-if="!telegramConfigured" class="sc-note">{{ i18n.telegram_not_configured }}</div>

        <div v-if="isClosed" class="sc-closed">{{ i18n.reply_closed }}</div>

        <form v-else-if="can.reply" class="sc-reply" @submit.prevent="send">
          <textarea
            ref="textarea"
            v-model="draft"
            class="sc-textarea"
            rows="3"
            :maxlength="maxLength"
            :placeholder="i18n.reply_placeholder"
            :aria-label="i18n.reply_placeholder"
            :disabled="sending"
            @keydown.enter.ctrl.prevent="send"
            @keydown.enter.meta.prevent="send"
          />
          <div v-if="sendError" class="sc-error" role="alert">{{ sendError }}</div>
          <div class="sc-actions">
            <span class="sc-hint">{{ i18n.reply_hint }}</span>
            <button type="button" class="sc-btn sc-btn--secondary" :disabled="closing" @click="closeConversation">
              {{ i18n.close_conversation }}
            </button>
            <button type="submit" class="sc-btn" :disabled="sending || !draft.trim()">{{ i18n.send }}</button>
          </div>
        </form>
      </template>
    </Card>
  </div>
</template>

<script>
import { api, config, errorMessage, i18n } from '../api'

export default {
  props: ['resourceName', 'resourceId', 'panel'],

  data() {
    return {
      i18n: i18n(),
      maxLength: config().maxLength || 2000,
      loading: true,
      loadError: null,
      conversation: null,
      messages: [],
      version: null,
      can: { reply: false, manage: false },
      telegramConfigured: true,
      draft: '',
      sending: false,
      sendError: null,
      closing: false,
      timer: null,
      failures: 0,
    }
  },

  computed: {
    isClosed() {
      return this.conversation && this.conversation.status === 'closed'
    },
  },

  mounted() {
    this.load()
    document.addEventListener('visibilitychange', this.onVisibility)
  },

  beforeUnmount() {
    clearTimeout(this.timer)
    document.removeEventListener('visibilitychange', this.onVisibility)
  },

  methods: {
    url(path = '') {
      return api('/conversations/' + encodeURIComponent(this.resourceId) + path)
    },

    async load() {
      try {
        const { data } = await Nova.request().get(this.url())

        this.apply(data.data)
        this.can = data.data.can || this.can
        this.telegramConfigured = data.data.telegram_configured !== false
        this.loadError = null
        this.$nextTick(() => this.scrollToBottom(true))
      } catch (error) {
        this.loadError = errorMessage(error, this.i18n.load_error)
      } finally {
        this.loading = false
        this.schedule()
      }
    },

    apply(payload) {
      this.conversation = payload.conversation
      this.messages = payload.messages || []
      this.version = payload.version
    },

    schedule() {
      clearTimeout(this.timer)

      if (this.loadError) {
        return
      }

      const base = config().pollInterval || 4000
      const delay = this.failures ? Math.min(base * 2 ** this.failures, 60000) : base

      this.timer = setTimeout(this.poll, delay)
    },

    async poll() {
      if (document.hidden) {
        return // resumed by visibilitychange
      }

      try {
        const { data } = await Nova.request().get(this.url('/messages'), { params: { version: this.version } })

        this.failures = 0

        if (data.data.changed) {
          const stick = this.isNearBottom()
          this.apply(data.data)
          this.$nextTick(() => this.scrollToBottom(stick))
        }
      } catch (error) {
        this.failures++
      } finally {
        this.schedule()
      }
    },

    onVisibility() {
      if (!document.hidden && !this.loading) {
        clearTimeout(this.timer)
        this.poll()
      }
    },

    async send() {
      const text = this.draft.trim()

      if (!text || this.sending) {
        return
      }

      this.sending = true
      this.sendError = null

      try {
        await Nova.request().post(this.url('/messages'), { message: text })
        this.draft = ''
        clearTimeout(this.timer)
        await this.poll()
        this.$nextTick(() => {
          this.scrollToBottom(true)
          this.$refs.textarea && this.$refs.textarea.focus()
        })
      } catch (error) {
        this.sendError = errorMessage(error, this.i18n.reply_error)
      } finally {
        this.sending = false
      }
    },

    async closeConversation() {
      if (!window.confirm(this.i18n.close_confirm)) {
        return
      }

      this.closing = true

      try {
        await Nova.request().post(this.url('/close'))
        Nova.success(this.i18n.closed_success)
        clearTimeout(this.timer)
        await this.poll()
      } catch (error) {
        Nova.error(errorMessage(error, this.i18n.reply_error))
      } finally {
        this.closing = false
      }
    },

    senderLabel(message) {
      const senders = this.i18n.senders || {}

      if (message.sender === 'customer') {
        return this.conversation ? this.conversation.customer_name : senders.customer
      }

      if (message.sender === 'operator') {
        const via = message.source === 'admin' ? this.i18n.via_admin : message.source === 'telegram' ? this.i18n.via_telegram : null
        const name = message.operator_name || senders.operator

        return via ? `${name} (${via})` : name
      }

      return message.operator_name ? (this.i18n.closed_by || '').replace(':name', message.operator_name) : ''
    },

    deliveryLabel(message) {
      const delivery = this.i18n.delivery || {}

      switch (message.telegram_status) {
        case 'sent':
          return delivery.sent
        case 'failed':
          return (delivery.failed || '').replace(':attempts', message.telegram_attempts)
        case 'pending':
        case 'sending':
          return delivery.pending
        default:
          return null
      }
    },

    formatTime(iso) {
      const date = new Date(iso)

      return isNaN(date.getTime()) ? '' : date.toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })
    },

    isNearBottom() {
      const list = this.$refs.list

      return !list || list.scrollHeight - list.scrollTop - list.clientHeight < 80
    },

    scrollToBottom(force) {
      const list = this.$refs.list

      if (list && force) {
        list.scrollTop = list.scrollHeight
      }
    },
  },
}
</script>
