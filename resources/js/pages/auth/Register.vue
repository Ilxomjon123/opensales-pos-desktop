<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Check, Gift, Rocket, ShieldCheck, Zap } from 'lucide-vue-next';
import { computed, toRef } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import UsernameStatusBadge from '@/components/UsernameStatusBadge.vue';
import { useUsernameAvailability } from '@/composables/useUsernameAvailability';
import { home } from '@/routes';

type Plan = { key: string; amount: number };
type Country = { id: number; code: string; name: string; native_name: string | null; flag: string | null; phone_prefix: string; currency_symbol?: string };

const props = defineProps<{
    plans: Plan[];
    plansByCountry?: Record<string, Plan[]>;
    trialDays: number;
    countries: Country[];
}>();

const { t } = useI18n();

const brandName = computed(() => usePage().props.project?.name ?? '');

const form = useForm({
    name: '',
    username: '',
    phone: '',
    country_id: props.countries[0]?.id ?? null,
    commission_type: '',
    password: '',
    password_confirmation: '',
});

const selectedCountry = computed<Country | undefined>(() =>
    props.countries.find((c) => c.id === form.country_id),
);

// Tanlangan davlat tariflari + valyuta belgisi (yo'q bo'lsa default plans / so'm).
const activePlans = computed<Plan[]>(() =>
    props.plansByCountry?.[selectedCountry.value?.code ?? ''] ?? props.plans,
);

const planCurrency = computed<string>(() => selectedCountry.value?.currency_symbol ?? "so'm");

const { status: usernameStatus } = useUsernameAvailability(toRef(form, 'username'), {
    endpoint: '/register/username-availability',
});

const perks = [
    { icon: Zap, key: 'perk1' },
    { icon: Rocket, key: 'perk2' },
    { icon: ShieldCheck, key: 'perk3' },
];

function formatMoney(n: number): string {
    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function submit() {
    form.post('/register', {
        onError: () => form.reset('password', 'password_confirmation'),
    });
}

defineOptions({ layout: null });
</script>

<template>
    <Head :title="t('pageAuth.register.headTitle')" />

    <div class="flex min-h-svh bg-background">
        <!-- Chap brand panel (faqat desktop) -->
        <aside class="relative hidden w-2/5 max-w-md flex-col justify-between overflow-hidden bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-700 p-10 text-white lg:flex">
            <div class="absolute -right-16 -top-16 h-72 w-72 rounded-full bg-white/10 blur-2xl" />
            <div class="absolute -bottom-20 -left-10 h-72 w-72 rounded-full bg-black/10 blur-2xl" />

            <Link :href="home()" class="relative flex items-center gap-2 font-semibold">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15">
                    <AppLogoIcon class="size-6" />
                </div>
                <span class="text-lg">{{ brandName }}</span>
            </Link>

            <div class="relative space-y-6">
                <h2 class="text-3xl font-bold leading-tight">{{ t('pageAuth.register.asideTitle') }}</h2>
                <ul class="space-y-4">
                    <li v-for="perk in perks" :key="perk.key" class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/15">
                            <component :is="perk.icon" class="size-4" />
                        </span>
                        <span class="text-sm text-white/90">{{ t(`pageAuth.register.${perk.key}`) }}</span>
                    </li>
                </ul>
            </div>

            <div class="relative flex items-center gap-2 rounded-xl bg-white/10 p-4 text-sm">
                <Gift class="size-5 shrink-0" />
                <span>{{ t('pageAuth.register.trialNotice', { days: props.trialDays }) }}</span>
            </div>
        </aside>

        <!-- O'ng forma paneli -->
        <main class="relative flex flex-1 flex-col items-center overflow-y-auto px-4 py-8 sm:px-6 lg:justify-center lg:py-12">
            <div class="absolute right-4 top-4">
                <LocaleSwitcher />
            </div>

            <div class="w-full max-w-xl">
                <!-- Logo + sarlavha (mobil/kichik ekranda) -->
                <div class="mb-6 flex flex-col items-center text-center lg:items-start lg:text-left">
                    <Link :href="home()" class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-primary-foreground lg:hidden">
                        <AppLogoIcon class="size-7" />
                    </Link>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('pageAuth.register.title') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ t('pageAuth.register.description') }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Tashkilot nomi -->
                    <div class="grid gap-2">
                        <Label for="name">{{ t('pageAuth.register.name') }}</Label>
                        <Input id="name" v-model="form.name" type="text" required autofocus :tabindex="1" :placeholder="t('pageAuth.register.namePlaceholder')" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <!-- Tarif tanlash -->
                    <div class="grid gap-2">
                        <Label>{{ t('pageAuth.register.choosePlan') }}</Label>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <button
                                v-for="plan in activePlans"
                                :key="plan.key"
                                type="button"
                                class="relative flex items-center gap-3 rounded-xl border p-3 text-left transition sm:flex-col sm:items-start sm:gap-1 sm:pr-3"
                                :class="form.commission_type === plan.key
                                    ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                    : 'border-border hover:border-primary/40 hover:bg-muted/40'"
                                @click="form.commission_type = plan.key"
                            >
                                <!-- Matn: mobil chapda, desktopda tepada -->
                                <div class="min-w-0 flex-1 sm:flex-none">
                                    <p class="text-sm font-semibold">{{ t(`pageAuth.register.plans.${plan.key}.name`) }}</p>
                                    <p class="text-[11px] leading-tight text-muted-foreground">{{ t(`pageAuth.register.plans.${plan.key}.unit`) }}</p>
                                </div>
                                <!-- Narx: mobil o'ngda, desktopda matn ostida -->
                                <span class="shrink-0 whitespace-nowrap text-base font-bold text-primary sm:order-none">
                                    {{ formatMoney(plan.amount) }}<span class="text-xs font-normal text-muted-foreground"> {{ planCurrency }}</span>
                                </span>
                                <!-- Tanlov belgisi -->
                                <span
                                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border transition sm:absolute sm:right-2 sm:top-2"
                                    :class="form.commission_type === plan.key ? 'border-primary bg-primary text-primary-foreground' : 'border-muted-foreground/30'"
                                >
                                    <Check v-if="form.commission_type === plan.key" class="h-3 w-3" />
                                </span>
                            </button>
                        </div>
                        <InputError :message="form.errors.commission_type" />
                    </div>

                    <!-- Trial ogohlantirish (mobil/kichik ekranda — desktopda chapda) -->
                    <div class="flex items-start gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2.5 text-xs lg:hidden">
                        <Gift class="mt-0.5 size-4 shrink-0 text-primary" />
                        <p class="text-muted-foreground">{{ t('pageAuth.register.trialNotice', { days: props.trialDays }) }}</p>
                    </div>

                    <!-- Davlat tanlash (telefon/valyutani belgilaydi) -->
                    <div v-if="props.countries.length > 1" class="grid gap-2">
                        <Label>{{ t('pageAuth.register.country') }}</Label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="country in props.countries"
                                :key="country.id"
                                type="button"
                                class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors"
                                :class="form.country_id === country.id ? 'border-primary bg-primary/5 font-medium' : 'border-muted-foreground/30'"
                                @click="form.country_id = country.id"
                            >
                                <span>{{ country.flag }}</span>
                                <span>{{ country.native_name ?? country.name }}</span>
                            </button>
                        </div>
                        <InputError :message="form.errors.country_id" />
                    </div>

                    <!-- Login + telefon (ikki ustun) -->
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="username">{{ t('pageAuth.register.username') }}</Label>
                            <Input id="username" v-model="form.username" type="text" required :tabindex="2" autocomplete="username" :placeholder="t('pageAuth.register.usernamePlaceholder')" />
                            <UsernameStatusBadge :status="usernameStatus" />
                            <InputError :message="form.errors.username" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="phone">{{ t('pageAuth.register.phone') }}</Label>
                            <Input id="phone" v-model="form.phone" type="tel" required :tabindex="3" autocomplete="tel" :placeholder="selectedCountry ? `${selectedCountry.phone_prefix} ...` : t('pageAuth.register.phonePlaceholder')" />
                            <InputError :message="form.errors.phone" />
                        </div>
                    </div>

                    <!-- Parol + tasdiq (ikki ustun) -->
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="password">{{ t('pageAuth.register.password') }}</Label>
                            <PasswordInput id="password" v-model="form.password" required :tabindex="4" autocomplete="new-password" :placeholder="t('pageAuth.register.passwordPlaceholder')" />
                            <InputError :message="form.errors.password" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="password_confirmation">{{ t('pageAuth.register.passwordConfirm') }}</Label>
                            <PasswordInput id="password_confirmation" v-model="form.password_confirmation" required :tabindex="5" autocomplete="new-password" :placeholder="t('pageAuth.register.passwordConfirmPlaceholder')" />
                        </div>
                    </div>

                    <p class="rounded-lg bg-muted/50 px-3 py-2.5 text-xs text-muted-foreground">{{ t('pageAuth.register.botLater') }}</p>

                    <Button type="submit" size="lg" class="w-full" :tabindex="6" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        {{ form.processing ? t('pageAuth.register.submitting') : t('pageAuth.register.submit') }}
                    </Button>

                    <div class="text-center text-sm text-muted-foreground">
                        {{ t('pageAuth.register.haveAccount') }}
                        <Link href="/login" class="font-medium text-foreground underline" :tabindex="7">{{ t('pageAuth.register.loginLink') }}</Link>
                    </div>
                </form>
            </div>
        </main>
    </div>
</template>
