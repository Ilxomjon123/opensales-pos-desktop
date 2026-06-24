<script setup lang="ts">
import { Check, X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Spinner } from '@/components/ui/spinner';
import type { UsernameStatus } from '@/composables/useUsernameAvailability';

defineProps<{ status: UsernameStatus }>();

const { t } = useI18n();
</script>

<template>
    <p
        v-if="status !== 'idle'"
        class="mt-1 flex items-center gap-1 text-xs"
        :class="{
            'text-muted-foreground': status === 'checking' || status === 'short',
            'text-emerald-600': status === 'available',
            'text-destructive': status === 'taken' || status === 'invalid',
        }"
    >
        <Spinner v-if="status === 'checking'" class="h-3 w-3" />
        <Check v-else-if="status === 'available'" class="h-3 w-3" />
        <X v-else-if="status === 'taken' || status === 'invalid'" class="h-3 w-3" />
        <span>{{ t(`usernameStatus.${status}`) }}</span>
    </p>
</template>
