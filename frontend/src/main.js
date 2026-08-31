import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
import App from './App.vue'
import router from './router'

// Style Global & Icons
import './assets/main.css'
import 'primeicons/primeicons.css'

const app = createApp(App)

// Custom Directive: v-click-outside (Robust with event.composedPath)
app.directive('click-outside', {
  mounted(el, binding) {
    el._clickOutside = (event) => {
      const path = event.composedPath ? event.composedPath() : []
      if (path.includes(el) || el === event.target || el.contains(event.target)) {
        return
      }
      binding.value(event)
    }
    // Defer listener registration so the trigger click doesn't prematurely fire
    setTimeout(() => {
      document.addEventListener('click', el._clickOutside)
    }, 0)
  },
  unmounted(el) {
    document.removeEventListener('click', el._clickOutside)
  },
})

app.use(createPinia())
app.use(router)
app.use(PrimeVue, {
  theme: {
    preset: Aura,
    options: {
      darkModeSelector: '.dark',
    },
  },
})

app.mount('#app')