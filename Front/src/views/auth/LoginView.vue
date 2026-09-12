<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/authStore'
import { useToast } from 'vue-toastification'

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

const email = ref('admin.tec@core.com')
const password = ref('password')
const rememberMe = ref(false)
const isLoading = ref(false)
const errorMsg = ref('')

const handleLogin = async () => {
  isLoading.value = true
  errorMsg.value = ''
  try {
    await auth.login(email.value, password.value)
    toast.success('Bienvenido')
    // Respeta ?redirect= tras el login (session expiry, deep-links)
    const redirect = router.currentRoute.value.query.redirect as string | undefined
    router.push(redirect && redirect.startsWith('/') ? redirect : { name: 'dashboard' })
  } catch (e: any) {
    const status = e?.response?.status
    if (status === 419) {
      errorMsg.value = 'Sesión expirada. Recarga la página (F5) e intenta de nuevo. Si persiste, limpia cookies del sitio.'
    } else if (status === 422 || status === 401) {
      errorMsg.value = e?.response?.data?.errors?.email?.[0] || e?.response?.data?.message || 'Credenciales incorrectas. Verifica tu correo y contraseña.'
    } else if (status === 403) {
      errorMsg.value = e?.response?.data?.message || 'Usuario desactivado. Contacta al administrador.'
    } else {
      errorMsg.value = e?.response?.data?.message || 'Error de conexión. Verifica que la API esté en http://localhost:8000'
    }
    if (errorMsg.value.includes('CSRF token mismatch')) {
      errorMsg.value = 'Sesión expirada (CSRF). Recarga la página e intenta de nuevo.'
    }
    toast.error(errorMsg.value)
  } finally {
    isLoading.value = false
  }
}
</script>

<!------------------------------------------------------------------------------------------------------------------>

<template>
    <div class="split-screen-login">
    
    <!-- Panel izquierdo -->
    <div class="left-pane">

        <div class="form-container">

            <!------------ Header ------------>
            <div class="brand">
            <div class="logo-placeholder">coreReport</div> 
            
            <h2>Bienvenido de vuelta!</h2>
            <p class="subtitle">Ingrese sus datos para continuar.</p>
        </div>
            <!------------ Formulario ------------>
        <form @submit.prevent="handleLogin">
            <div class="form-group">
                <label>Correo electrónico</label>
                <input 
                v-model="email" 
                type="email" 
                placeholder="admin.tec@core.com" 
                required 
                />
            </div>

            <div class="form-group">
                <label>Contraseña</label>
                <input 
                v-model="password" 
                type="password" 
                placeholder="••••••••" 
                required 
                />
            </div>

            <div class="form-actions">
                <label class="checkbox-label">
                <input type="checkbox" v-model="rememberMe">
                <span>Recordarme</span>
                </label>
                <a href="#" class="forgot-link">¿Olvidaste tu contraseña?</a>
            </div>

            <p v-if="errorMsg" class="error-msg">{{ errorMsg }}</p>
            <button type="submit" :disabled="isLoading" class="btn-primary">
                {{ isLoading ? 'Ingresando...' : 'Ingresar' }}
            </button>
        </form>

        <p class="signup-text">
          ¿No tienes una cuenta? <router-link :to="{name: 'register' }">Regístrate aquí</router-link>
        </p>

      </div>
    </div>

    <!-- Panel derecho -->
    <div class="right-pane">
        <img src = "/src/assets/login-image.png" alt = "Login Visual" class = "login-image" />
    </div>

    </div>
</template>

<!------------------------------------------------------------------------------------------------------------------>

<style scoped>

/* --------- LAYOUT ---------*/
.split-screen-login {
    display: flex;
    min-height: 100vh;
    width: 100vw;
    font-family: 'Inter', sans-serif;
}
.left-pane{
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #ffffff;
    padding: 2rem;
}

.right-pane {
    flex: 1;
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    position: relative;
    overflow: hidden;
}

.login-image {
    width: 100%;
    height: 100%;
    opacity: 0.7;
    object-fit: cover;
    position: absolute;
    top: 0;
    left: 0;
    z-index: 1;
}

@media (max-width: 768px) {
    .right-pane {
        display: none;
    }
}

/* --------- FORM --------- */
.form-container {
    width: 100%;
    max-width: 380px;
}

.brand {
    margin-bottom: 2.5rem;
}

.logo-placeholder {
    font-weight: 900;
    font-size: 1.5rem;
    color: #1e3a8a;
    margin-bottom: 0.5rem;
}

h2 {
    font-size: 1.8rem;
    color: #111827;
    margin: 0 0 0.5rem 0;
}

.subtitle {
    color: #6b7280;
    font-size: 0.95rem;
}

.form-group {
    margin-bottom: 1.2rem;
}

label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.4rem;
}

input[type="email"],
input[type="password"] {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 1rem;
    box-sizing: border-box;
    transition: all 0.2s;
}

input:focus {
    border-color: #1e3a8a;
    box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
    outline: none;
}

/* ------------ ACTIONS --------- */
.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    font-size: 0.9rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #374151;
    cursor: pointer;
}

.forgot-link {
    color: #1e3a8a;
    font-weight: 600;
    text-decoration: none;
}

.btn-primary {
    width: 100%;
    padding: 0.9rem;
    background-color: #1e3a8a;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-primary:hover {
    background-color: #172554;
}

.btn-primary:disabled {
    background-color: #93c5fd;
    cursor: not-allowed;
}

/* --------- SIGN UP --------- */
.signup-text {
    text-align: center;
    margin-top: 2rem;
    color: #6b7280;
    font-size: 0.9rem;
}

.signup-text a {
    color: #1e3a8a;
    font-weight: 600;
    text-decoration: none;
}
.error-msg { color:#dc2626; background:#fee2e2; padding:0.6rem; border-radius:6px; margin-bottom:1rem; font-size:0.9rem; }
</style>