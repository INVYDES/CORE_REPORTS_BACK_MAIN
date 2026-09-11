<script setup lang="ts">
import { computed } from 'vue'
// @ts-ignore
import { VueDatePicker } from '@vuepic/vue-datepicker'

const props = defineProps<{
    modelValue: any;
    placeholder?: string;
}>()

const emit = defineEmits(['update:modelValue'])

const internalValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})
</script>

<template>
    <div class="date-filter-wrapper">
        <VueDatePicker 
            v-model="internalValue" 
            :enable-time-picker="false"
            auto-apply
            :placeholder="placeholder || 'Seleccionar fecha'"
            format="yyyy-MM-dd"
            model-type="yyyy-MM-dd"
            :clearable="true"
        />
    </div>
</template>

<style scoped>
.date-filter-wrapper {
    width: 100%;
}
:deep(.dp__input) {
    box-sizing: border-box;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    font-size: 0.9rem;
    color: #334155;
    outline: none;
    cursor: pointer;
    font-family: inherit;
    box-shadow: none;
    height: auto;
}
:deep(.dp__input:hover) {
    border-color: #cbd5e1;
}
:deep(.dp__input:focus) {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
</style>