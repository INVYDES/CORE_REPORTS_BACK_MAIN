<script setup lang = "ts">
    import { type Component } from 'vue'

    defineProps<{
        title: string
        value: string | number
        icon: Component
        trend?: string
        trendColor?: 'green' | 'red' | 'neutral'
        color?: string
    }>()
</script>

<template>
    <div class = "stat-card">
        <div class = "card-content">
            <span class = "card-title">{{ title }}</span>
            <h3 class = "card-value">{{ value }}</h3>

            <span v-if="trend" class="card-trend" :class="trendColor">
                {{ trend }}
            </span>
        </div>

        <div
            class = "card-icon"
            :style="{backgroundColor: color || '#eff6ff', color: '#1d4ed8'}"
        >
            <component :is = "icon" :size = "24"></component>        
        </div>
    </div>
</template>

<style scoped lang="scss">
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
  
        border: 1px solid #f1f5f9;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        transition: transform 0.2s, box-shadow 0.2s;

        &:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
    }

    .card-content {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .card-title {
        font-size: 0.875rem;
        color: #64748b; // Slate-500
        font-weight: 600;
    }

    .card-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0f172a; // Slate-900
        margin: 0;
        line-height: 1.2;
    }

    .card-trend {
        font-size: 0.8rem;
        font-weight: 500;
  
    &.green { color: #10b981; }
    &.red { color: #ef4444; } 
    &.neutral { color: #94a3b8; }
    }

    .card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>