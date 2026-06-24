<script setup lang="ts">
import { computed } from 'vue';
import { matchRange } from '@/lib/translit';

const props = defineProps<{ text: string; query: string }>();

type Part = { text: string; hit: boolean };

const parts = computed<Part[]>(() => {
    const range = props.query ? matchRange(props.text, props.query) : null;

    if (!range) {
        return [{ text: props.text, hit: false }];
    }

    const [start, end] = range;

    return [
        { text: props.text.slice(0, start), hit: false },
        { text: props.text.slice(start, end), hit: true },
        { text: props.text.slice(end), hit: false },
    ].filter((p) => p.text !== '');
});
</script>

<template>
    <span>
        <template v-for="(part, i) in parts" :key="i">
            <mark
                v-if="part.hit"
                class="rounded-sm bg-yellow-200 text-foreground dark:bg-yellow-500/40"
                >{{ part.text }}</mark
            >
            <template v-else>{{ part.text }}</template>
        </template>
    </span>
</template>
