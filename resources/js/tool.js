import ConversationThread from './components/ConversationThread.vue'
import Settings from './pages/Settings.vue'

Nova.booting(app => {
  app.component('support-chat-thread', ConversationThread)
  Nova.inertia('SupportChatSettings', Settings)
})
