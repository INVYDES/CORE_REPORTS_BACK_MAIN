<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import client from '../../api/client'
import { useToast } from 'vue-toastification'

const router = useRouter()
const toast = useToast()
const isLoading = ref(false)

const fiscalRegimes = [
    { code: '601', label: '601 - General de Ley Personas Morales' },
    { code: '626', label: '626 - Simplificado de Confianza' }
]
const cfdiUses = [
    { code: 'G01', label: 'G01 - Adquisición de mercancías' },
    { code: 'G03', label: 'G03 - Gastos en general' },
]
const plans = [
    { id: 'free', label: 'Prueba gratuita (10 pedidos/día por 7 días)' },
    { id: 'monthly', label: 'Mensual - $399.00 IVA Incluido' },
    { id: 'annual', label: 'Anual - $3830.40 (20% descuento)' }
]
const formData = reactive({
    companyName: '', rfc: '', fiscalRegime: '', cfdiUse: '',
    username: '', firstName: '', lastNamePaternal: '', lastNameMaternal: '', email: '', password: '',
    street: '', colony: '', zipCode: '', city: '', state: '', municipality: '', phone: '',
    planType: '', paymentMethod: '', paymentForm: '', invoiceRequired: false
})
const showLicencias = ref(false)
const licencias = ref<any[]>([])
const selectedLicencia = ref<number | null>(null)

const handleRegister = async () => {
    isLoading.value = true
    try {
        const { data } = await client.post('/public/register-compania', {
            companyName: formData.companyName,
            rfc: formData.rfc,
            fiscalRegime: formData.fiscalRegime,
            cfdiUse: formData.cfdiUse,
            email: formData.email,
            password: formData.password,
            firstName: formData.firstName,
            lastNamePaternal: formData.lastNamePaternal,
            lastNameMaternal: formData.lastNameMaternal,
            phone: formData.phone,
            planType: formData.planType,
        })
        // Guardar token y loguear automáticamente
        if (data.token && data.user) {
            localStorage.setItem('token', data.token)
            localStorage.setItem('user', JSON.stringify(data.user))
            // @ts-ignore
            const { useAuthStore } = await import('../../stores/authStore')
            const auth = useAuthStore()
            // @ts-ignore
            auth.token = data.token
            // @ts-ignore
            auth.currentUser = data.user
        }
        // Si eligió trial o no eligió plan, ir directo al dashboard
        if (!formData.planType || formData.planType === 'free' || formData.planType === 'trial') {
            toast.success('Cuenta creada con plan Trial. ¡Bienvenido!')
            router.push({ name: 'dashboard' })
            return
        }
        // Para planes de pago, mostrar selector de licencias con Mercado Pago
        toast.success('Cuenta creada. Selecciona tu licencia para continuar.')
        // Cargar licencias disponibles
        try {
            const res = await client.get('/licencias/disponibles')
            licencias.value = res.data.data || res.data || []
        } catch {
            // Fallback hardcode si no hay endpoint
            licencias.value = [
                { id: 2, nombre: 'Mensual', tipo: 'mensual', precio: 399, descripcion: 'Plan mensual' },
                { id: 3, nombre: 'Anual', tipo: 'anual', precio: 3830.40, descripcion: 'Plan anual 20% descuento' },
            ]
        }
        showLicencias.value = true
    } catch(e:any){ toast.error(e?.response?.data?.message || JSON.stringify(e?.response?.data?.errors) || 'Error al registrar') } finally { isLoading.value=false }
}

const pagarConMercadoPago = async () => {
    if (!selectedLicencia.value) {
        toast.error('Selecciona una licencia')
        return
    }
    isLoading.value = true
    try {
        const res = await client.post(`/licencias/${selectedLicencia.value}/comprar-mercadopago`)
        const initPoint = res.data.init_point || res.data.initPoint
        if (initPoint) {
            toast.success('Redirigiendo a Mercado Pago...')
            window.location.href = initPoint
        } else {
            toast.success('Licencia activada')
            router.push({ name: 'dashboard' })
        }
    } catch(e:any){ toast.error(e?.response?.data?.message || 'Error al crear preferencia de pago') } finally { isLoading.value=false }
}
</script>

<template>
    <div class="split-screen-register">

        <!-- DIVISIÓN PANEL IZQUIERDO-->
        <div class="left-pane">
            <div class="form-container">
        
                <!-- Datos cabecera (Empresa y demás cosas lol) -->
                <div class="brand">
                    <div class="logo-placeholder">coreReports</div> 
                    <h2>Crear Cuenta</h2>
                    <p class="subtitle">Ingrese los datos de su compañia para iniciar.</p>
                </div>

                <form @submit.prevent="handleRegister">
                    <!-- SECCIÓN 1 - INFORMACIÓN DE LA COMPAÑÍA-->
                    <div class="form-section">
                        <h3>
                            <span class="step-num">1</span> Información de la compañía
                        </h3>
                
                        <!-- Nombre de la empresa-->
                        <div class="form-group">
                            <label>Nombre de la Empresa *</label>
                            <input
                                v-model="formData.companyName"
                                type="text"
                                required
                                placeholder="MATERIALES, TECNOLOGÍA Y AUTOMATIZACIÓN S.A. de C.V."
                            />
                        </div>
                
                        <!-- 2 input fields in a single row -->
                        <div class="row">

                        <!-- RFC -->
                        <div class="form-group half">
                            <label>RFC *</label>
                            <input v-model="formData.rfc" type="text" required placeholder="XAXX010101000" />
                        </div>

                        <!-- CFDI -->
                        <div class="form-group half">
                            <label>Uso CFDI *</label>
                            <select v-model="formData.cfdiUse" required>
                            <option value=""disabled>
                                Seleccione...
                            </option>
                            <option v-for="use in cfdiUses" :key="use.code" :value="use.code">
                                {{ use.label }}
                            </option>
                            </select>
                        </div>

                    </div>

                <!-- Régimen Fiscal -->
                <div class="form-group">
                    <label>Régimen Fiscal *</label>
                    <select v-model="formData.fiscalRegime" required>
                        <option value="" disabled>
                            Seleccione Régimen Fiscal...
                        </option>
                        <option v-for="regime in fiscalRegimes" :key="regime.code" :value="regime.code">
                            {{ regime.label }}
                        </option>
                    </select>
                </div>

                    </div>

                    <!-- SECCIÓN 2 - INFORMACIÓN DEL ADMINISTRADOR -->
                    <div class="form-section">
                        <h3>
                            <span class="step-num">2</span> Información del administrador
                        </h3>

                        <!-- 2 input fields in a single row -->
                        <div class="row">

                            <!--Nombre de usuario-->                    
                            <div class="form-group half">
                                <label>Nombre de Usuario *</label>
                                <input
                                    v-model="formData.username"
                                    type="text"
                                    required
                                    placeholder="admin_mta"
                                />
                            </div>
                    
                            <!--Correo electrónico-->
                            <div class="form-group half">
                                <label>Correo electrónico *</label>
                                <input
                                    v-model="formData.email"
                                    type="email"
                                    required
                                    placeholder="admin@correo.com" />
                            </div>

                        </div>

                        <!-- Contraseña -->
                        <div class="form-group">
                            <label>Contraseña *</label>
                            <input
                                v-model="formData.password"
                                type="password"
                                required
                                placeholder="••••••••"
                            />
                        </div>

                        <!--Nombre del administrador-->
                        <div class="form-group">
                            <label>Nombre(s) *</label>
                            <input
                                v-model="formData.firstName"
                                type="text"
                                required
                            />
                        </div>

                        <!-- 2 input fields in a single row -->
                        <div class="row">

                            <!--Apellido paterno-->
                            <div class="form-group half">
                                <label>Apellido Paterno *</label>
                                <input
                                    v-model="formData.lastNamePaternal"
                                    type="text"
                                    required
                                />
                            </div>
            
                            <!--Apellido Materno-->
                            <div class="form-group half">
                                <label>Apellido Materno</label>
                                <input
                                    v-model="formData.lastNameMaternal"
                                    type="text"
                                />
                            </div>

                        </div>
                    </div>

                    <!-- SECCIÓN 3 - UBICACIÓN Y DATOS DE CONTACTO -->
                    <div class="form-section">
                        <h3>
                            <span class="step-num">3</span> Ubicación y datos de contacto
                        </h3>
            
                        <!--Calle y número-->
                        <div class="form-group">
                            <label>Calle y Número *</label>
                            <input
                                v-model="formData.street"
                                type="text"
                                required
                                placeholder="Av. Reforma 123"
                            />
                        </div>

                        <!-- 2 input fields in a single row -->
                        <div class="row">

                            <!--Colonia-->
                            <div class="form-group half">
                                <label>Colonia *</label>
                                <input v-model="formData.colony" type="text" required />
                            </div>

                            <!--Código postal-->
                            <div class="form-group half">
                                <label>C.P. *</label>
                                <input
                                    v-model="formData.zipCode"
                                    type="text"
                                    required
                                />
                            </div>
                        </div>
                
                        <!-- 2 input fields in a single row -->
                        <div class="row">

                            <!--Ciudad-->
                            <div class="form-group half">
                                <label>Ciudad *</label>
                                <input
                                    v-model="formData.city"
                                    type="text"
                                    required
                                />
                            </div>

                            <!--Estado-->
                            <div class="form-group half">
                                <label>Estado *</label>
                                <input
                                    v-model="formData.state"
                                    type="text"
                                    required
                                />
                            </div>

                        </div>

                        <!-- 2 input fields in a single row -->
                        <div class="row">
                        
                            <!-- Municipio/Alcaldía-->
                            <div class="form-group half">
                                <label>Municipio/Alcaldía *</label>
                                <input
                                    v-model="formData.municipality"
                                    type="text"
                                    required
                                />
                            </div>

                            <!-- Teléfono -->
                            <div class="form-group half">
                                <label>Teléfono *</label>
                                <input
                                    v-model="formData.phone"
                                    type="tel"
                                    required
                                />
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 4 - DATOS DE PAGO -->
                    <div class="form-section">
                        <h3>
                            <span class="step-num">4</span> Datos de pago
                        </h3>
                
                        <!-- Tipo de licencia (Plan)-->
                        <div class="form-group">
                            <label>Tipo de Licencia (Plan) *</label>
                            <select v-model="formData.planType" required>
                                <option value="" disabled>Select a plan...</option>
                                <option v-for="plan in plans" :key="plan.id" :value="plan.id"> {{ plan.label }} </option>
                            </select>
                        </div>

                        <!-- 2 input fields in a single row-->
                        <div class="row">

                            <!-- Método de pago preferido-->
                            <div class="form-group half">
                                <label>Método de Pago Preferido *</label>
                                <select v-model="formData.paymentMethod" required>
                                    <option value="PUE">PUE - Pago en una sola exhibición</option>
                                    <option value="PPD">PPD - Pago en parcialidades</option>
                                </select>
                            </div>

                            <!-- Forma de pago-->
                            <div class="form-group half">
                                <label>Forma de Pago *</label>
                                <select v-model="formData.paymentForm" required>
                                    <option value="01">01 - Efectivo</option>
                                    <option value="03">03 - Transferencia</option>
                                    <option value="04">04 - Tarjeta de crédito</option>
                                    <option value="99">99 - Por definir</option>
                                </select>
                            </div>

                        </div>

                        <!-- Checkbox "Requiero factura"-->    
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                            <input
                                type="checkbox"
                                v-model="formData.invoiceRequired"
                            />
                            <span>Requiero Factura</span>
                            </label>
                        </div>

                    </div>

                    <!-- SECCIÓN 5 - SELECCIÓN DE LICENCIA (aparece después de registro) -->
                    <div v-if="showLicencias" class="form-section" style="border:2px solid #3b82f6; background:#eff6ff; border-radius:8px; padding:1.5rem;">
                        <h3><span class="step-num" style="background:#10b981">5</span> Selecciona tu licencia</h3>
                        <p class="subtitle" style="margin-bottom:1rem; color:#475569">Tu cuenta ya fue creada. Elige un plan para activar tu licencia y serás redirigido a Mercado Pago.</p>
                        <div v-for="lic in licencias" :key="lic.id" @click="selectedLicencia = lic.id"
                             :style="{border: selectedLicencia===lic.id ? '2px solid #3b82f6' : '1px solid #cbd5e1', background: selectedLicencia===lic.id ? '#dbeafe' : 'white', borderRadius:'8px', padding:'1rem', marginBottom:'0.75rem', cursor:'pointer'}">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <strong>{{ lic.nombre }}</strong> <span style="background:#e0e7ff; padding:2px 6px; border-radius:4px; font-size:0.75rem;">{{ lic.tipo }}</span>
                                    <div style="font-size:0.85rem; color:#475569; margin-top:0.25rem;">{{ lic.descripcion }}</div>
                                    <div style="font-size:0.8rem; color:#64748b;">{{ lic.max_usuarios }} usuarios • {{ lic.max_reportes_mensuales }} reportes/mes</div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-weight:700; color:#1e3a8a;">${{ lic.precio }} MXN</div>
                                    <div v-if="lic.precio_anual" style="font-size:0.75rem; color:#64748b;">Anual ${{ lic.precio_anual }}</div>
                                </div>
                            </div>
                        </div>
                        <button type="button" @click="pagarConMercadoPago" :disabled="isLoading || !selectedLicencia" class="btn-primary" style="margin-top:1rem; background:#009ee3;">
                            {{ isLoading ? 'Creando preferencia...' : 'Pagar con Mercado Pago' }}
                        </button>
                        <button type="button" @click="router.push({ name: 'dashboard' })" style="width:100%; margin-top:0.5rem; background:transparent; border:1px solid #cbd5e1; padding:0.6rem; border-radius:6px; cursor:pointer; color:#475569;">Continuar con Trial gratis</button>
                    </div>

                    <!-- SECCIÓN 5 - BOTÓN "ENVIAR"-->
                    <div v-if="!showLicencias" class="form-actions">
                        <button type="submit" :disabled="isLoading" class="btn-primary">
                            {{ isLoading ? 'Registrando...' : 'Crear cuenta' }}
                        </button>
                        <p class="login-link">
                            ¿Ya tiene una cuenta? <router-link :to="{ name: 'login' }">Inicie sesión aquí</router-link>
                        </p>
                    </div>
                </form>

            </div>
        </div>

        <!-- DIVISIÓN PANEL DERECHO-->
        <div class="right-pane">
            <!-- Imagen fondo panel derecho-->
            <img
                src="/src/assets/login-image3.png"
                alt="Industrial Background"
                class="bg-image"
            />
            <div class="overlay-content">
            </div>
        </div>

    </div>
</template>

<style scoped lang="scss">
@use "sass:color";

// Color variables
$dark-blue: #1e3a8a;
$text-dark: #111827;
$text-gray: #374151;
$border-color: #d1d5db;

// Split screen - Properties
.split-screen-register {
    display: flex;
    height: 100vh;
    width: 100vw;
    font-family: 'Inter', sans-serif;
    overflow: hidden;
}

// Left Pane: Scrolls independently
.left-pane {
    flex: 1;
    background-color: #ffffff;
    overflow-y: auto; // Enables scrolling for the form
    padding: 2rem;
    display: flex;
    justify-content: center;
}

// Form container
.form-container {
    width: 100%;
    max-width: 500px;
    padding-bottom: 3rem;
}

// Brand Header
.brand {
    margin-bottom: 2rem;
    text-align: center;
  
    .logo-placeholder{
        font-weight: 900;
        font-size: 1.5rem;
        color: $dark-blue;
        margin-bottom: 0.5rem;
    }
  
    h2{
        font-size: 2rem;
        color: $text-dark;
        margin: 0 0 0.5rem 0;
    }
  
    .subtitle{
        color: #000000;
        font-size: 1rem;
    }
}

// Section Styling
.form-section {
    margin-bottom: 2rem;
    border-bottom: 1px solid #f3f4f6;
    padding-bottom: 1.5rem;

    h3 {
        font-size: 1.1rem;
        color: $dark-blue;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 8px;

        // Número de sección
        .step-num {
            background: $dark-blue;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    }
}

// Grid System for Inputs
.row {
  display: flex;
  gap: 15px;
  
  .half {
    flex: 1;
  }
}

// Form group
.form-group {
  margin-bottom: 1rem;
  
    label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        color: $text-gray;
        margin-bottom: 0.4rem;
    }
  
    input, select {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid $border-color;
        border-radius: 6px;
        font-size: 0.95rem;
        box-sizing: border-box;
        transition: all 0.2s;
        background-color: #fff;
    
        &:focus {
            border-color: $dark-blue;
            box-shadow: 0 0 0 3px rgba($dark-blue, 0.1);
            outline: none;
        }
    }
}

// Checkbox input
input[type="checkbox"] {
    width: auto !important;
    margin-right: 10px;
    transform: scale(1.2);
    cursor: pointer;
}

//Checkbox group
.checkbox-group {
    margin-top: 1.5 rem;
    padding: 10px 0;

    // Checkbox label
    .checkbox-label {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 0;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 500;
        color: $text-gray
    }
}

// Actions
.form-actions {
    margin-top: 2rem;
  
    // "Create Account" - Properties
    .btn-primary {
        width: 100%;
        padding: 0.9rem;
        background-color: $dark-blue;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: background-color 0.5s;
        
        // Hover
        &:hover {
            background-color: color.adjust($dark-blue, $lightness: -10%);
        }
    
        // Disabled
        &:disabled {
            background-color: color.adjust($dark-blue, $lightness: 40%);
            cursor: not-allowed;
        }
    }
  
    // Login link
    .login-link {
        text-align: center;
        margin-top: 1rem;
        padding-bottom: 3rem;
        font-size: 0.9rem;
        color: #6b7280;
    
        a {
            color: $dark-blue;
            font-weight: 600;
            text-decoration: none;
        }
    }
}

// Right Pane (Fixed)
.right-pane {
    flex: 1;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #0f172a;

    // Image size
    @media (max-width: 900px) {
        display: none;
    }
  
    //Background image
    .bg-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0.7;
    }
}
</style>