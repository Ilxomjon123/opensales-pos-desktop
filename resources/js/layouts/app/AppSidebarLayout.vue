<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import MobileBottomNav from '@/components/MobileBottomNav.vue';
import MobileTopBar from '@/components/MobileTopBar.vue';
import { Toaster } from '@/components/ui/sonner';
import { useNavItems } from '@/composables/useNavItems';
import { setCurrencySymbol } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { watchEffect } from 'vue';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { navItems, homeUrl } = useNavItems();

// Valyuta belgisining YAGONA manbasi — Inertia `currency` shared prop'i. Har
// navigatsiya/refresh/impersonation'da reaktiv sinxron. Bu global `formatMoneySum`
// belgisining "so'm"ga tushib qolish ildiz sababini yopadi.
const page = usePage();
watchEffect(() => {
    const currency = page.props.currency as { symbol?: string } | undefined;
    setCurrencySymbol(currency?.symbol ?? "so'm");
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <!-- overflow-x-clip (hidden EMAS): gorizontal toshib ketishni kesadi, lekin
             scroll konteyner yaratmaydi -> ichki sahifalardagi `position: sticky`
             (masalan Birja savati) window'ga nisbatan to'g'ri ishlaydi. -->
        <AppContent variant="sidebar" class="overflow-x-clip">
            <ImpersonationBanner />
            <MobileTopBar :breadcrumbs="breadcrumbs" :home-url="homeUrl" />
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="flex flex-1 flex-col pb-[calc(4rem+env(safe-area-inset-bottom))] md:pb-0">
                <slot />
            </div>
        </AppContent>
        <MobileBottomNav :items="navItems" />
        <Toaster />
        <ConfirmDialog />
    </AppShell>
</template>
