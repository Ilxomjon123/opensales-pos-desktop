<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Monitor, Moon, Settings, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useAppearance  } from '@/composables/useAppearance';
import type {Appearance} from '@/composables/useAppearance';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const { t } = useI18n();

const handleLogout = () => {
    router.flushAll();
};

const { appearance, updateAppearance } = useAppearance();

const themes = computed<{ value: Appearance; icon: typeof Sun; label: string }[]>(() => [
    { value: 'light', icon: Sun, label: t('theme.light') },
    { value: 'dark', icon: Moon, label: t('theme.dark') },
    { value: 'system', icon: Monitor, label: t('theme.system') },
]);

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-username="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />

    <!-- Mavzu (theme) switcher -->
    <div class="px-2 py-1.5">
        <p class="mb-1.5 text-xs text-muted-foreground">{{ t('theme.label') }}</p>
        <div class="grid grid-cols-3 gap-1 rounded-md border p-0.5">
            <button
                v-for="theme in themes"
                :key="theme.value"
                type="button"
                class="flex items-center justify-center gap-1 rounded px-2 py-1.5 text-xs font-medium transition-colors"
                :class="appearance === theme.value
                    ? 'bg-accent text-accent-foreground'
                    : 'text-muted-foreground hover:bg-muted/60'"
                :aria-pressed="appearance === theme.value"
                @click="updateAppearance(theme.value)"
            >
                <component :is="theme.icon" class="h-3.5 w-3.5" />
                {{ theme.label }}
            </button>
        </div>
    </div>

    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                {{ t('nav.settings') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            {{ t('nav.logout') }}
        </Link>
    </DropdownMenuItem>
</template>
