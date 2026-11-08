// Thin wrapper around Nova's axios instance (sends the session cookie + CSRF token for us).

export function config() {
  return Nova.config('supportChat') || {}
}

export function i18n() {
  return config().i18n || {}
}

export function api(path) {
  return (config().apiBase || '/nova-vendor/support-chat') + path
}

export function errorMessage(error, fallback) {
  const response = error && error.response

  if (response && response.status === 403) {
    return i18n().forbidden || fallback
  }

  if (response && response.data) {
    if (response.data.errors) {
      const first = Object.values(response.data.errors)[0]

      if (first && first.length) {
        return first[0]
      }
    }

    if (response.data.message) {
      return response.data.message
    }
  }

  return fallback
}
