<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { UserX } from 'lucide-vue-next';
import { computed } from 'vue';

type Impersonation = {
    active: boolean;
    impersonator: { id: number; name: string };
    as: { id: number; name: string; role: string };
} | null;

const page = usePage();
const imp = computed<Impersonation>(() => (page.props as any).impersonation ?? null);

function stop() {
    router.post('/impersonate/stop');
}
</script>

<template>
    <div
        v-if="imp?.active"
        class="sticky top-0 z-50 flex w-full items-center justify-between gap-2 border-b-2 border-amber-600 bg-amber-500 px-3 py-2 text-sm text-white shadow-md sm:gap-3 sm:px-4"
    >
        <div class="flex min-w-0 items-center gap-2">
            <UserX class="h-4 w-4 shrink-0" />
            <span class="min-w-0 text-xs leading-tight sm:text-sm">
                Siz <span class="font-bold">{{ imp.as.name }}</span> sifatida kirgansiz
                <span class="hidden sm:inline">(aslida: {{ imp.impersonator.name }})</span>
            </span>
        </div>
        <button
            type="button"
            class="shrink-0 rounded-md bg-white/20 px-2 py-1 text-xs font-medium whitespace-nowrap transition-colors hover:bg-white/30 sm:px-3"
            @click="stop"
        >
            <span class="sm:hidden">← Admin</span>
            <span class="hidden sm:inline">← Admin hisobiga qaytish</span>
        </button>
    </div>
</template>
