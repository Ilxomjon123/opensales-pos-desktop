<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { AtSign, Bot, KeyRound, MessageSquare, ShoppingCart, Store, User } from 'lucide-vue-next';
import { ref, toRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import UsernameStatusBadge from '@/components/UsernameStatusBadge.vue';
import { useUsernameAvailability } from '@/composables/useUsernameAvailability';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

type Country = { id: number; code: string; name: string; native_name: string | null; flag: string | null; phone_prefix: string };

const props = defineProps<{
    countries: Country[];
}>();

const form = useForm({
    name: '',
    username: '',
    password: '',
    bot_token: '',
    bot_username: '',
    telegram_chat_id: null as number | null,
    min_order_amount: 0 as number,
    country_id: props.countries[0]?.id ?? null,
});

const { status: usernameStatus } = useUsernameAvailability(toRef(form, 'username'));

const tokenStatus = ref<'idle' | 'checking' | 'valid' | 'invalid' | 'taken'>('idle');
const detectedUsername = ref('');
const takenByDealer = ref('');
let tokenDebounce: ReturnType<typeof setTimeout> | null = null;
let tokenAbort: AbortController | null = null;

watch(() => form.bot_token, (token) => {
    if (tokenDebounce) {
        clearTimeout(tokenDebounce);
    }

    if (tokenAbort) {
        tokenAbort.abort();
        tokenAbort = null;
    }

    tokenStatus.value = 'idle';
    detectedUsername.value = '';
    takenByDealer.value = '';

    if (!token || !/^\d+:[\w-]+$/.test(token)) {
        return;
    }

    tokenStatus.value = 'checking';
    tokenDebounce = setTimeout(async () => {
        tokenAbort = new AbortController();

        try {
            const res = await fetch(`/admin/api/verify-token?token=${encodeURIComponent(token)}`, {
                signal: tokenAbort.signal,
            });
            const data = await res.json();

            if (!data.username) {
                tokenStatus.value = 'invalid';

                return;
            }

            if (data.taken_by) {
                tokenStatus.value = 'taken';
                takenByDealer.value = data.taken_by;
                detectedUsername.value = data.username;

                return;
            }

            tokenStatus.value = 'valid';
            detectedUsername.value = data.username;
            form.bot_username = data.username;
        } catch (e) {
            if ((e as Error).name === 'AbortError') {
return;
}

            tokenStatus.value = 'invalid';
        }
    }, 800);
});

function submit() {
    form.post('/admin/dealers');
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.dealersCreate.headTitle')" />

    <div class="mx-auto max-w-3xl p-4 md:p-6">
        <!-- Header -->
        <div class="mb-6 flex items-center gap-3 sm:mb-8 sm:gap-4">
            <Button variant="ghost" size="icon" @click="router.get('/admin/dealers')" class="shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
                </svg>
            </Button>
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageAdmin.dealersCreate.title') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('pageAdmin.dealersCreate.subtitle') }}</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- 1. Asosiy ma'lumotlar -->
            <Card>
                <CardHeader class="pb-4">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                            <Store class="h-4 w-4 text-primary" />
                        </div>
                        <div>
                            <CardTitle class="text-base">{{ t('pageAdmin.dealersCreate.infoTitle') }}</CardTitle>
                            <CardDescription>{{ t('pageAdmin.dealersCreate.infoDescription') }}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <Label for="name">{{ t('pageAdmin.dealersCreate.name') }}</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            :placeholder="t('pageAdmin.dealersCreate.namePlaceholder')"
                            required
                            class="mt-1.5"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div v-if="props.countries.length > 1">
                        <Label>{{ t('pageAdmin.dealersCreate.country') }}</Label>
                        <div class="mt-1.5 flex flex-wrap gap-2">
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
                </CardContent>
            </Card>

            <!-- 2. Login ma'lumotlari -->
            <Card>
                <CardHeader class="pb-4">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500/10">
                            <User class="h-4 w-4 text-blue-500" />
                        </div>
                        <div>
                            <CardTitle class="text-base">{{ t('pageAdmin.dealersCreate.loginTitle') }}</CardTitle>
                            <CardDescription>{{ t('pageAdmin.dealersCreate.loginDescription') }}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <Label for="username">
                            <AtSign class="mr-1 inline h-3.5 w-3.5 text-muted-foreground" />
                            {{ t('pageAdmin.dealersCreate.username') }}
                        </Label>
                        <Input
                            id="username"
                            v-model="form.username"
                            autocomplete="username"
                            :placeholder="t('pageAdmin.dealersCreate.usernamePlaceholder')"
                            required
                            class="mt-1.5"
                        />
                        <UsernameStatusBadge :status="usernameStatus" />
                        <InputError :message="form.errors.username" />
                    </div>

                    <div>
                        <Label for="password">
                            <KeyRound class="mr-1 inline h-3.5 w-3.5 text-muted-foreground" />
                            {{ t('pageAdmin.dealersCreate.password') }}
                        </Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            :placeholder="t('pageAdmin.dealersCreate.passwordPlaceholder')"
                            required
                            class="mt-1.5"
                        />
                        <InputError :message="form.errors.password" />
                    </div>
                </CardContent>
            </Card>

            <!-- 3. Telegram bot -->
            <Card>
                <CardHeader class="pb-4">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-500/10">
                            <Bot class="h-4 w-4 text-sky-500" />
                        </div>
                        <div>
                            <CardTitle class="text-base">{{ t('pageAdmin.dealersCreate.botTitle') }}</CardTitle>
                            <CardDescription>{{ t('pageAdmin.dealersCreate.botDescription') }}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <Label for="bot_token">{{ t('pageAdmin.dealersCreate.botToken') }}</Label>
                        <div class="relative mt-1.5">
                            <Input
                                id="bot_token"
                                v-model="form.bot_token"
                                :placeholder="t('pageAdmin.dealersCreate.botTokenPlaceholder')"
                                class="font-mono pr-28"
                            />
                            <div class="absolute inset-y-0 right-2 flex items-center">
                                <Spinner v-if="tokenStatus === 'checking'" class="h-4 w-4" />
                                <Badge v-else-if="tokenStatus === 'valid'" variant="default" class="text-xs">
                                    @{{ detectedUsername }}
                                </Badge>
                                <Badge v-else-if="tokenStatus === 'taken'" variant="destructive" class="text-xs">
                                    {{ t('pageAdmin.dealersCreate.tokenTaken') }}
                                </Badge>
                                <Badge v-else-if="tokenStatus === 'invalid'" variant="destructive" class="text-xs">
                                    {{ t('pageAdmin.dealersCreate.tokenInvalid') }}
                                </Badge>
                            </div>
                        </div>
                        <p v-if="tokenStatus === 'taken'" class="mt-1.5 text-xs text-destructive">
                            {{ t('pageAdmin.dealersCreate.tokenTakenMessage', { name: takenByDealer }) }}
                        </p>
                        <InputError :message="form.errors.bot_token" />
                    </div>

                    <div>
                        <Label for="chat_id">
                            <MessageSquare class="mr-1 inline h-3.5 w-3.5 text-muted-foreground" />
                            {{ t('pageAdmin.dealersCreate.chatId') }}
                            <span class="ml-1 text-xs font-normal text-muted-foreground">{{ t('pageAdmin.dealersCreate.optional') }}</span>
                        </Label>
                        <Input
                            id="chat_id"
                            type="number"
                            v-model.number="form.telegram_chat_id"
                            :placeholder="t('pageAdmin.dealersCreate.chatIdPlaceholder')"
                            class="mt-1.5"
                        />
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            {{ t('pageAdmin.dealersCreate.chatIdHint') }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- 4. Buyurtma sozlamalari -->
            <Card>
                <CardHeader class="pb-4">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/10">
                            <ShoppingCart class="h-4 w-4 text-amber-500" />
                        </div>
                        <div>
                            <CardTitle class="text-base">{{ t('pageAdmin.dealersCreate.orderSettingsTitle') }}</CardTitle>
                            <CardDescription>{{ t('pageAdmin.dealersCreate.orderSettingsDescription') }}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div>
                        <Label for="min_order_amount">
                            {{ t('pageAdmin.dealersCreate.minOrderAmount') }}
                            <span class="ml-1 text-xs font-normal text-muted-foreground">{{ t('pageAdmin.dealersCreate.minOrderUnit') }}</span>
                        </Label>
                        <Input
                            id="min_order_amount"
                            type="number"
                            min="0"
                            step="1000"
                            v-model.number="form.min_order_amount"
                            placeholder="0"
                            class="mt-1.5"
                        />
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            {{ t('pageAdmin.dealersCreate.minOrderHint') }}
                        </p>
                        <InputError :message="form.errors.min_order_amount" />
                    </div>
                </CardContent>
            </Card>

            <!-- Actions -->
            <div class="flex flex-col-reverse items-stretch gap-2 pt-2 sm:flex-row sm:items-center sm:justify-end sm:gap-3">
                <Button variant="outline" type="button" @click="router.get('/admin/dealers')">
                    {{ t('pageAdmin.dealersCreate.cancel') }}
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing || tokenStatus === 'invalid' || tokenStatus === 'taken'"
                    class="sm:min-w-[120px]"
                >
                    <Spinner v-if="form.processing" class="mr-2" />
                    {{ form.processing ? t('pageAdmin.dealersCreate.creating') : t('pageAdmin.dealersCreate.create') }}
                </Button>
            </div>
        </form>
    </div>
</template>
