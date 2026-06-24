<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Activity } from 'lucide-vue-next';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth?.role === 'super_admin');
</script>

<template>
    <header
        class="hidden h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:flex md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="ml-auto flex items-center gap-1">
            <TooltipProvider v-if="isSuperAdmin" :delay-duration="0">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            as-child
                            class="group h-9 w-9 cursor-pointer"
                        >
                            <a
                                href="/pulse"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <span class="sr-only">Pulse</span>
                                <Activity
                                    class="size-5 opacity-80 group-hover:opacity-100"
                                />
                            </a>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Pulse monitoring</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
            <LocaleSwitcher />
        </div>
    </header>
</template>
