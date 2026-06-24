<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCurrency } from '@/composables/useCurrency';
import { formatDateTime } from '@/lib/date';

type RecentOrder = {
    id: number;
    total: number;
    status: string;
    status_label: string;
    created_at: string | null;
};

type BotUserDetail = {
    id: number;
    telegram_id: number;
    name: string | null;
    username: string | null;
    is_active: boolean;
    blocked_at: string | null;
    joined_at: string | null;
    last_seen_at: string | null;
    orders_count: number;
    shop: {
        id: number;
        name: string;
        is_active: boolean;
        phone: string | null;
        address: string | null;
        region: string | null;
        district: string | null;
        balance: number;
    };
    recent_orders: RecentOrder[];
};

const props = defineProps<{
    open: boolean;
    memberId: number | null;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { t } = useI18n();
const { formatWithSymbol } = useCurrency();

const member = ref<BotUserDetail | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

watch(
    () => [props.open, props.memberId] as const,
    async ([open, id]) => {
        if (!open || !id) {
            return;
        }

        member.value = null;
        error.value = null;
        loading.value = true;

        try {
            const res = await fetch(`/dealer/bot-users/${id}/json`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }

            member.value = await res.json();
        } catch (e) {
            error.value =
                e instanceof Error
                    ? e.message
                    : t('pageDealer.botUsers.detail.loadError');
        } finally {
            loading.value = false;
        }
    },
    { immediate: true },
);

function statusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'delivered' || status === 'confirmed') return 'secondary';
    if (status === 'cancelled') return 'destructive';
    return 'outline';
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent
            class="flex max-h-[calc(100dvh-2rem)] flex-col gap-3 p-4 sm:max-w-2xl sm:gap-4 sm:p-6"
            @open-auto-focus="(e: Event) => e.preventDefault()"
        >
            <DialogHeader>
                <DialogTitle class="pr-8 text-base sm:text-lg">
                    {{ t('pageDealer.botUsers.detail.title') }}
                </DialogTitle>
            </DialogHeader>

            <div class="-mx-4 flex-1 overflow-y-auto px-4 sm:-mx-6 sm:px-6">
                <div
                    v-if="loading"
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    {{ t('pageDealer.botUsers.detail.loading') }}
                </div>

                <p
                    v-else-if="error"
                    class="rounded bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    {{ error }}
                </p>

                <div v-else-if="member" class="space-y-4">
                    <!-- Header: name + status -->
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-semibold">
                            {{
                                member.name || t('pageDealer.botUsers.unnamed')
                            }}
                        </h3>
                        <Badge
                            v-if="member.blocked_at"
                            variant="destructive"
                            :title="
                                t('pageDealer.botUsers.blockedSince', {
                                    date: formatDateTime(member.blocked_at),
                                })
                            "
                        >
                            {{ t('pageDealer.botUsers.statusBlocked') }}
                        </Badge>
                        <Badge
                            v-else
                            :variant="
                                member.is_active ? 'secondary' : 'destructive'
                            "
                        >
                            {{
                                member.is_active
                                    ? t('pageDealer.botUsers.statusActive')
                                    : t('pageDealer.botUsers.statusInactive')
                            }}
                        </Badge>
                    </div>

                    <!-- User fields -->
                    <div
                        class="grid grid-cols-2 gap-3 rounded-lg border bg-muted/20 p-3 text-sm sm:grid-cols-3"
                    >
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('pageDealer.botUsers.detail.username') }}
                            </p>
                            <p class="font-medium">
                                {{
                                    member.username
                                        ? '@' + member.username
                                        : '—'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('pageDealer.botUsers.detail.telegramId') }}
                            </p>
                            <p class="font-mono font-medium">
                                {{ member.telegram_id }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('pageDealer.botUsers.table.orders') }}
                            </p>
                            <p class="font-mono font-medium">
                                {{ member.orders_count }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('pageDealer.botUsers.table.joinedAt') }}
                            </p>
                            <p class="font-medium">
                                {{ formatDateTime(member.joined_at) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('pageDealer.botUsers.table.lastSeen') }}
                            </p>
                            <p class="font-medium">
                                {{ formatDateTime(member.last_seen_at) }}
                            </p>
                        </div>
                    </div>

                    <!-- Shop -->
                    <div class="space-y-2">
                        <h4 class="text-sm font-medium">
                            {{ t('pageDealer.botUsers.detail.shopInfo') }}
                        </h4>
                        <div
                            class="grid grid-cols-2 gap-3 rounded-lg border bg-muted/20 p-3 text-sm sm:grid-cols-3"
                        >
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('pageDealer.botUsers.table.shop') }}
                                </p>
                                <p class="font-medium">
                                    {{ member.shop.name }}
                                </p>
                            </div>
                            <div v-if="member.shop.phone">
                                <p class="text-xs text-muted-foreground">
                                    {{ t('pageDealer.botUsers.detail.phone') }}
                                </p>
                                <p class="font-medium">
                                    {{ member.shop.phone }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        t('pageDealer.botUsers.detail.balance')
                                    }}
                                </p>
                                <p
                                    class="font-mono font-medium"
                                    :class="
                                        member.shop.balance < 0
                                            ? 'text-red-600'
                                            : 'text-green-600'
                                    "
                                >
                                    {{ formatWithSymbol(member.shop.balance) }}
                                </p>
                            </div>
                            <div
                                v-if="
                                    member.shop.region || member.shop.district
                                "
                            >
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        t('pageDealer.botUsers.detail.location')
                                    }}
                                </p>
                                <p class="font-medium">
                                    {{
                                        [
                                            member.shop.region,
                                            member.shop.district,
                                        ]
                                            .filter(Boolean)
                                            .join(', ')
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="member.shop.address"
                                class="col-span-2 sm:col-span-3"
                            >
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        t('pageDealer.botUsers.detail.address')
                                    }}
                                </p>
                                <p class="font-medium">
                                    {{ member.shop.address }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Recent orders -->
                    <div class="space-y-2">
                        <h4 class="text-sm font-medium">
                            {{
                                t('pageDealer.botUsers.detail.recentOrders')
                            }}
                            ({{ member.recent_orders.length }})
                        </h4>
                        <div
                            v-if="member.recent_orders.length > 0"
                            class="overflow-hidden rounded-lg border"
                        >
                            <table class="w-full text-left text-sm">
                                <thead class="border-b bg-muted/40 text-xs">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">#</th>
                                        <th class="px-3 py-2 font-medium">
                                            {{
                                                t(
                                                    'pageDealer.botUsers.detail.orderDate',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="px-3 py-2 text-center font-medium"
                                        >
                                            {{
                                                t(
                                                    'pageDealer.botUsers.table.status',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="px-3 py-2 text-right font-medium"
                                        >
                                            {{
                                                t(
                                                    'pageDealer.botUsers.detail.orderTotal',
                                                )
                                            }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr
                                        v-for="o in member.recent_orders"
                                        :key="o.id"
                                    >
                                        <td class="px-3 py-2 font-mono">
                                            {{ o.id }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-muted-foreground"
                                        >
                                            {{ formatDateTime(o.created_at) }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <Badge
                                                :variant="
                                                    statusVariant(o.status)
                                                "
                                                >{{ o.status_label }}</Badge
                                            >
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right font-mono"
                                        >
                                            {{ formatWithSymbol(o.total) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p
                            v-else
                            class="rounded-lg border bg-muted/20 px-3 py-4 text-center text-sm text-muted-foreground"
                        >
                            {{ t('pageDealer.botUsers.detail.noOrders') }}
                        </p>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
