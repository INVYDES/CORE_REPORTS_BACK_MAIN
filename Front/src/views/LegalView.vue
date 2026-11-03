<script setup lang="ts">
  import { computed, watch } from 'vue'
  import { useRoute } from 'vue-router'
  import { CalendarDays, Mail, ShieldCheck } from 'lucide-vue-next'
  import PublicHeader from '../components/shared/PublicHeader.vue'
  import AppFooter from '../components/shared/AppFooter.vue'
  import { useSeo } from '../utils/seo'

  interface Seccion {
    id: string
    titulo: string
    parrafos: string[]
    bullets?: string[]
  }

  interface Documento {
    titulo: string
    resumen: string
    actualizado: string
    secciones: Seccion[]
  }

  const CONTACTO = 'contacto@corion.mx'
  const RESPONSABLE = 'CoreReports'

  const documentos: Record<string, Documento> = {
    privacy: {
      titulo: 'Aviso de privacidad',
      resumen:
        'Cómo recabamos, usamos y protegemos tus datos personales conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP).',
      actualizado: '17 de octubre de 2026',
      secciones: [
        {
          id: 'responsable',
          titulo: '1. Responsable de los datos',
          parrafos: [
            `${RESPONSABLE} es responsable del tratamiento de los datos personales que recaba a través de esta plataforma y de sus sitios relacionados.`,
            `Para cualquier asunto relacionado con privacidad puedes escribir a ${CONTACTO}.`,
          ],
        },
        {
          id: 'datos',
          titulo: '2. Datos personales que recabamos',
          parrafos: ['Recabamos únicamente los datos necesarios para operar el servicio:'],
          bullets: [
            'Datos de identificación y contacto: nombre, correo electrónico, teléfono y empresa.',
            'Datos de la cuenta: rol, credenciales de acceso y bitácora de movimientos.',
            'Datos operativos: tickets, servicios, reportes, evidencias fotográficas y firmas de conformidad que capture tu organización.',
            'Datos de facturación: RFC, régimen fiscal y uso de CFDI cuando contratas un plan de pago.',
            'Datos técnicos: dirección IP, navegador y registros de acceso necesarios para seguridad y diagnóstico.',
          ],
        },
        {
          id: 'finalidades',
          titulo: '3. Finalidades del tratamiento',
          parrafos: ['Tus datos se utilizan para las siguientes finalidades primarias:'],
          bullets: [
            'Crear y administrar tu cuenta y la de los usuarios de tu empresa.',
            'Prestar el servicio contratado y dar seguimiento a tickets, servicios y reportes.',
            'Emitir comprobantes fiscales y gestionar cobros.',
            'Enviar notificaciones operativas relacionadas con el servicio.',
            'Atender solicitudes de soporte y requerimientos legales.',
          ],
        },
        {
          id: 'transferencias',
          titulo: '4. Transferencias y encargados',
          parrafos: [
            'Los datos se alojan en servidores de proveedores de infraestructura que actúan como encargados del tratamiento y están obligados contractualmente a mantener la confidencialidad.',
            'No vendemos datos personales. Cualquier transferencia a terceros se realizará con tu consentimiento o cuando exista una obligación legal.',
          ],
        },
        {
          id: 'conservacion',
          titulo: '5. Conservación de la información',
          parrafos: [
            'Conservamos los datos mientras exista una relación contractual y durante los plazos que exijan las disposiciones fiscales y legales aplicables. Al concluir, se eliminan o anonimizan de forma segura.',
          ],
        },
        {
          id: 'arco',
          titulo: '6. Derechos ARCO',
          parrafos: [
            `Puedes ejercer tus derechos de acceso, rectificación, cancelación y oposición enviando una solicitud a ${CONTACTO}. Incluye tu nombre, el derecho que deseas ejercer y la documentación que acredite tu identidad.`,
            'Atenderemos la solicitud en los plazos previstos por la LFPDPPP. Si consideras que no fue atendida correctamente, puedes acudir al Instituto Nacional de Transparencia, Acceso a la Información y Protección de Datos Personales (INAI).',
          ],
        },
        {
          id: 'cookies',
          titulo: '7. Cookies y tecnologías similares',
          parrafos: [
            'Usamos almacenamiento del navegador estrictamente necesario para mantener la sesión activa y preferencias de la aplicación. No utilizamos cookies publicitarias ni de seguimiento de terceros.',
          ],
        },
        {
          id: 'cambios',
          titulo: '8. Cambios a este aviso',
          parrafos: [
            'Cualquier modificación a este aviso se publicará en esta misma página con su fecha de actualización. El uso continuado del servicio implica la aceptación de la versión vigente.',
          ],
        },
      ],
    },
    terms: {
      titulo: 'Términos y condiciones',
      resumen:
        'Condiciones bajo las cuales se presta el servicio de coreReports, incluyendo planes, uso aceptable y responsabilidades de las partes.',
      actualizado: '17 de octubre de 2026',
      secciones: [
        {
          id: 'objeto',
          titulo: '1. Objeto y aceptación',
          parrafos: [
            `Estos términos regulan el acceso y uso de la plataforma ${RESPONSABLE}. Al crear una cuenta o utilizar el servicio, aceptas estas condiciones en su totalidad.`,
            'Si actúas en representación de una empresa, declaras contar con facultades suficientes para obligarla.',
          ],
        },
        {
          id: 'cuenta',
          titulo: '2. Registro y seguridad de la cuenta',
          parrafos: [
            'Cada usuario es responsable de la veracidad de los datos proporcionados y de mantener la confidencialidad de sus credenciales.',
            'La empresa administradora decide qué usuarios invita y qué roles les asigna. Los accesos son personales e intransferibles.',
          ],
        },
        {
          id: 'planes',
          titulo: '3. Planes, pagos y facturación',
          parrafos: [
            'La prueba gratuita se otorga por el periodo indicado al momento del registro y no genera obligación de pago.',
            'Los planes de pago se renuevan por el periodo contratado. Los precios se expresan en pesos mexicanos e incluyen IVA salvo indicación en contrario.',
            'La falta de pago puede derivar en la suspensión del servicio previo aviso.',
          ],
        },
        {
          id: 'uso',
          titulo: '4. Uso aceptable',
          parrafos: ['Queda prohibido:'],
          bullets: [
            'Utilizar la plataforma para fines ilícitos o contrarios al orden público.',
            'Intentar acceder a información de otras empresas o a cuentas que no te pertenecen.',
            'Interferir con la seguridad, disponibilidad o integridad del servicio.',
            'Extraer de forma masiva datos de la plataforma sin autorización.',
          ],
        },
        {
          id: 'propiedad',
          titulo: '5. Propiedad intelectual',
          parrafos: [
            'El software, la marca, el diseño y la documentación de coreReports son propiedad de sus titulares y están protegidos por la legislación aplicable.',
            'Los datos que tu empresa captura son y seguirán siendo tuyos. Solo los usamos para prestar el servicio.',
          ],
        },
        {
          id: 'disponibilidad',
          titulo: '6. Disponibilidad y soporte',
          parrafos: [
            'Procuramos mantener el servicio disponible de forma continua, pero pueden existir mantenimientos programados o interrupciones por causas ajenas a nuestro control.',
            'El soporte se presta por los canales publicados en la plataforma durante días hábiles.',
          ],
        },
        {
          id: 'responsabilidad',
          titulo: '7. Limitación de responsabilidad',
          parrafos: [
            'La plataforma es una herramienta de gestión. Las decisiones operativas, técnicas y de seguridad industrial son responsabilidad de cada empresa usuaria.',
            'En la medida permitida por la ley, no seremos responsables por daños indirectos, lucro cesante o pérdida de datos derivada de un uso indebido del servicio.',
          ],
        },
        {
          id: 'terminacion',
          titulo: '8. Terminación',
          parrafos: [
            'Puedes cancelar tu cuenta en cualquier momento. Podremos suspender o cancelar el acceso en caso de incumplimiento de estos términos, previa notificación cuando sea posible.',
          ],
        },
        {
          id: 'ley',
          titulo: '9. Legislación aplicable',
          parrafos: [
            'Estos términos se rigen por las leyes de los Estados Unidos Mexicanos. Cualquier controversia se someterá a los tribunales competentes de la Ciudad de México.',
          ],
        },
      ],
    },
    'legal-notice': {
      titulo: 'Aviso legal',
      resumen:
        'Información general sobre el titular del sitio, condiciones de uso y responsabilidad sobre los contenidos publicados.',
      actualizado: '17 de octubre de 2026',
      secciones: [
        {
          id: 'titular',
          titulo: '1. Identidad del titular',
          parrafos: [
            `Este sitio es operado por ${RESPONSABLE}, con domicilio en México y correo de contacto ${CONTACTO}.`,
            'Los datos fiscales completos se proporcionan a los clientes al formalizar la contratación y en los comprobantes correspondientes.',
          ],
        },
        {
          id: 'objeto-sitio',
          titulo: '2. Objeto del sitio',
          parrafos: [
            'El sitio tiene fines informativos y de acceso al servicio. La información sobre funcionalidades, precios y alcance puede actualizarse sin aviso previo.',
          ],
        },
        {
          id: 'condiciones',
          titulo: '3. Condiciones de uso',
          parrafos: [
            'El acceso al sitio es gratuito y no requiere registro, salvo para las áreas privadas de la aplicación. El usuario se compromete a hacer un uso diligente de los contenidos.',
          ],
        },
        {
          id: 'propiedad-sitio',
          titulo: '4. Propiedad intelectual e industrial',
          parrafos: [
            'Las marcas, logotipos, textos, ilustraciones y código de este sitio están protegidos por derechos de propiedad intelectual. Queda prohibida su reproducción total o parcial sin autorización escrita.',
          ],
        },
        {
          id: 'enlaces',
          titulo: '5. Enlaces a terceros',
          parrafos: [
            'El sitio puede incluir enlaces a servicios externos. No controlamos ni respondemos por sus contenidos, políticas o disponibilidad.',
          ],
        },
        {
          id: 'proteccion',
          titulo: '6. Protección de datos',
          parrafos: [
            'El tratamiento de datos personales se describe en el Aviso de privacidad, disponible en este mismo sitio.',
          ],
        },
        {
          id: 'responsabilidad-sitio',
          titulo: '7. Responsabilidad',
          parrafos: [
            'Se realizan esfuerzos razonables para mantener la información actualizada y libre de errores. No obstante, no se garantiza la exactitud absoluta de los contenidos ni la ausencia de interrupciones técnicas.',
          ],
        },
        {
          id: 'contacto',
          titulo: '8. Contacto',
          parrafos: [`Para cualquier aclaración sobre este aviso, escríbenos a ${CONTACTO}.`],
        },
      ],
    },
  }

  const route = useRoute()
  const DOC_POR_DEFECTO = documentos['privacy'] as Documento
  const doc = computed<Documento>(() => documentos[String(route.meta.legal)] ?? DOC_POR_DEFECTO)

  useSeo(() => ({
    title: `${doc.value.titulo} · coreReports`,
    description: doc.value.resumen,
    path: route.path,
  }))

  watch(
    () => route.fullPath,
    () => window.scrollTo({ top: 0 })
  )
</script>

<template>
  <div class="legal-page">
    <PublicHeader />

    <main class="legal-main">
      <div class="hero-strip">
        <div class="container">
          <span class="eyebrow"><ShieldCheck :size="14" /> Documentos legales</span>
          <h1>{{ doc.titulo }}</h1>
          <p class="resumen">{{ doc.resumen }}</p>
          <span class="updated"
            ><CalendarDays :size="14" /> Última actualización: {{ doc.actualizado }}</span
          >
        </div>
      </div>

      <div class="container layout">
        <aside class="toc" aria-label="Contenido">
          <span class="toc-title">Contenido</span>
          <a v-for="s in doc.secciones" :key="s.id" :href="`#${s.id}`">{{ s.titulo }}</a>
        </aside>

        <article class="doc">
          <section v-for="s in doc.secciones" :id="s.id" :key="s.id" class="doc-section">
            <h2>{{ s.titulo }}</h2>
            <p v-for="p in s.parrafos" :key="p">{{ p }}</p>
            <ul v-if="s.bullets">
              <li v-for="b in s.bullets" :key="b">{{ b }}</li>
            </ul>
          </section>

          <div class="contact-box">
            <Mail :size="20" />
            <div>
              <strong>¿Dudas sobre este documento?</strong>
              <p>
                Escríbenos a <a :href="`mailto:${CONTACTO}`">{{ CONTACTO }}</a> y te respondemos en
                días hábiles.
              </p>
            </div>
          </div>
        </article>
      </div>
    </main>

    <AppFooter />
  </div>
</template>

<style scoped>
  .legal-page {
    min-height: 100vh;
    background: #fff;
    color: #0f172a;
    font-family: 'Inter', sans-serif;
    display: flex;
    flex-direction: column;
  }
  .legal-main {
    flex: 1;
  }
  .container {
    width: 100%;
    max-width: 1140px;
    margin: 0 auto;
    padding: 0 1.5rem;
  }
  .hero-strip {
    background:
      radial-gradient(700px 260px at 8% -10%, #e0e7ff 0%, rgba(224, 231, 255, 0) 70%), #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 3rem 0 2.5rem;
  }
  .eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: #1e3a8a;
    font-weight: 700;
    font-size: 0.75rem;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    margin-bottom: 0.8rem;
  }
  h1 {
    margin: 0;
    font-size: clamp(1.9rem, 3.4vw, 2.6rem);
    letter-spacing: -0.03em;
  }
  .resumen {
    margin: 0.8rem 0 0;
    max-width: 46rem;
    color: #475569;
    line-height: 1.65;
  }
  .updated {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 1rem;
    font-size: 0.8rem;
    color: #64748b;
  }
  .layout {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 3rem;
    padding: 3rem 1.5rem 4rem;
    align-items: start;
  }
  .toc {
    position: sticky;
    top: 88px;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    border-left: 2px solid #e2e8f0;
    padding-left: 1rem;
  }
  .toc-title {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #94a3b8;
    margin-bottom: 0.3rem;
  }
  .toc a {
    color: #475569;
    text-decoration: none;
    font-size: 0.85rem;
    line-height: 1.4;
    transition:
      color 0.2s,
      border-color 0.2s;
  }
  .toc a:hover {
    color: #1e3a8a;
  }
  .doc {
    max-width: 760px;
  }
  .doc-section {
    scroll-margin-top: 90px;
    margin-bottom: 2.25rem;
  }
  .doc-section h2 {
    font-size: 1.15rem;
    margin: 0 0 0.7rem;
    color: #1e293b;
  }
  .doc-section p {
    margin: 0 0 0.8rem;
    color: #475569;
    line-height: 1.75;
    font-size: 0.94rem;
  }
  .doc-section ul {
    margin: 0.25rem 0 1rem;
    padding-left: 1.15rem;
    color: #475569;
    line-height: 1.7;
    font-size: 0.94rem;
  }
  .doc-section li {
    margin-bottom: 0.3rem;
  }
  .contact-box {
    display: flex;
    gap: 0.9rem;
    align-items: flex-start;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem;
    color: #1e3a8a;
  }
  .contact-box strong {
    color: #0f172a;
    font-size: 0.95rem;
  }
  .contact-box p {
    margin: 0.35rem 0 0;
    color: #475569;
    font-size: 0.9rem;
  }
  .contact-box a {
    color: #1e3a8a;
    font-weight: 600;
  }

  /* ---------- Modo oscuro ---------- */
  html.theme-dark .legal-page {
    background: #0b1220;
    color: #e2e8f0;
  }
  html.theme-dark .hero-strip {
    background:
      radial-gradient(700px 260px at 8% -10%, #172554 0%, rgba(23, 37, 84, 0) 70%), #0f172a;
    border-bottom-color: #1e293b;
  }
  html.theme-dark .eyebrow {
    color: #93c5fd;
  }
  html.theme-dark h1,
  html.theme-dark .doc-section h2 {
    color: #f1f5f9;
  }
  html.theme-dark .resumen,
  html.theme-dark .doc-section p,
  html.theme-dark .doc-section ul {
    color: #94a3b8;
  }
  html.theme-dark .updated {
    color: #94a3b8;
  }
  html.theme-dark .toc {
    border-color: #1e293b;
  }
  html.theme-dark .toc a {
    color: #cbd5e1;
  }
  html.theme-dark .toc a:hover {
    color: #93c5fd;
  }
  html.theme-dark .contact-box {
    background: #0f172a;
    border-color: #1e293b;
  }
  html.theme-dark .contact-box strong {
    color: #f1f5f9;
  }
  html.theme-dark .contact-box p {
    color: #94a3b8;
  }
  html.theme-dark .contact-box a {
    color: #93c5fd;
  }

  @media (max-width: 900px) {
    .layout {
      grid-template-columns: 1fr;
      gap: 1.5rem;
    }
    .toc {
      position: static;
      border-left: none;
      border-bottom: 1px solid #e2e8f0;
      padding: 0 0 1rem;
      flex-direction: row;
      flex-wrap: wrap;
      gap: 0.5rem 1rem;
    }
    .toc-title {
      width: 100%;
    }
  }
</style>
