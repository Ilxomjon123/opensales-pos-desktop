<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Menu, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LandingLocaleSwitcher from '@/components/LandingLocaleSwitcher.vue';
import { useLocalePath } from '@/composables/useLocalePath';
import { login } from '@/routes';

const page = usePage();
const { t } = useI18n();
const { lp } = useLocalePath();

const brandName = computed(() => page.props.project?.name ?? '');
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));

// Landing til prefiksi: uz prefiksiz, qolganlari `/<locale>` bilan.
const LOCALE_PREFIX = /^\/(ru|en|uz-Cyrl)(?=\/|$)/;

const isHome = computed(() => {
    const path = (page.url ?? '/').split('?')[0].split('#')[0].replace(LOCALE_PREFIX, '') || '/';

    return path === '/';
});

const mobileMenuOpen = ref(false);

function toggleMobileMenu(): void {
    mobileMenuOpen.value = !mobileMenuOpen.value;
}

function closeMobileMenu(): void {
    mobileMenuOpen.value = false;
}

function scrollToSection(id: string): void {
    const target = document.getElementById(id);

    if (!target) {
        return;
    }

    const headerOffset = 64;
    const top = target.getBoundingClientRect().top + window.scrollY - headerOffset;
    window.scrollTo({ top, behavior: 'smooth' });

    if (history.replaceState) {
        history.replaceState(null, '', `#${id}`);
    }
}

function handleAnchor(id: string, mobile = false): void {
    if (mobile) {
        closeMobileMenu();
    }

    if (isHome.value) {
        if (mobile) {
            setTimeout(() => scrollToSection(id), 80);
        } else {
            scrollToSection(id);
        }

        return;
    }

    router.visit(`${lp('/')}#${id}`);
}

const navItems = computed(() => [
    { id: 'features', label: t('pageWelcome.navFeatures') },
    { id: 'audiences', label: t('pageWelcome.navAudiences') },
    { id: 'reports', label: t('pageWelcome.navReports') },
    { id: 'pricing', label: t('pageWelcome.navPricing') },
    { id: 'compare', label: t('pageWelcome.navCompare') },
    { id: 'faq', label: t('pageWelcome.navFaq') },
    { id: 'contact', label: t('pageWelcome.navContact') },
]);
</script>

<template>
    <header
        class="fixed inset-x-0 top-0 z-40 border-b border-border/60 bg-background/85 backdrop-blur-md"
    >
        <div
            class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-4 md:px-6"
        >
            <Link :href="lp('/')" class="flex shrink-0 items-center gap-2">
                <div
                    class="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                >
                    <AppLogoIcon class="size-5" />
                </div>
                <span class="text-lg font-semibold">{{ brandName }}</span>
            </Link>

            <nav class="hidden min-w-0 items-center gap-5 lg:flex">
                <a
                    v-for="item in navItems"
                    :key="item.id"
                    :href="`${lp('/')}#${item.id}`"
                    class="whitespace-nowrap text-sm text-muted-foreground transition-colors hover:text-foreground"
                    @click.prevent="handleAnchor(item.id)"
                >
                    {{ item.label }}
                </a>
                <Link
                    :href="lp('/blog')"
                    class="whitespace-nowrap text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    Blog
                </Link>
            </nav>

            <div class="flex shrink-0 items-center gap-2">
                <LandingLocaleSwitcher />
                <Link
                    v-if="isAuthenticated"
                    :href="'/dashboard'"
                    class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    {{ t('pageWelcome.navPanel') }}
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="hidden h-9 items-center justify-center rounded-md px-2 text-xs font-medium text-foreground hover:bg-accent sm:inline-flex sm:px-3 sm:text-sm"
                    >
                        {{ t('pageWelcome.navLogin') }}
                    </Link>
                    <Link
                        href="/register"
                        class="hidden h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 sm:inline-flex"
                    >
                        {{ t('pageWelcome.navRegister') }}
                    </Link>
                </template>

                <button
                    type="button"
                    class="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground lg:hidden"
                    aria-label="Menyu"
                    :aria-expanded="mobileMenuOpen"
                    @click="toggleMobileMenu"
                >
                    <X v-if="mobileMenuOpen" class="size-5" />
                    <Menu v-else class="size-5" />
                </button>
            </div>
        </div>

        <div
            v-if="mobileMenuOpen"
            class="border-t border-border/60 bg-background lg:hidden"
        >
            <nav class="mx-auto flex w-full max-w-6xl flex-col gap-1 px-4 py-4">
                <a
                    v-for="item in navItems"
                    :key="item.id"
                    :href="`${lp('/')}#${item.id}`"
                    class="rounded-md px-3 py-2.5 text-sm text-foreground hover:bg-accent"
                    @click.prevent="handleAnchor(item.id, true)"
                >
                    {{ item.label }}
                </a>
                <Link
                    :href="lp('/blog')"
                    class="rounded-md px-3 py-2.5 text-sm text-foreground hover:bg-accent"
                    @click="closeMobileMenu"
                >
                    Blog
                </Link>

                <div class="mt-2 border-t border-border/60 pt-3">
                    <LandingLocaleSwitcher />
                </div>

                <div v-if="!isAuthenticated" class="mt-2 flex gap-2 border-t border-border/60 pt-3">
                    <Link
                        :href="login()"
                        class="inline-flex h-10 flex-1 items-center justify-center rounded-md border px-4 text-sm font-medium text-foreground hover:bg-accent"
                        @click="closeMobileMenu"
                    >
                        {{ t('pageWelcome.navLogin') }}
                    </Link>
                    <Link
                        href="/register"
                        class="inline-flex h-10 flex-1 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        @click="closeMobileMenu"
                    >
                        {{ t('pageWelcome.navRegister') }}
                    </Link>
                </div>
            </nav>
        </div>
    </header>
</template>
