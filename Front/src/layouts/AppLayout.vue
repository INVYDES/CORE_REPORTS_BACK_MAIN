<script setup lang="ts">
  import { ref, onMounted } from 'vue';
  import { useAuthStore } from '../stores/authStore';
  import { storeToRefs } from 'pinia';
  import AppSidebar from '../components/shared/AppSidebar.vue';
  import AppHeader from '../components/shared/AppHeader.vue';
  import ReportCreateView from '../views/admin/reports/ReportCreateView.vue';
  import TicketCreateView from '../views/admin/tickets/TicketCreateView.vue';

  const authStore = useAuthStore();
  const { currentUser } = storeToRefs(authStore);

  const isSidebarOpen = ref(false);
  const showReportModal = ref(false);
  const showTicketModal = ref(false);

  onMounted(() => {
    if (!localStorage.getItem('hasVisited')) {
      isSidebarOpen.value = true;
      localStorage.setItem('hasVisited', 'true');
    }
  });

  const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
  };

  const closeSidebar = () => {
    isSidebarOpen.value = false;
  };
</script>

<template>
  <div class="page-container-app-layout">
    <!-- Overlay for mobile when sidebar is open -->
    <div 
      v-if="isSidebarOpen" 
      class="sidebar-overlay" 
      @click="closeSidebar"
    ></div>

    <AppSidebar :is-open="isSidebarOpen" @close="closeSidebar" @openReportModal="showReportModal = true" @openTicketModal="showTicketModal = true" />

    <div class="main-content-wrapper">
      <AppHeader v-if="currentUser?.rol !== 0" @toggle-sidebar="toggleSidebar" />

      <main class="content-body">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </main>

      <Teleport to="body">
        <div v-if="showReportModal" class="report-modal-overlay" @click.self="showReportModal = false">
          <div class="report-modal-content">
            <button class="report-modal-close" @click="showReportModal = false" title="Cerrar">×</button>
            <div class="report-modal-body">
              <ReportCreateView :isModal="true" @close="showReportModal = false" @created="showReportModal = false" />
            </div>
          </div>
        </div>
      </Teleport>

      <Teleport to="body">
        <div v-if="showTicketModal" class="report-modal-overlay" @click.self="showTicketModal = false">
          <div class="report-modal-content" style="max-width: 800px;">
            <button class="report-modal-close" @click="showTicketModal = false" title="Cerrar">×</button>
            <div class="report-modal-body">
              <TicketCreateView :isModal="true" @close="showTicketModal = false" @created="showTicketModal = false" />
            </div>
          </div>
        </div>
      </Teleport>

    </div>
  </div>
</template>

<style scoped lang="scss">
  .page-container-app-layout {
    display: flex;
    min-height: 100dvh;
    height: 100dvh;
    width: 100vw;
    overflow: hidden; 
    background-color: #f1f5f9; 
    position: relative;
  }

  .sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 30;
  }

  @media (max-width: 768px) {
    .sidebar-overlay {
      display: block;
    }
  }

  .main-content-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .content-body {
    flex: 1;
    overflow-y: auto; 
    padding: 2rem;
  }

  @media (max-width: 768px) {
    .content-body {
      padding: 1rem;
    }
    .main-content-wrapper {
      margin-left: 0 !important;
      width: 100%;
      overflow-x: hidden;
    }
  }

  .fade-enter-active,
  .fade-leave-active {
    transition: opacity 0.2s ease;
  }

  .fade-enter-from,
  .fade-leave-to {
    opacity: 0;
  }

  .report-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
  }
  .report-modal-content {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 1100px;
    max-height: 90vh;
    overflow: hidden;
    position: relative;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
  }
  .report-modal-close {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    font-size: 20px;
    cursor: pointer;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    transition: all 0.2s;
    &:hover { background: #e2e8f0; color: #0f172a; }
  }
  .report-modal-body {
    overflow-y: auto;
    flex: 1;
    padding: 1.5rem;
    @media (max-width: 768px) { padding: 1rem; }
  }

  @media print {
    .page-container-app-layout {
      display: block !important;
      height: auto !important;
      overflow: visible !important;
    }
    .sidebar-overlay {
      display: none !important;
    }
    .main-content-wrapper {
      margin-left: 0 !important;
      width: 100% !important;
    }
    .content-body {
      padding: 0 !important;
      overflow: visible !important;
    }
  }
</style>