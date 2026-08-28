import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'

// Style Global & Icons
import './assets/main.css'
import 'primeicons/primeicons.css'

// PrimeVue Config (Preset Aura)
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'

// Vue Toastification
import Toast from 'vue-toastification'
import 'vue-toastification/dist/index.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(PrimeVue, {
    theme: {
        preset: Aura
    }
})
app.use(Toast, { position: 'top-right', timeout: 3000 })

app.mount('#app')