<template>
  <div class="sc-settings">
    <Head :title="i18n.settings_page.title" />

    <Heading :level="1" class="sc-thread__heading">{{ i18n.settings_page.title }}</Heading>

    <Card class="sc-card">
      <div v-if="loading" class="sc-empty">…</div>

      <div v-else-if="loadError" class="sc-empty sc-empty--error">{{ loadError }}</div>

      <form v-else class="sc-form" @submit.prevent="save">
        <div v-if="!status.telegram_configured" class="sc-note">{{ i18n.telegram_not_configured }}</div>

        <p class="sc-hint">{{ i18n.settings_page.fields_helper }}</p>

        <div v-for="field in ['email_field', 'phone_field']" :key="field" class="sc-field">
          <label :for="'sc-' + field">{{ i18n.settings_page[field] }}</label>
          <select :id="'sc-' + field" v-model="form[field]" class="sc-input">
            <option v-for="mode in ['hidden', 'optional', 'required']" :key="mode" :value="mode">
              {{ i18n.settings_page['field_' + mode] }}
            </option>
          </select>
          <div v-if="errors[field]" class="sc-error">{{ errors[field][0] }}</div>
        </div>

        <div v-for="field in ['auto_close_hours', 'delete_after_days']" :key="field" class="sc-field">
          <label :for="'sc-' + field">{{ i18n.settings_page[field] }}</label>
          <input :id="'sc-' + field" v-model.number="form[field]" type="number" min="0" step="1" class="sc-input" />
          <p class="sc-hint">{{ i18n.settings_page[field + '_helper'] }}</p>
          <div v-if="errors[field]" class="sc-error">{{ errors[field][0] }}</div>
        </div>

        <div class="sc-actions">
          <button type="submit" class="sc-btn" :disabled="saving">{{ i18n.save || 'Save' }}</button>
        </div>
      </form>
    </Card>
  </div>
</template>

<script>
import { api, errorMessage, i18n } from '../api'

export default {
  data() {
    return {
      i18n: i18n(),
      loading: true,
      loadError: null,
      saving: false,
      form: {},
      errors: {},
      status: { telegram_configured: true },
    }
  },

  async mounted() {
    try {
      const { data } = await Nova.request().get(api('/settings'))

      this.form = { ...data.data.settings }
      this.status = data.data
    } catch (error) {
      this.loadError = errorMessage(error, this.i18n.load_error)
    } finally {
      this.loading = false
    }
  },

  methods: {
    async save() {
      this.saving = true
      this.errors = {}

      try {
        const { data } = await Nova.request().put(api('/settings'), this.form)

        this.form = { ...data.data.settings }
        Nova.success(this.i18n.settings_saved)
      } catch (error) {
        if (error.response && error.response.status === 422) {
          this.errors = error.response.data.errors || {}
        } else {
          Nova.error(errorMessage(error, this.i18n.reply_error))
        }
      } finally {
        this.saving = false
      }
    },
  },
}
</script>
