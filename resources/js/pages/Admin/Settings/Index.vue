<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { QrCode, Send, SlidersHorizontal, Smartphone, type LucideIcon } from 'lucide-vue-next';
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout.vue';

type Flag = { key: string };
type Country = { code: string; name: string; native_name: string | null; flag: string | null };

const props = defineProps<{
    flags: Flag[];
    countries: Country[];
    matrix: Record<string, Record<string, boolean>>;
}>();

const { t } = useI18n();

// Optimistik holat — toggle darrov ko'rinadi, server xato bersa qaytariladi.
const state = reactive<Record<string, Record<string, boolean>>>(
    JSON.parse(JSON.stringify(props.matrix)),
);

// Bir vaqtda bitta toggle uchun saqlovchi (disable holati).
const saving = reactive<Record<string, boolean>>({});

const flagIcons: Record<string, LucideIcon> = {
    'phone-login': Smartphone,
    'telegram-login': Send,
    'qr-login': QrCode,
};

function cellKey(country: string, flag: string): string {
    return `${country}:${flag}`;
}

function flagLabel(key: string): string {
    return t(`pageAdmin.settings.flags.${key}.label`);
}

function flagDescription(key: string): string {
    return t(`pageAdmin.settings.flags.${key}.description`);
}

// Har bayroq uchun nechta davlatda yoqilgan (CardHeader badge).
function enabledCount(flag: string): number {
    return props.countries.filter((c) => state[c.code]?.[flag]).length;
}

function toggle(country: string, flag: string, enabled: boolean): void {
    const key = cellKey(country, flag);
    const previous = state[country][flag];
    state[country][flag] = enabled;
    saving[key] = true;

    router.patch(
        '/admin/settings/flags',
        { country, flag, enabled },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                state[country][flag] = previous;
            },
            onFinish: () => {
                saving[key] = false;
            },
        },
    );
}

const total = computed(() => props.countries.length);
</script>

<template>
    <Head :title="t('pageAdmin.settings.headTitle')" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4 md:p-8">
        <!-- Sarlavha -->
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                <SlidersHorizontal class="h-5 w-5 text-primary" />
            </div>
            <div class="min-w-0">
                <h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">
                    {{ t('pageAdmin.settings.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('pageAdmin.settings.description') }}
                </p>
            </div>
        </div>

        <!-- Davlat yo'q holati -->
        <Card v-if="total === 0" class="border-dashed">
            <CardContent class="flex flex-col items-center gap-2 py-12 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                    <SlidersHorizontal class="h-6 w-6 text-muted-foreground" />
                </div>
                <p class="text-sm text-muted-foreground">{{ t('pageAdmin.settings.noCountries') }}</p>
            </CardContent>
        </Card>

        <!-- Har bayroq — alohida karta -->
        <Card
            v-for="flag in flags"
            :key="flag.key"
            class="overflow-hidden border-border/70 transition-shadow hover:shadow-sm"
        >
            <CardHeader class="gap-0 border-b border-border/60 bg-muted/30 p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <component :is="flagIcons[flag.key] ?? SlidersHorizontal" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-semibold sm:text-base">{{ flagLabel(flag.key) }}</h2>
                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium tabular-nums"
                                :class="enabledCount(flag.key) > 0
                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                    : 'bg-muted text-muted-foreground'"
                            >
                                {{ enabledCount(flag.key) }}/{{ total }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground sm:text-sm">
                            {{ flagDescription(flag.key) }}
                        </p>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-2 sm:p-3">
                <div class="grid grid-cols-1 gap-1 sm:grid-cols-2">
                    <label
                        v-for="country in countries"
                        :key="country.code"
                        class="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-muted/60"
                        :class="{ 'opacity-60': saving[cellKey(country.code, flag.key)] }"
                    >
                        <span class="flex min-w-0 items-center gap-2.5">
                            <span
                                v-if="country.flag"
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-muted text-base leading-none"
                            >{{ country.flag }}</span>
                            <span class="flex min-w-0 flex-col">
                                <span class="truncate text-sm font-medium">
                                    {{ country.native_name || country.name }}
                                </span>
                                <span
                                    class="text-xs"
                                    :class="state[country.code]?.[flag.key]
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-muted-foreground'"
                                >
                                    {{ state[country.code]?.[flag.key]
                                        ? t('pageAdmin.settings.enabled')
                                        : t('pageAdmin.settings.disabled') }}
                                </span>
                            </span>
                        </span>
                        <Switch
                            class="shrink-0"
                            :model-value="state[country.code]?.[flag.key] ?? false"
                            :disabled="saving[cellKey(country.code, flag.key)]"
                            @update:model-value="(v: boolean) => toggle(country.code, flag.key, v)"
                        />
                    </label>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
