<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BellRing,
    Bot,
    Check,
    Copy,
    ExternalLink,
    Globe,
    Link as LinkIcon,
    Lock,
    MessageSquare,
    PackageX,
    RefreshCcw,
    ShoppingCart,
    Sparkles,
    Trash2,
    Webhook,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';

const { t } = useI18n();
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { confirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime, formatUnix } from '@/lib/date';

type DealerItem = {
    id: number;
    name: string;
    bot_username: string | null;
    bot_display_name: string | null;
    bot_short_description: string | null;
    bot_description: string | null;
    contact_phone: string | null;
    bot_display_name_default: string;
    bot_short_description_default: string;
    bot_description_default: string;
    telegram_chat_id: number | null;
    is_active: boolean;
    visibility: 'private' | 'public';
    min_order_amount: number;
    show_out_of_stock: boolean;
    notify_on_price_change: boolean;
    notify_on_new_product: boolean;
    webhook_set_at: string | null;
    webhook_active: boolean;
};

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

const props = defineProps<{
    dealer: { data: DealerItem };
    webhook: WebhookSnapshot;
    miniapp_url: string;
    notify_connect_url: string | null;
}>();

const d = computed(() => props.dealer.data);
const webhook = computed(() => props.webhook);
const miniappUrl = computed(() => props.miniapp_url);
const notifyConnectUrl = computed(() => props.notify_connect_url);

const showManualChat = ref(false);

function refreshPage(): void {
    router.reload();
}

const form = reactive({
    bot_token: '',
    telegram_chat_id: d.value.telegram_chat_id,
    min_order_amount: d.value.min_order_amount ?? 0,
    show_out_of_stock: d.value.show_out_of_stock ?? true,
    notify_on_price_change: d.value.notify_on_price_change ?? true,
    notify_on_new_product: d.value.notify_on_new_product ?? true,
    bot_display_name: d.value.bot_display_name ?? '',
    bot_short_description: d.value.bot_short_description ?? '',
    bot_description: d.value.bot_description ?? '',
    contact_phone: d.value.contact_phone ?? '',
    visibility: d.value.visibility ?? 'private',
});

const processing = ref(false);
const errors = ref<Record<string, string>>({});

const tokenStatus = ref<'idle' | 'checking' | 'valid' | 'invalid' | 'taken'>(
    'idle',
);
const detectedUsername = ref('');
const takenByDealer = ref('');
let tokenDebounce: ReturnType<typeof setTimeout> | null = null;
let tokenAbort: AbortController | null = null;

watch(
    () => form.bot_token,
    (token) => {
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
                const res = await fetch(
                    `/dealer/api/verify-token?token=${encodeURIComponent(token)}`,
                    {
                        signal: tokenAbort.signal,
                    },
                );
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
    },
);

function submit() {
    processing.value = true;
    errors.value = {};

    const data: Record<string, unknown> = {
        telegram_chat_id: form.telegram_chat_id,
        min_order_amount: form.min_order_amount,
        show_out_of_stock: form.show_out_of_stock,
        notify_on_price_change: form.notify_on_price_change,
        notify_on_new_product: form.notify_on_new_product,
        bot_display_name: form.bot_display_name,
        bot_short_description: form.bot_short_description,
        bot_description: form.bot_description,
        contact_phone: form.contact_phone,
        visibility: form.visibility,
    };

    if (form.bot_token) {
        data.bot_token = form.bot_token;
    }

    router.put('/dealer/bot', data, {
        preserveScroll: true,
        onSuccess: () => {
            form.bot_token = '';
            tokenStatus.value = 'idle';
        },
        onError: (e) => {
            errors.value = e;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

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

const webhookStatusLabel = computed(
    () =>
        ({
            active: t('pageDealer.bot.statusActive'),
            mismatch: t('pageDealer.bot.statusMismatch'),
            error: t('pageDealer.bot.statusError'),
            none: t('pageDealer.bot.statusNone'),
        })[webhookStatus.value],
);

const webhookStatusVariant = computed<'default' | 'destructive' | 'outline'>(
    () =>
        ({
            active: 'default' as const,
            mismatch: 'outline' as const,
            error: 'destructive' as const,
            none: 'outline' as const,
        })[webhookStatus.value],
);

function setWebhook() {
    webhookProcessing.value = true;
    router.post(
        '/dealer/bot/webhook',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                webhookProcessing.value = false;
            },
        },
    );
}

async function removeWebhook() {
    const ok = await confirm({
        title: t('pageDealer.bot.webhookDeleteTitle'),
        description: t('pageDealer.bot.webhookDeleteDesc'),
        confirmText: t('pageDealer.bot.webhookDeleteConfirm'),
        variant: 'destructive',
    });

    if (!ok) {
        return;
    }

    webhookProcessing.value = true;
    router.delete('/dealer/bot/webhook', {
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

const miniappCopied = ref(false);

function copyMiniappUrl() {
    navigator.clipboard.writeText(miniappUrl.value);
    miniappCopied.value = true;
    setTimeout(() => {
        miniappCopied.value = false;
    }, 1500);
}

const botfatherUrl = 'https://t.me/BotFather';

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.bot.headTitle')" />

    <div class="mx-auto w-full max-w-3xl p-3 sm:p-4 md:p-6">
        <div class="mb-6 flex items-start gap-3 sm:mb-8 sm:gap-4">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-500/10"
            >
                <Bot class="h-5 w-5 text-sky-500" />
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-bold tracking-tight sm:text-2xl">
                    {{ t('pageDealer.bot.title') }}
                </h1>
                <p class="text-xs text-muted-foreground sm:text-sm">
                    {{ t('pageDealer.bot.subtitle') }}
                </p>
            </div>
            <Badge
                :variant="d.is_active ? 'default' : 'destructive'"
                class="shrink-0"
            >
                {{
                    d.is_active
                        ? t('pageDealer.bot.active')
                        : t('pageDealer.bot.inactive')
                }}
            </Badge>
        </div>

        <form id="bot-settings-form" @submit.prevent="submit" class="space-y-6">
            <Card>
                <CardHeader class="pb-4">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-500/10"
                        >
                            <Bot class="h-4 w-4 text-sky-500" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <CardTitle class="text-base">{{
                                t('pageDealer.bot.infoCardTitle')
                            }}</CardTitle>
                            <CardDescription class="break-words">
                                {{ t('pageDealer.bot.currentBot') }}
                                <span
                                    v-if="d.bot_username"
                                    class="font-mono break-all"
                                    >@{{ d.bot_username }}</span
                                >
                                <span
                                    v-else
                                    class="text-muted-foreground italic"
                                    >{{ t('pageDealer.bot.notSet') }}</span
                                >
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <Label for="bot_token" class="flex-wrap">
                            <span>{{ t('pageDealer.bot.newToken') }}</span>
                            <span
                                class="text-xs font-normal text-muted-foreground"
                            >
                                {{ t('pageDealer.bot.tokenHint') }}
                            </span>
                        </Label>
                        <div class="relative mt-1.5">
                            <Input
                                id="bot_token"
                                v-model="form.bot_token"
                                placeholder="1234567890:AAEhB..."
                                class="pr-28 font-mono"
                            />
                            <div
                                class="absolute inset-y-0 right-2 flex items-center"
                            >
                                <Spinner
                                    v-if="tokenStatus === 'checking'"
                                    class="h-4 w-4"
                                />
                                <Badge
                                    v-else-if="tokenStatus === 'valid'"
                                    variant="default"
                                    class="text-xs"
                                >
                                    @{{ detectedUsername }}
                                </Badge>
                                <Badge
                                    v-else-if="tokenStatus === 'taken'"
                                    variant="destructive"
                                    class="text-xs"
                                >
                                    {{ t('pageDealer.bot.tokenTaken') }}
                                </Badge>
                                <Badge
                                    v-else-if="tokenStatus === 'invalid'"
                                    variant="destructive"
                                    class="text-xs"
                                >
                                    {{ t('pageDealer.bot.tokenInvalid') }}
                                </Badge>
                            </div>
                        </div>
                        <p
                            v-if="tokenStatus === 'taken'"
                            class="mt-1.5 text-xs text-destructive"
                        >
                            {{
                                t('pageDealer.bot.tokenTakenHint', {
                                    name: takenByDealer,
                                })
                            }}
                        </p>
                        <p v-else class="mt-1.5 text-xs text-muted-foreground">
                            {{ t('pageDealer.bot.tokenWebhookReset') }}
                        </p>
                        <InputError :message="errors.bot_token" />

                        <!-- Token qanday olinadi — bosqichma-bosqich -->
                        <div
                            class="mt-3 rounded-md border border-sky-500/20 bg-sky-500/5 p-3"
                        >
                            <p
                                class="mb-2 flex items-center gap-1.5 text-sm font-medium"
                            >
                                <Sparkles class="h-3.5 w-3.5 text-sky-500" />
                                {{ t('pageDealer.bot.tokenGuideTitle') }}
                            </p>
                            <ol
                                class="list-decimal space-y-1.5 pl-5 text-xs text-muted-foreground"
                            >
                                <li>
                                    {{
                                        t(
                                            'pageDealer.bot.tokenGuideStep1Prefix',
                                        )
                                    }}
                                    <a
                                        href="https://t.me/BotFather"
                                        target="_blank"
                                        class="font-medium underline underline-offset-2 hover:text-foreground"
                                        >@BotFather</a
                                    >
                                    {{ t('pageDealer.bot.tokenGuideStep1Mid') }}
                                    <code class="rounded bg-muted px-1"
                                        >Start</code
                                    >
                                    {{
                                        t(
                                            'pageDealer.bot.tokenGuideStep1Suffix',
                                        )
                                    }}
                                </li>
                                <li>
                                    <code class="rounded bg-muted px-1"
                                        >/newbot</code
                                    >
                                    {{
                                        t(
                                            'pageDealer.bot.tokenGuideStep2Suffix',
                                        )
                                    }}
                                </li>
                                <li>
                                    {{
                                        t(
                                            'pageDealer.bot.tokenGuideStep3Prefix',
                                        )
                                    }}
                                    <span class="font-medium">{{
                                        t('pageDealer.bot.tokenGuideStep3Name')
                                    }}</span>
                                    {{
                                        t(
                                            'pageDealer.bot.tokenGuideStep3Suffix',
                                        )
                                    }}
                                </li>
                                <li>
                                    {{
                                        t(
                                            'pageDealer.bot.tokenGuideStep4Prefix',
                                        )
                                    }}
                                    <span class="font-medium">{{
                                        t(
                                            'pageDealer.bot.tokenGuideStep4Username',
                                        )
                                    }}</span>
                                    {{ t('pageDealer.bot.tokenGuideStep4Mid') }}
                                    <code class="rounded bg-muted px-1"
                                        >_bot</code
                                    >
                                    {{
                                        t(
                                            'pageDealer.bot.tokenGuideStep4Suffix',
                                        )
                                    }}
                                    <code class="rounded bg-muted px-1"
                                        >olma_savdo_bot</code
                                    >)
                                </li>
                                <li>
                                    {{
                                        t(
                                            'pageDealer.bot.tokenGuideStep5Prefix',
                                        )
                                    }}
                                    <span class="font-medium">{{
                                        t('pageDealer.bot.tokenGuideStep5Token')
                                    }}</span>
                                    {{
                                        t(
                                            'pageDealer.bot.tokenGuideStep5Suffix',
                                        )
                                    }}
                                </li>
                            </ol>
                        </div>
                    </div>

                    <!-- Bildirishnomalar — chat ID ni qo'lda emas, tugma orqali ulaymiz -->
                    <div>
                        <Label class="flex-wrap">
                            <MessageSquare
                                class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                            />
                            <span>{{ t('pageDealer.bot.notifications') }}</span>
                        </Label>

                        <!-- Ulangan holat -->
                        <div
                            v-if="d.telegram_chat_id"
                            class="mt-1.5 flex items-center justify-between gap-3 rounded-md border border-emerald-500/30 bg-emerald-500/5 p-3"
                        >
                            <div class="flex items-center gap-2 text-sm">
                                <Check
                                    class="h-4 w-4 shrink-0 text-emerald-600"
                                />
                                <span class="font-medium">{{
                                    t('pageDealer.bot.connected')
                                }}</span>
                                <code class="rounded bg-muted px-1 text-xs">{{
                                    d.telegram_chat_id
                                }}</code>
                            </div>
                            <Button
                                v-if="notifyConnectUrl"
                                as-child
                                size="sm"
                                variant="outline"
                                class="shrink-0"
                            >
                                <a
                                    :href="notifyConnectUrl"
                                    target="_blank"
                                    rel="noopener"
                                    >{{ t('pageDealer.bot.reconnect') }}</a
                                >
                            </Button>
                        </div>

                        <!-- Bot ulangan, lekin chat hali yo'q — tugma orqali ulash -->
                        <div
                            v-else-if="notifyConnectUrl"
                            class="mt-1.5 space-y-2"
                        >
                            <Button as-child class="w-full sm:w-auto">
                                <a
                                    :href="notifyConnectUrl"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <MessageSquare class="mr-2 h-4 w-4" />
                                    {{ t('pageDealer.bot.openBotToConnect') }}
                                </a>
                            </Button>
                            <p class="text-xs text-muted-foreground">
                                {{ t('pageDealer.bot.connectHintPrefix') }}
                                <code class="rounded bg-muted px-1">Start</code>
                                {{ t('pageDealer.bot.connectHintSuffix') }}
                            </p>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="refreshPage"
                            >
                                <RefreshCcw class="mr-2 h-4 w-4" />
                                {{ t('pageDealer.bot.refreshStatus') }}
                            </Button>
                        </div>

                        <!-- Token hali o'rnatilmagan -->
                        <p v-else class="mt-1.5 text-xs text-muted-foreground">
                            {{ t('pageDealer.bot.saveTokenFirst') }}
                        </p>

                        <!-- Qo'lda kiritish (ixtiyoriy, ilg'or foydalanuvchilar uchun) -->
                        <button
                            type="button"
                            class="mt-2 text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground"
                            @click="showManualChat = !showManualChat"
                        >
                            {{ t('pageDealer.bot.enterChatIdManually') }}
                        </button>
                        <div v-if="showManualChat" class="mt-1.5">
                            <Input
                                id="chat_id"
                                type="number"
                                v-model.number="form.telegram_chat_id"
                                :placeholder="
                                    t('pageDealer.bot.chatIdPlaceholder')
                                "
                            />
                            <InputError :message="errors.telegram_chat_id" />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </form>

        <!-- Mini App URL — BotFather'da "Open App" tugmasini sozlash uchun -->
        <Card class="mt-6">
            <CardHeader class="pb-4">
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-500/10"
                    >
                        <ExternalLink class="h-4 w-4 text-indigo-500" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <CardTitle class="text-base">{{
                            t('pageDealer.bot.miniappCardTitle')
                        }}</CardTitle>
                        <CardDescription>
                            {{ t('pageDealer.bot.miniappCardDesc') }}
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div>
                    <Label class="flex items-center gap-1.5">
                        <LinkIcon
                            class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                        />
                        {{ t('pageDealer.bot.miniappUrl') }}
                    </Label>
                    <div
                        class="mt-1 flex items-center gap-2 rounded-md border bg-muted/30 px-3 py-2"
                    >
                        <code class="min-w-0 flex-1 truncate text-xs">{{
                            miniappUrl
                        }}</code>
                        <Button
                            size="icon"
                            variant="ghost"
                            type="button"
                            class="h-7 w-7 shrink-0"
                            @click="copyMiniappUrl"
                        >
                            <Check
                                v-if="miniappCopied"
                                class="h-3.5 w-3.5 text-emerald-600"
                            />
                            <Copy v-else class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>

                <div class="rounded-md border bg-muted/20 p-3 text-sm">
                    <p class="mb-2 font-medium">
                        {{ t('pageDealer.bot.botfatherSetupTitle') }}
                    </p>
                    <ol
                        class="list-decimal space-y-1 pl-5 text-xs text-muted-foreground"
                    >
                        <li>
                            <a
                                :href="botfatherUrl"
                                target="_blank"
                                class="underline underline-offset-2 hover:text-foreground"
                                >@BotFather</a
                            >
                            {{ t('pageDealer.bot.botfatherStep1Suffix') }}
                        </li>
                        <li>
                            <code class="rounded bg-muted px-1">/mybots</code> →
                            bot →
                            <span class="font-medium">Bot Settings</span> →
                            <span class="font-medium">Configure Mini App</span>
                        </li>
                        <li>
                            <span class="font-medium">Edit Mini App URL</span>
                            {{ t('pageDealer.bot.botfatherStep3Suffix') }}
                        </li>
                    </ol>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ t('pageDealer.bot.botfatherNote') }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Webhook boshqaruvi -->
        <Card class="mt-6">
            <CardHeader class="pb-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10"
                        >
                            <Webhook class="h-4 w-4 text-emerald-500" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <CardTitle class="text-base">{{
                                t('pageDealer.bot.webhookCardTitle')
                            }}</CardTitle>
                            <CardDescription>{{
                                t('pageDealer.bot.webhookCardDesc')
                            }}</CardDescription>
                        </div>
                    </div>
                    <Badge :variant="webhookStatusVariant" class="shrink-0">{{
                        webhookStatusLabel
                    }}</Badge>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div>
                    <Label class="flex items-center gap-1.5">
                        <LinkIcon
                            class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                        />
                        {{ t('pageDealer.bot.expectedUrl') }}
                    </Label>
                    <div
                        class="mt-1 flex items-center gap-2 rounded-md border bg-muted/30 px-3 py-2"
                    >
                        <code class="min-w-0 flex-1 truncate text-xs">{{
                            webhook.expected_url
                        }}</code>
                        <Button
                            size="icon"
                            variant="ghost"
                            type="button"
                            class="h-7 w-7 shrink-0"
                            @click="copyUrl"
                        >
                            <Check
                                v-if="copied"
                                class="h-3.5 w-3.5 text-emerald-600"
                            />
                            <Copy v-else class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>

                <div class="rounded-md border p-3 text-sm">
                    <div
                        v-if="webhook.telegram"
                        class="space-y-2 text-xs sm:text-sm"
                    >
                        <div
                            class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                        >
                            <span class="text-muted-foreground">{{
                                t('pageDealer.bot.telegramUrl')
                            }}</span>
                            <code
                                class="min-w-0 truncate text-xs sm:max-w-[60%]"
                                >{{
                                    webhook.telegram.url ||
                                    t('pageDealer.bot.telegramEmpty')
                                }}</code
                            >
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">{{
                                t('pageDealer.bot.pendingUpdates')
                            }}</span>
                            <span class="font-mono">{{
                                webhook.telegram.pending_update_count
                            }}</span>
                        </div>
                        <div
                            class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <span class="text-muted-foreground">{{
                                t('pageDealer.bot.lastSet')
                            }}</span>
                            <span>{{ formatDateTime(webhook.set_at) }}</span>
                        </div>
                        <div
                            v-if="webhook.telegram.last_error_message"
                            class="rounded-md border border-destructive/30 bg-destructive/5 p-2"
                        >
                            <p class="text-xs font-medium text-destructive">
                                {{ t('pageDealer.bot.errorTitle') }}
                            </p>
                            <p class="mt-1 text-xs">
                                {{ webhook.telegram.last_error_message }}
                            </p>
                            <p
                                v-if="webhook.telegram.last_error_date"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{
                                    formatUnix(webhook.telegram.last_error_date)
                                }}
                            </p>
                        </div>
                    </div>
                    <div v-else class="text-sm text-muted-foreground">
                        {{ t('pageDealer.bot.telegramFetchError') }}
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        @click="setWebhook"
                        :disabled="webhookProcessing"
                    >
                        <Spinner v-if="webhookProcessing" class="mr-2" />
                        <Webhook v-else class="mr-2 h-4 w-4" />
                        {{
                            webhook.telegram?.url
                                ? t('pageDealer.bot.webhookSetAgain')
                                : t('pageDealer.bot.webhookSet')
                        }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="refreshWebhook"
                    >
                        <RefreshCcw class="mr-2 h-4 w-4" />
                        {{ t('pageDealer.bot.refreshStatus') }}
                    </Button>
                    <Button
                        v-if="webhook.telegram?.url"
                        type="button"
                        variant="outline"
                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                        @click="removeWebhook"
                        :disabled="webhookProcessing"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        {{ t('pageDealer.bot.webhookDelete') }}
                    </Button>
                </div>

                <p
                    v-if="!d.is_active"
                    class="text-xs text-amber-600 dark:text-amber-400"
                >
                    {{ t('pageDealer.bot.dealerInactive') }}
                </p>
            </CardContent>
        </Card>

        <!-- Float saqlash tugmasi (FAB) — viewport'ga fixed, scroll'dan qat'i nazar
             doim ko'rinadi. Mobil bottom nav ustida. Forma'ga `form` orqali bog'langan. -->
        <Button
            type="submit"
            form="bot-settings-form"
            size="lg"
            :disabled="
                processing ||
                tokenStatus === 'invalid' ||
                tokenStatus === 'taken' ||
                tokenStatus === 'checking'
            "
            class="fixed right-4 bottom-20 z-50 shadow-xl shadow-primary/20 md:right-8 md:bottom-8"
        >
            <Spinner v-if="processing" class="mr-2" />
            {{
                processing
                    ? t('pageDealer.bot.saving')
                    : t('pageDealer.bot.save')
            }}
        </Button>
    </div>
</template>
