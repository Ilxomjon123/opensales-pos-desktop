<script setup lang="ts">
import { Check, X, Clock } from 'lucide-vue-next';
import { computed } from 'vue';
import { formatDateTime } from '@/lib/date';
import type { OrderStatus, OrderStatusHistoryEntry } from '@/types';

const props = defineProps<{
    history: OrderStatusHistoryEntry[];
    currentStatus: OrderStatus;
}>();

const statusColor: Record<OrderStatus, string> = {
    pending: 'bg-amber-500',
    assembling: 'bg-sky-500',
    delivering: 'bg-blue-500',
    delivered: 'bg-emerald-500',
    received: 'bg-teal-500',
    cancelled: 'bg-rose-500',
};

const items = computed(() => props.history ?? []);
</script>

<template>
    <div class="space-y-4">
        <div v-if="items.length === 0" class="text-sm text-muted-foreground">
            Tarix yozuvi yo'q
        </div>

        <ol v-else class="relative space-y-4 border-l border-border pl-6">
            <li v-for="entry in items" :key="entry.id" class="relative">
                <span
                    class="absolute -left-[33px] top-1 flex h-6 w-6 items-center justify-center rounded-full text-white"
                    :class="statusColor[entry.to_status]"
                >
                    <Check v-if="entry.to_status === 'received' || entry.to_status === 'delivered'" class="h-3 w-3" />
                    <X v-else-if="entry.to_status === 'cancelled'" class="h-3 w-3" />
                    <Clock v-else class="h-3 w-3" />
                </span>

                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="font-semibold">{{ entry.to_status_label }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{ entry.changed_at ? formatDateTime(entry.changed_at) : '—' }}
                    </span>
                </div>

                <div v-if="entry.actor" class="mt-1 text-xs text-muted-foreground">
                    {{ entry.actor.name }}
                    <span v-if="entry.actor.role">· {{ entry.actor.role }}</span>
                    <span v-else-if="entry.actor.type === 'shop_member'">· Mijoz a'zosi</span>
                </div>

                <div
                    v-if="entry.reason"
                    class="mt-2 rounded-md bg-muted/50 px-3 py-2 text-xs text-foreground/80"
                >
                    {{ entry.reason }}
                </div>
            </li>
        </ol>
    </div>
</template>
