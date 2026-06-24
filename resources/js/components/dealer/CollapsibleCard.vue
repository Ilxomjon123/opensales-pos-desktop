<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = withDefaults(
    defineProps<{
        title: string;
        defaultOpen?: boolean;
        contentClass?: string;
    }>(),
    {
        defaultOpen: true,
        contentClass: 'p-0',
    },
);

const open = ref(props.defaultOpen);
</script>

<template>
    <Card class="gap-0">
        <CardHeader class="pb-2">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-2 text-left"
                :aria-expanded="open"
                @click="open = !open"
            >
                <CardTitle class="flex min-w-0 items-center gap-2 text-sm">
                    <slot name="icon" />
                    <span class="truncate">{{ title }}</span>
                </CardTitle>
                <div class="flex shrink-0 items-center gap-2">
                    <slot name="actions" />
                    <ChevronDown
                        class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
                        :class="open ? '' : '-rotate-90'"
                    />
                </div>
            </button>
        </CardHeader>
        <CardContent v-show="open" :class="contentClass">
            <slot />
        </CardContent>
    </Card>
</template>
