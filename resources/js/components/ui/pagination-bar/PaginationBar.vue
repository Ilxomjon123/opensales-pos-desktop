<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-vue-next';
import { computed } from 'vue';

type PageMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

const props = defineProps<{
    meta: PageMeta;
}>();

const emit = defineEmits<{
    'change': [page: number];
    'prefetch': [page: number];
}>();

const pageItems = computed<(number | '...')[]>(() => {
    const cur = props.meta.current_page;
    const last = props.meta.last_page;

    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const items: (number | '...')[] = [1];
    const startNear = Math.max(2, cur - 1);
    const endNear = Math.min(last - 1, cur + 1);

    if (startNear > 2) items.push('...');
    for (let i = startNear; i <= endNear; i++) items.push(i);
    if (endNear < last - 1) items.push('...');
    items.push(last);

    return items;
});

function go(page: number) {
    if (page < 1 || page > props.meta.last_page || page === props.meta.current_page) return;
    emit('change', page);
}

function prefetch(page: number) {
    if (page < 1 || page > props.meta.last_page || page === props.meta.current_page) return;
    emit('prefetch', page);
}
</script>

<template>
    <div v-if="meta.total > 0" class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <p class="text-xs text-muted-foreground">
            <span class="font-medium text-foreground">{{ meta.from ?? 0 }}</span>
            –
            <span class="font-medium text-foreground">{{ meta.to ?? 0 }}</span>
            /
            <span class="font-medium text-foreground">{{ meta.total }}</span>
            ta
            <span class="ml-2 text-muted-foreground/70">
                · {{ meta.current_page }} / {{ meta.last_page }} sahifa
            </span>
        </p>

        <div v-if="meta.last_page > 1" class="flex items-center gap-1">
            <Button
                variant="outline"
                size="icon"
                class="h-8 w-8"
                :disabled="meta.current_page === 1"
                @click="go(1)"
                @mouseenter="prefetch(1)"
                title="Birinchi"
            >
                <ChevronsLeft class="h-4 w-4" />
            </Button>
            <Button
                variant="outline"
                size="icon"
                class="h-8 w-8"
                :disabled="meta.current_page === 1"
                @click="go(meta.current_page - 1)"
                @mouseenter="prefetch(meta.current_page - 1)"
                title="Oldingi"
            >
                <ChevronLeft class="h-4 w-4" />
            </Button>

            <template v-for="(item, idx) in pageItems" :key="idx">
                <span v-if="item === '...'" class="px-2 text-xs text-muted-foreground">…</span>
                <Button
                    v-else
                    :variant="item === meta.current_page ? 'default' : 'outline'"
                    size="sm"
                    class="h-8 min-w-8 px-2"
                    @click="go(item)"
                    @mouseenter="prefetch(item)"
                >
                    {{ item }}
                </Button>
            </template>

            <Button
                variant="outline"
                size="icon"
                class="h-8 w-8"
                :disabled="meta.current_page === meta.last_page"
                @click="go(meta.current_page + 1)"
                @mouseenter="prefetch(meta.current_page + 1)"
                title="Keyingi"
            >
                <ChevronRight class="h-4 w-4" />
            </Button>
            <Button
                variant="outline"
                size="icon"
                class="h-8 w-8"
                :disabled="meta.current_page === meta.last_page"
                @click="go(meta.last_page)"
                @mouseenter="prefetch(meta.last_page)"
                title="Oxirgi"
            >
                <ChevronsRight class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>
