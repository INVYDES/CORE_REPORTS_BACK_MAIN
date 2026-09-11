import type { Meta, StoryObj } from '@storybook/vue3'
import StatCard from './StatCard.vue'
import { FileText } from 'lucide-vue-next'

const meta: Meta<typeof StatCard> = {
  title: 'UI/StatCard',
  component: StatCard,
  tags: ['autodocs'],
  argTypes: {
    trendColor: { control: 'select', options: ['green', 'red', 'neutral'] },
  },
}

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {
  args: {
    title: 'Reportes Generados',
    value: 128,
    icon: FileText,
    trend: '+12% vs mes anterior',
    trendColor: 'green',
    color: '#eff6ff',
  },
}

export const Warning: Story = {
  args: {
    title: 'Tickets Vencidos',
    value: 4,
    icon: FileText,
    trend: '-2 vs ayer',
    trendColor: 'red',
    color: '#fee2e2',
  },
}
