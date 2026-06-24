<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Bell, Bot, Check, FolderTree, Package, Rocket, Store, Truck, X } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';

type OnboardingSteps = {
    bot_connected: boolean;
    notifications_connected: boolean;
    has_category: boolean;
    has_product: boolean;
    has_shop: boolean;
    has_deliveryman: boolean;
};

const props = defineProps<{
    onboarding: {
        steps: OnboardingSteps;
        bot_username: string | null;
        connect_url: string | null;
    };
}>();

const { t } = useI18n();

type Step = {
    key: keyof OnboardingSteps;
    icon: typeof Bell;
    href?: string;
    external?: string | null;
    /** Bot ulanmaguncha bajarib bo'lmaydigan qadam — bloklangan ko'rinadi. */
    needsBot?: boolean;
};

const STEPS = computed<Step[]>(() => [
    { key: 'bot_connected', icon: Bot, href: '/dealer/bot' },
    { key: 'notifications_connected', icon: Bell, external: props.onboarding.connect_url, needsBot: true },
    { key: 'has_category', icon: FolderTree, href: '/dealer/categories' },
    { key: 'has_product', icon: Package, href: '/dealer/products' },
    { key: 'has_shop', icon: Store, href: '/dealer/shops' },
    { key: 'has_deliveryman', icon: Truck, href: '/dealer/deliverymen' },
]);

const botConnected = computed(() => props.onboarding.steps.bot_connected);
const doneCount = computed(() => Object.values(props.onboarding.steps).filter(Boolean).length);
const total = computed(() => STEPS.value.length);
const allDone = computed(() => doneCount.value === total.value);
const progress = computed(() => Math.round((doneCount.value / total.value) * 100));

function dismiss() {
    router.post('/dealer/onboarding/complete', {}, { preserveScroll: true });
}
</script>

<template>
    <Card class="border-primary/30 bg-gradient-to-br from-primary/5 to-transparent">
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/15">
                        <Rocket class="h-4.5 w-4.5 text-primary" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold tracking-tight">{{ t('onboarding.title') }}</h2>
                        <p class="text-xs text-muted-foreground">{{ t('onboarding.subtitle') }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-md p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    :title="t('onboarding.skip')"
                    @click="dismiss"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Progress -->
            <div class="mt-3">
                <div class="mb-1 flex items-center justify-between text-xs">
                    <span class="font-medium text-muted-foreground">{{ doneCount }} / {{ total }}</span>
                    <span class="font-semibold text-primary">{{ progress }}%</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full bg-primary transition-all duration-500" :style="{ width: progress + '%' }" />
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-2">
            <div
                v-for="step in STEPS"
                :key="step.key"
                class="flex items-center gap-3 rounded-lg border p-3"
                :class="onboarding.steps[step.key] ? 'border-green-500/30 bg-green-500/5' : 'border-border bg-card'"
            >
                <div
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
                    :class="onboarding.steps[step.key] ? 'bg-green-500 text-white' : 'bg-muted text-muted-foreground'"
                >
                    <Check v-if="onboarding.steps[step.key]" class="h-4 w-4" />
                    <component :is="step.icon" v-else class="h-4 w-4" />
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium" :class="onboarding.steps[step.key] && 'text-muted-foreground line-through'">
                        {{ t(`onboarding.steps.${step.key}.title`) }}
                    </p>
                    <p class="text-xs text-muted-foreground">{{ t(`onboarding.steps.${step.key}.description`) }}</p>
                </div>

                <template v-if="!onboarding.steps[step.key]">
                    <span
                        v-if="step.needsBot && !botConnected"
                        class="shrink-0 text-xs text-muted-foreground"
                    >
                        {{ t('onboarding.needsBot') }}
                    </span>
                    <Button v-else-if="step.external" as-child size="sm" variant="outline" class="shrink-0">
                        <a :href="step.external" target="_blank" rel="noopener">{{ t('onboarding.connect') }}</a>
                    </Button>
                    <Button v-else-if="step.href" as-child size="sm" variant="outline" class="shrink-0">
                        <Link :href="step.href">{{ t('onboarding.go') }}</Link>
                    </Button>
                </template>
            </div>

            <div v-if="allDone" class="pt-2">
                <Button class="w-full" @click="dismiss">
                    <Rocket class="mr-1.5 h-4 w-4" />
                    {{ t('onboarding.finish') }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
