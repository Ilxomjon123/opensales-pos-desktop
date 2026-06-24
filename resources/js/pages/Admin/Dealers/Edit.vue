<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AtSign, Bot, Check, Copy, KeyRound, Link as LinkIcon, MessageSquare, Plus, RefreshCcw, Search, ShoppingCart, Store, Trash2, User, Users, Webhook } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, toRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Spinner } from '@/components/ui/spinner';
import UsernameStatusBadge from '@/components/UsernameStatusBadge.vue';
import { confirm } from '@/composables/useConfirm';
import { useUsernameAvailability } from '@/composables/useUsernameAvailability';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime, formatUnix } from '@/lib/date';
import { currencySymbol } from '@/lib/format';
import type { DealerItem } from '@/types';

const { t } = useI18n();

type WebhookSnapshot = {
    expected_url: string;
    set_at: string | null;
    telegram: {
        url: string;
        pending_update_count: number;
        last_error_message: string | null;
        last_error_date: number | null;
    } | null;
    matches_expected: boolean;
};

type Country = { id: number; code: string; name: string; native_name: string | null; flag: string | null; phone_prefix: string };

const props = defineProps<{
    dealer: { data: DealerItem & { country_id: number | null; currency: string | null } };
    dealerUsername: string;
    webhook: WebhookSnapshot;
    shopsCount: number;
    regions: RegionOption[];
    countries: Country[];
}>();

type RegionOption = { name: string; districts: string[] };

type DirectoryEntry = {
    id: number;
    name: string;
    legal_name: string | null;
    inn: string | null;
    phone: string | null;
    region: string | null;
    district: string | null;
};

const d = props.dealer.data;

const form = reactive({
    name: d.name,
    username: props.dealerUsername,
    password: '',
    bot_token: '',
    telegram_chat_id: d.telegram_chat_id,
    min_order_amount: d.min_order_amount ?? 0,
    sells_on_marketplace: d.sells_on_marketplace ?? false,
    marketplace_commission_type: d.marketplace_commission_type ?? null,
    marketplace_platform_fee_rate: d.marketplace_platform_fee_rate ?? null,
    marketplace_fixed_commission_amount: d.marketplace_fixed_commission_amount ?? null,
    country_id: d.country_id ?? null,
    currency: d.currency ?? 'UZS',
});

const marketplaceCommissionTypes = computed(() => [
    { value: '', label: t('pageAdmin.dealersEdit.marketplaceCommissionBot') },
    { value: 'turnover_percentage', label: t('pageAdmin.dealersEdit.marketplaceCommissionTurnover') },
    { value: 'fixed_per_order', label: t('pageAdmin.dealersEdit.marketplaceCommissionFixed') },
]);

const processing = ref(false);
const errors = ref<Record<string, string>>({});

const { status: usernameStatus } = useUsernameAvailability(toRef(form, 'username'), {
    initialValue: props.dealerUsername,
});

// Token verification
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
            const url = `/admin/api/verify-token?token=${encodeURIComponent(token)}&dealer_id=${d.id}`;
            const res = await fetch(url, { signal: tokenAbort.signal });
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
        } catch (e) {
            if ((e as Error).name === 'AbortError') {
return;
}

            tokenStatus.value = 'invalid';
        }
    }, 800);
});

function submit() {
    processing.value = true;
    const data: Record<string, unknown> = {
        name: form.name,
        username: form.username,
        telegram_chat_id: form.telegram_chat_id,
        min_order_amount: form.min_order_amount,
        sells_on_marketplace: form.sells_on_marketplace,
        marketplace_commission_type: form.marketplace_commission_type || null,
        marketplace_platform_fee_rate: form.marketplace_platform_fee_rate,
        marketplace_fixed_commission_amount: form.marketplace_fixed_commission_amount,
        country_id: form.country_id,
        currency: form.currency,
    };

    if (form.password) {
data.password = form.password;
}

    if (form.bot_token) {
data.bot_token = form.bot_token;
}

    router.put(`/admin/dealers/${d.id}`, data, {
        onFinish: () => {
 processing.value = false; 
},
        onError: (e) => {
 errors.value = e; 
},
    });
}

const webhook = computed(() => props.webhook);
const webhookProcessing = ref(false);
const copied = ref(false);

const webhookStatus = computed<'active' | 'mismatch' | 'error' | 'none'>(() => {
    const w = webhook.value;

    if (!w.telegram || w.telegram.url === '') {
return 'none';
}

    if (w.telegram.last_error_message) {
return 'error';
}

    if (!w.matches_expected) {
return 'mismatch';
}

    return 'active';
});

const webhookStatusLabel = computed(() => ({
    active: t('pageAdmin.dealersEdit.webhookStatusActive'),
    mismatch: t('pageAdmin.dealersEdit.webhookStatusMismatch'),
    error: t('pageAdmin.dealersEdit.webhookStatusError'),
    none: t('pageAdmin.dealersEdit.webhookStatusNone'),
}[webhookStatus.value]));

const webhookStatusVariant = computed<'default' | 'destructive' | 'outline'>(() => ({
    active: 'default',
    mismatch: 'outline',
    error: 'destructive',
    none: 'outline',
}[webhookStatus.value] as 'default' | 'destructive' | 'outline'));

function setWebhook() {
    webhookProcessing.value = true;
    router.post(`/admin/dealers/${d.id}/webhook`, {}, {
        preserveScroll: true,
        onFinish: () => {
 webhookProcessing.value = false; 
},
    });
}

async function removeWebhook() {
    const ok = await confirm({
        title: t('pageAdmin.dealersEdit.removeConfirmTitle'),
        description: t('pageAdmin.dealersEdit.removeConfirmDescription'),
        confirmText: t('pageAdmin.dealersEdit.removeConfirm'),
        variant: 'destructive',
    });

    if (!ok) {
return;
}

    webhookProcessing.value = true;
    router.delete(`/admin/dealers/${d.id}/webhook`, {
        preserveScroll: true,
        onFinish: () => {
 webhookProcessing.value = false; 
},
    });
}

function refreshWebhook() {
    router.reload({ only: ['webhook'] });
}

function copyUrl() {
    navigator.clipboard.writeText(webhook.value.expected_url);
    copied.value = true;
    setTimeout(() => {
 copied.value = false; 
}, 1500);
}

// Spravochnikdan mijoz biriktirish
const dirSearch = ref('');
const dirRegion = ref('');
const dirDistrict = ref('');
const dirResults = ref<DirectoryEntry[]>([]);
const dirSelected = ref<Set<number>>(new Set());
const dirSearching = ref(false);
const dirLoadingMore = ref(false);
const dirHasMore = ref(false);
const dirAssigning = ref(false);
const assignedCount = ref(props.shopsCount);
let dirDebounce: ReturnType<typeof setTimeout> | null = null;
let dirAbort: AbortController | null = null;

const dirDistrictItems = computed(() => {
    const found = props.regions.find((r) => r.name === dirRegion.value);

    return (found?.districts ?? []).map((name) => ({ value: name, label: name }));
});

// Hozir yuklangan natijalardagi barchasi tanlanganmi
const dirAllSelected = computed(() => dirResults.value.length > 0 && dirResults.value.every((s) => dirSelected.value.has(s.id)));

watch(dirSearch, () => {
    if (dirDebounce) {
        clearTimeout(dirDebounce);
    }

    dirDebounce = setTimeout(() => loadDirectory(), 400);
});

function onRegionChange() {
    dirDistrict.value = '';
    loadDirectory();
}

async function fetchPage(offset: number, signal: AbortSignal): Promise<{ shops: DirectoryEntry[]; has_more: boolean }> {
    const params = new URLSearchParams({
        q: dirSearch.value ?? '',
        region: dirRegion.value ?? '',
        district: dirDistrict.value ?? '',
        offset: String(offset),
    });
    const res = await fetch(`/admin/dealers/${d.id}/directory-search?${params.toString()}`, {
        signal,
        headers: { Accept: 'application/json' },
    });

    return res.json();
}

// Filter/qidiruv o'zgarganda — noldan qayta yuklash
async function loadDirectory() {
    if (dirAbort) {
        dirAbort.abort();
    }

    dirAbort = new AbortController();
    dirSearching.value = true;

    try {
        const data = await fetchPage(0, dirAbort.signal);
        dirResults.value = data.shops ?? [];
        dirHasMore.value = data.has_more ?? false;
    } catch (e) {
        if ((e as Error).name === 'AbortError') {
            return;
        }

        dirResults.value = [];
        dirHasMore.value = false;
    } finally {
        dirSearching.value = false;
    }
}

// Scroll pastga tushganda — keyingi sahifani qo'shib yuklash
async function loadMore() {
    if (dirLoadingMore.value || dirSearching.value || !dirHasMore.value || !dirAbort) {
        return;
    }

    dirLoadingMore.value = true;
    const signal = dirAbort.signal;

    try {
        const data = await fetchPage(dirResults.value.length, signal);
        dirResults.value = [...dirResults.value, ...(data.shops ?? [])];
        dirHasMore.value = data.has_more ?? false;
    } catch (e) {
        if ((e as Error).name === 'AbortError') {
            return;
        }
    } finally {
        dirLoadingMore.value = false;
    }
}

function onDirScroll(e: Event) {
    const el = e.target as HTMLElement;

    if (el.scrollHeight - el.scrollTop - el.clientHeight < 120) {
        loadMore();
    }
}

function toggleDir(id: number) {
    const next = new Set(dirSelected.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    dirSelected.value = next;
}

function toggleSelectAll() {
    const next = new Set(dirSelected.value);

    if (dirAllSelected.value) {
        dirResults.value.forEach((s) => next.delete(s.id));
    } else {
        dirResults.value.forEach((s) => next.add(s.id));
    }

    dirSelected.value = next;
}

async function assignShops() {
    if (dirSelected.value.size === 0 || dirAssigning.value) {
        return;
    }

    dirAssigning.value = true;

    try {
        const res = await fetch(`/admin/dealers/${d.id}/assign-shops`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ directory_ids: Array.from(dirSelected.value) }),
        });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }

        const data = await res.json();
        assignedCount.value = data.shops_count ?? assignedCount.value;
        dirSelected.value = new Set();
        toast.success(data.message);
        await loadDirectory();
    } catch {
        toast.error(t('pageAdmin.dealersEdit.assignFailed'));
    } finally {
        dirAssigning.value = false;
    }
}

onMounted(() => loadDirectory());

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.dealersEdit.headTitle', { name: d.name })" />

    <div class="mx-auto max-w-3xl p-4 md:p-6">
        <div class="mb-6 flex items-center gap-3 sm:mb-8 sm:gap-4">
            <Button variant="ghost" size="icon" @click="router.get('/admin/dealers')" class="shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
                </svg>
            </Button>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">{{ d.name }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('pageAdmin.dealersEdit.subtitle') }}</p>
            </div>
            <Badge :variant="d.is_active ? 'default' : 'destructive'" class="shrink-0">
                {{ d.is_active ? t('pageAdmin.dealersEdit.active') : t('pageAdmin.dealersEdit.inactive') }}
            </Badge>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <Card>
                <CardHeader class="pb-4">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                            <Store class="h-4 w-4 text-primary" />
                        </div>
                        <div>
                            <CardTitle class="text-base">{{ t('pageAdmin.dealersEdit.infoTitle') }}</CardTitle>
                            <CardDescription>{{ t('pageAdmin.dealersEdit.infoDescription') }}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <Label for="name">{{ t('pageAdmin.dealersEdit.name') }}</Label>
                        <Input id="name" v-model="form.name" required class="mt-1.5" />
                        <InputError :message="errors.name" />
                    </div>

                    <div v-if="props.countries.length > 1">
                        <Label>{{ t('pageAdmin.dealersEdit.country') }}</Label>
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
                        <InputError :message="errors.country_id" />
                    </div>

                    <div>
                        <Label>{{ t('pageAdmin.dealersEdit.currency') }}</Label>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            <button
                                v-for="cur in ['UZS', 'RUB']"
                                :key="cur"
                                type="button"
                                class="rounded-lg border px-3 py-2 text-sm transition-colors"
                                :class="form.currency === cur ? 'border-primary bg-primary/5 font-medium' : 'border-muted-foreground/30'"
                                @click="form.currency = cur"
                            >
                                {{ cur }}
                            </button>
                        </div>
                        <InputError :message="errors.currency" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-4">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500/10">
                            <User class="h-4 w-4 text-blue-500" />
                        </div>
                        <div>
                            <CardTitle class="text-base">{{ t('pageAdmin.dealersEdit.loginTitle') }}</CardTitle>
                            <CardDescription>{{ t('pageAdmin.dealersEdit.loginDescription') }}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <Label for="username">
                            <AtSign class="mr-1 inline h-3.5 w-3.5 text-muted-foreground" />
                            {{ t('pageAdmin.dealersEdit.username') }}
                        </Label>
                        <Input id="username" v-model="form.username" autocomplete="username" required class="mt-1.5" />
                        <UsernameStatusBadge :status="usernameStatus" />
                        <InputError :message="errors.username" />
                    </div>
                    <div>
                        <Label for="password">
                            <KeyRound class="mr-1 inline h-3.5 w-3.5 text-muted-foreground" />
                            {{ t('pageAdmin.dealersEdit.newPassword') }}
                            <span class="ml-1 text-xs font-normal text-muted-foreground">{{ t('pageAdmin.dealersEdit.passwordHint') }}</span>
                        </Label>
                        <Input id="password" v-model="form.password" type="password" :placeholder="t('pageAdmin.dealersEdit.newPasswordPlaceholder')" class="mt-1.5" />
                        <InputError :message="errors.password" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-4">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-500/10">
                            <Bot class="h-4 w-4 text-sky-500" />
                        </div>
                        <div>
                            <CardTitle class="text-base">{{ t('pageAdmin.dealersEdit.botTitle') }}</CardTitle>
                            <CardDescription>
                                {{ t('pageAdmin.dealersEdit.currentBot') }} <span class="font-mono">@{{ d.bot_username }}</span>
                                <Badge v-if="d.webhook_active" variant="default" class="ml-2 text-xs">{{ t('pageAdmin.dealersEdit.webhookActive') }}</Badge>
                                <Badge v-else variant="outline" class="ml-2 text-xs">{{ t('pageAdmin.dealersEdit.webhookInactive') }}</Badge>
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <Label for="bot_token">
                            {{ t('pageAdmin.dealersEdit.newBotToken') }}
                            <span class="ml-1 text-xs font-normal text-muted-foreground">{{ t('pageAdmin.dealersEdit.passwordHint') }}</span>
                        </Label>
                        <div class="relative mt-1.5">
                            <Input
                                id="bot_token"
                                v-model="form.bot_token"
                                :placeholder="t('pageAdmin.dealersEdit.newBotTokenPlaceholder')"
                                class="font-mono pr-28"
                            />
                            <div class="absolute inset-y-0 right-2 flex items-center">
                                <Spinner v-if="tokenStatus === 'checking'" class="h-4 w-4" />
                                <Badge v-else-if="tokenStatus === 'valid'" variant="default" class="text-xs">
                                    @{{ detectedUsername }}
                                </Badge>
                                <Badge v-else-if="tokenStatus === 'taken'" variant="destructive" class="text-xs">
                                    {{ t('pageAdmin.dealersEdit.tokenTaken') }}
                                </Badge>
                                <Badge v-else-if="tokenStatus === 'invalid'" variant="destructive" class="text-xs">
                                    {{ t('pageAdmin.dealersEdit.tokenInvalid') }}
                                </Badge>
                            </div>
                        </div>
                        <p v-if="tokenStatus === 'taken'" class="mt-1.5 text-xs text-destructive">
                            {{ t('pageAdmin.dealersEdit.tokenTakenMessage', { name: takenByDealer }) }}
                        </p>
                        <InputError :message="errors.bot_token" />
                    </div>

                    <div>
                        <Label for="chat_id">
                            <MessageSquare class="mr-1 inline h-3.5 w-3.5 text-muted-foreground" />
                            {{ t('pageAdmin.dealersEdit.chatId') }}
                            <span class="ml-1 text-xs font-normal text-muted-foreground">{{ t('pageAdmin.dealersEdit.optional') }}</span>
                        </Label>
                        <Input
                            id="chat_id"
                            type="number"
                            v-model.number="form.telegram_chat_id"
                            :placeholder="t('pageAdmin.dealersEdit.chatIdPlaceholder')"
                            class="mt-1.5"
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-4">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/10">
                            <ShoppingCart class="h-4 w-4 text-amber-500" />
                        </div>
                        <div>
                            <CardTitle class="text-base">{{ t('pageAdmin.dealersEdit.orderSettingsTitle') }}</CardTitle>
                            <CardDescription>{{ t('pageAdmin.dealersEdit.orderSettingsDescription') }}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div>
                        <Label for="min_order_amount">
                            {{ t('pageAdmin.dealersEdit.minOrderAmount') }}
                            <span class="ml-1 text-xs font-normal text-muted-foreground">{{ t('pageAdmin.dealersEdit.minOrderUnit') }}</span>
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
                            {{ t('pageAdmin.dealersEdit.minOrderHint') }}
                        </p>
                        <InputError :message="errors.min_order_amount" />
                    </div>
                </CardContent>
            </Card>

            <!-- Birja (marketplace) sotuvchisi -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10">
                            <Store class="h-4 w-4 text-emerald-500" />
                        </div>
                        <div>
                            <CardTitle class="text-base">{{ t('pageAdmin.dealersEdit.marketplaceTitle') }}</CardTitle>
                            <CardDescription>{{ t('pageAdmin.dealersEdit.marketplaceDescription') }}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <label class="flex items-start gap-2 rounded-md border bg-muted/30 px-3 py-2.5">
                        <input type="checkbox" v-model="form.sells_on_marketplace" class="mt-0.5 rounded border-input" />
                        <span>
                            <span class="block text-sm font-medium">{{ t('pageAdmin.dealersEdit.marketplaceEnable') }}</span>
                            <span class="mt-0.5 block text-xs text-muted-foreground">
                                {{ t('pageAdmin.dealersEdit.marketplaceEnableHint') }}
                            </span>
                        </span>
                    </label>

                    <div v-if="form.sells_on_marketplace" class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <Label class="text-xs">{{ t('pageAdmin.dealersEdit.marketplaceCommissionType') }}</Label>
                            <select v-model="form.marketplace_commission_type" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                                <option v-for="o in marketplaceCommissionTypes" :key="o.value" :value="o.value || null">{{ o.label }}</option>
                            </select>
                            <InputError :message="errors.marketplace_commission_type" />
                        </div>
                        <div v-if="form.marketplace_commission_type === 'turnover_percentage'">
                            <Label class="text-xs">{{ t('pageAdmin.dealersEdit.marketplacePercent') }}</Label>
                            <Input type="number" min="0" max="100" step="0.01" v-model.number="form.marketplace_platform_fee_rate" class="mt-1.5" placeholder="0.00" />
                            <InputError :message="errors.marketplace_platform_fee_rate" />
                        </div>
                        <div v-else-if="form.marketplace_commission_type">
                            <Label class="text-xs">{{ t('pageAdmin.dealersEdit.marketplaceAmount') }} ({{ currencySymbol() }})</Label>
                            <Input type="number" min="0" step="100" v-model.number="form.marketplace_fixed_commission_amount" class="mt-1.5" placeholder="0" />
                            <InputError :message="errors.marketplace_fixed_commission_amount" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="flex flex-col-reverse items-stretch gap-2 pt-2 sm:flex-row sm:items-center sm:justify-end sm:gap-3">
                <Button variant="outline" type="button" @click="router.get('/admin/dealers')">
                    {{ t('pageAdmin.dealersEdit.cancel') }}
                </Button>
                <Button type="submit" :disabled="processing || tokenStatus === 'invalid' || tokenStatus === 'taken'" class="sm:min-w-[120px]">
                    <Spinner v-if="processing" class="mr-2" />
                    {{ processing ? t('pageAdmin.dealersEdit.saving') : t('pageAdmin.dealersEdit.save') }}
                </Button>
            </div>
        </form>

        <!-- Spravochnikdan mijoz biriktirish -->
        <Card class="mt-6">
            <CardHeader class="pb-4">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-500/10">
                        <Users class="h-4 w-4 text-violet-500" />
                    </div>
                    <div>
                        <CardTitle class="text-base">{{ t('pageAdmin.dealersEdit.assignTitle') }}</CardTitle>
                        <CardDescription>{{ t('pageAdmin.dealersEdit.assignDescription', { count: assignedCount }) }}</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="dirSearch" :placeholder="t('pageAdmin.dealersEdit.assignSearchPlaceholder')" class="pl-9" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <SearchableSelect
                        v-model="dirRegion"
                        :items="props.regions"
                        value-key="name"
                        label-key="name"
                        :placeholder="t('pageAdmin.dealersEdit.assignRegionAll')"
                        :search-placeholder="t('pageAdmin.dealersEdit.assignRegionSearch')"
                        :empty-text="t('pageAdmin.dealersEdit.assignRegionEmpty')"
                        clearable
                        @change="onRegionChange"
                    />
                    <SearchableSelect
                        v-model="dirDistrict"
                        :items="dirDistrictItems"
                        :disabled="!dirRegion"
                        :placeholder="t('pageAdmin.dealersEdit.assignDistrictAll')"
                        :search-placeholder="t('pageAdmin.dealersEdit.assignDistrictSearch')"
                        :empty-text="t('pageAdmin.dealersEdit.assignDistrictEmpty')"
                        clearable
                        @change="loadDirectory()"
                    />
                </div>

                <div class="max-h-80 divide-y overflow-y-auto rounded-md border" @scroll="onDirScroll">
                    <div v-if="dirSearching" class="flex items-center justify-center py-8 text-sm text-muted-foreground">
                        <Spinner class="mr-2" /> {{ t('pageAdmin.dealersEdit.assignSearching') }}
                    </div>
                    <div v-else-if="dirResults.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        {{ t('pageAdmin.dealersEdit.assignEmpty') }}
                    </div>
                    <template v-else>
                        <label class="flex cursor-pointer items-center gap-3 bg-muted/30 px-3 py-2.5 font-medium hover:bg-muted/50">
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-input"
                                :checked="dirAllSelected"
                                @change="toggleSelectAll"
                            />
                            <span class="text-sm">{{ t('pageAdmin.dealersEdit.assignSelectAll', { count: dirResults.length }) }}</span>
                        </label>
                        <label
                            v-for="s in dirResults"
                            :key="s.id"
                            class="flex cursor-pointer items-center gap-3 px-3 py-2.5 hover:bg-muted/40"
                        >
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-input"
                                :checked="dirSelected.has(s.id)"
                                @change="toggleDir(s.id)"
                            />
                            <div class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ s.legal_name || s.name }}</span>
                                <div class="truncate text-xs text-muted-foreground">
                                    <span v-if="s.inn">STIR {{ s.inn }}</span>
                                    <span v-if="s.phone"> · {{ s.phone }}</span>
                                    <span v-if="s.region"> · {{ s.region }}<span v-if="s.district">, {{ s.district }}</span></span>
                                </div>
                            </div>
                        </label>
                        <div v-if="dirLoadingMore" class="flex items-center justify-center py-3 text-sm text-muted-foreground">
                            <Spinner class="mr-2" /> {{ t('pageAdmin.dealersEdit.assignSearching') }}
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">{{ t('pageAdmin.dealersEdit.assignSelected', { count: dirSelected.size }) }}</span>
                    <Button type="button" :disabled="dirSelected.size === 0 || dirAssigning" @click="assignShops">
                        <Spinner v-if="dirAssigning" class="mr-2" />
                        <Plus v-else class="mr-2 h-4 w-4" />
                        {{ t('pageAdmin.dealersEdit.assignButton') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Webhook boshqaruvi -->
        <Card class="mt-6">
            <CardHeader class="pb-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10">
                            <Webhook class="h-4 w-4 text-emerald-500" />
                        </div>
                        <div>
                            <CardTitle class="text-base">{{ t('pageAdmin.dealersEdit.webhookTitle') }}</CardTitle>
                            <CardDescription>{{ t('pageAdmin.dealersEdit.webhookSectionDescription') }}</CardDescription>
                        </div>
                    </div>
                    <Badge :variant="webhookStatusVariant">{{ webhookStatusLabel }}</Badge>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Expected URL -->
                <div>
                    <Label class="flex items-center gap-1.5">
                        <LinkIcon class="h-3.5 w-3.5 text-muted-foreground" />
                        {{ t('pageAdmin.dealersEdit.expectedUrl') }}
                    </Label>
                    <div class="mt-1 flex items-center gap-2 rounded-md border bg-muted/30 px-3 py-2">
                        <code class="flex-1 truncate text-xs">{{ webhook.expected_url }}</code>
                        <Button size="icon" variant="ghost" type="button" class="h-7 w-7" @click="copyUrl">
                            <Check v-if="copied" class="h-3.5 w-3.5 text-emerald-600" />
                            <Copy v-else class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>

                <!-- Telegram holati -->
                <div class="rounded-md border p-3 text-sm">
                    <div v-if="webhook.telegram" class="space-y-2">
                        <div class="flex flex-col gap-1 sm:flex-row sm:justify-between">
                            <span class="text-muted-foreground">{{ t('pageAdmin.dealersEdit.telegramUrl') }}</span>
                            <code class="truncate text-xs">{{ webhook.telegram.url || t('pageAdmin.dealersEdit.emptyValue') }}</code>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">{{ t('pageAdmin.dealersEdit.pendingUpdates') }}</span>
                            <span class="font-mono">{{ webhook.telegram.pending_update_count }}</span>
                        </div>
                        <div class="flex flex-col gap-1 sm:flex-row sm:justify-between">
                            <span class="text-muted-foreground">{{ t('pageAdmin.dealersEdit.lastSet') }}</span>
                            <span>{{ formatDateTime(webhook.set_at) }}</span>
                        </div>
                        <div v-if="webhook.telegram.last_error_message" class="rounded-md border border-destructive/30 bg-destructive/5 p-2">
                            <p class="text-xs font-medium text-destructive">{{ t('pageAdmin.dealersEdit.errorLabel') }}</p>
                            <p class="mt-1 text-xs">{{ webhook.telegram.last_error_message }}</p>
                            <p v-if="webhook.telegram.last_error_date" class="mt-1 text-xs text-muted-foreground">
                                {{ formatUnix(webhook.telegram.last_error_date) }}
                            </p>
                        </div>
                    </div>
                    <div v-else class="text-sm text-muted-foreground">
                        {{ t('pageAdmin.dealersEdit.telegramFetchFail') }}
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-wrap gap-2">
                    <Button type="button" @click="setWebhook" :disabled="webhookProcessing">
                        <Spinner v-if="webhookProcessing" class="mr-2" />
                        <Webhook v-else class="mr-2 h-4 w-4" />
                        {{ webhook.telegram?.url ? t('pageAdmin.dealersEdit.reset') : t('pageAdmin.dealersEdit.setWebhook') }}
                    </Button>
                    <Button type="button" variant="outline" @click="refreshWebhook">
                        <RefreshCcw class="mr-2 h-4 w-4" />
                        {{ t('pageAdmin.dealersEdit.refreshState') }}
                    </Button>
                    <Button v-if="webhook.telegram?.url" type="button" variant="outline" class="text-destructive hover:bg-destructive/10 hover:text-destructive" @click="removeWebhook" :disabled="webhookProcessing">
                        <Trash2 class="mr-2 h-4 w-4" />
                        {{ t('pageAdmin.dealersEdit.removeWebhook') }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
