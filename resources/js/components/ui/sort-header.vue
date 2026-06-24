<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    column: string;
    activeColumn: string;
    direction: 'asc' | 'desc';
    align?: 'left' | 'right';
}>();

defineEmits<{ toggle: [column: string] }>();

const isActive = computed(() => props.activeColumn === props.column);
const icon = computed(() => {
    if (!isActive.value) return ArrowUpDown;
    return props.direction === 'asc' ? ArrowUp : ArrowDown;
});
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center gap-1 hover:text-foreground"
        :class="align === 'right' ? 'justify-end' : ''"
        @click="$emit('toggle', column)"
    >
        <slot />
        <component
            :is="icon"
            class="h-3 w-3"
            :class="isActive ? 'text-primary' : 'text-muted-foreground/50'"
        />
    </button>
</template>
