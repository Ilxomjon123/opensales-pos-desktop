<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';
import { useRoleLabel } from '@/composables/useRoleLabel';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        homeUrl: string;
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);
const { getInitials } = useInitials();
const { brandTitle } = useRoleLabel();

const title = computed(() => {
    const last = props.breadcrumbs[props.breadcrumbs.length - 1];

    return last?.title ?? brandTitle.value;
});

const showAvatar = computed(() => Boolean(user.value.avatar && user.value.avatar !== ''));
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-12 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 bg-background/95 px-3 backdrop-blur supports-[backdrop-filter]:bg-background/85 md:hidden"
    >
        <Link :href="homeUrl" class="flex min-w-0 items-center gap-2">
            <div class="flex aspect-square size-7 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon class="size-4 fill-current text-white dark:text-black" />
            </div>
            <span class="truncate text-sm font-semibold">{{ title }}</span>
        </Link>
        <div class="flex items-center gap-1">
            <LocaleSwitcher />
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md transition-colors active:bg-accent"
                        aria-label="User menu"
                    >
                        <Avatar class="h-8 w-8 rounded-lg">
                            <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
                            <AvatarFallback class="rounded-lg text-xs text-black dark:text-white">
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="min-w-56 rounded-lg" align="end" :side-offset="6">
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
