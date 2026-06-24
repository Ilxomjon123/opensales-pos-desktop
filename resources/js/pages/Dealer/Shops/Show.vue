<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    Check,
    Copy,
    Hash,
    ImagePlus,
    Link as LinkIcon,
    Loader2,
    MapPin,
    MapPinned,
    Pencil,
    Phone,
    Store,
    Trash2,
    Truck,
    User,
    Users,
} from 'lucide-vue-next';
import { computed, defineAsyncComponent, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import MapLinkButton from '@/components/dealer/MapLinkButton.vue';
import VisitModal from '@/components/dealer/VisitModal.vue';
import ImageLightbox from '@/components/ImageLightbox.vue';
// Leaflet `window` ga tayanadi → SSR'da yiqiladi. Faqat klientda lazy yuklanadi.
const LocationPicker = defineAsyncComponent(
    () => import('@/components/LocationPicker.vue'),
);
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { confirm } from '@/composables/useConfirm';
import { useCurrency } from '@/composables/useCurrency';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatDateTime } from '@/lib/date';

type Member = {
    id: number;
    telegram_id: number | null;
    name: string | null;
    username: string | null;
    phone: string | null;
    channel: 'telegram' | 'mobile' | 'both';
    is_active: boolean;
    joined_at: string | null;
};

const { t } = useI18n();
const { symbol } = useCurrency();

function channelLabel(channel: Member['channel']): string {
    return {
        telegram: t('pageDealer.shopsShow.channelBot'),
        mobile: t('pageDealer.shopsShow.channelMobile'),
        both: t('pageDealer.shopsShow.channelBoth'),
    }[channel];
}

type Invite = {
    id: number;
    token: string;
    link: string | null;
    bot_username: string | null;
    expires_at: string;
    is_valid: boolean;
};

type Branch = {
    id: number;
    name: string;
    phone: string | null;
    region: string | null;
    district: string | null;
    balance: number;
    is_active: boolean;
};

type Shop = {
    id: number;
    name: string;
    legal_name: string | null;
    phone: string;
    contact_person: string | null;
    address: string | null;
    landmark: string | null;
    region: string | null;
    district: string | null;
    inn: string | null;
    photo_url: string | null;
    latitude: number | null;
    longitude: number | null;
    map_provider: 'yandex' | 'google' | 'osm';
    balance: number;
    pending_total?: number;
    is_active: boolean;
    deliveryman: { id: number; name: string } | null;
    parent_shop_id: number | null;
    is_main_branch: boolean;
    parent: { id: number; name: string } | null;
    branches?: { data: Branch[] };
    branches_balance_sum?: number;
    total_balance_with_branches?: number;
};

type Visit = {
    id: number;
    note: string | null;
    visited_at: string | null;
    user: { id: number; name: string } | null;
    can_modify: boolean;
};

const props = defineProps<{
    shop: { data: Shop };
    members: { data: Member[] };
    visits: { data: Visit[] };
    activeInvite: { data: Invite } | null;
    canEdit: boolean;
    canInvite: boolean;
    canUpdatePhoto: boolean;
    canRecordVisit: boolean;
}>();

const s = computed(() => props.shop.data);
const copied = ref(false);
const photoOpen = ref(false);
const photoInput = ref<HTMLInputElement | null>(null);
const photoUploading = ref(false);
const visitOpen = ref(false);
const editingVisit = ref<Visit | null>(null);

function openCreateVisit() {
    editingVisit.value = null;
    visitOpen.value = true;
}

function openEditVisit(v: Visit) {
    editingVisit.value = v;
    visitOpen.value = true;
}

function onVisitSubmitted() {
    editingVisit.value = null;
    router.reload({ only: ['visits', 'shop'] });
}

// 4 soatlik tahrirlash oynasi — backend ham bir xil tekshiradi
function canModifyVisit(v: Visit): boolean {
    if (!v.can_modify || !v.visited_at) {
        return false;
    }

    return Date.now() - new Date(v.visited_at).getTime() < 4 * 3_600_000;
}

async function deleteVisit(v: Visit) {
    const ok = await confirm({
        title: t('pageDealer.shopsShow.deleteVisitTitle'),
        description: t('pageDealer.shopsShow.deleteVisitDesc'),
        confirmText: t('pageDealer.shopsShow.delete'),
        variant: 'destructive',
    });

    if (!ok) {
        return;
    }

    router.delete(`/dealer/shops/${s.value.id}/visits/${v.id}`, {
        preserveScroll: true,
        only: ['visits', 'shop'],
    });
}

function copy(text: string) {
    navigator.clipboard.writeText(text);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}

function shareTelegram(link: string) {
    const text = encodeURIComponent(
        t('pageDealer.shopsShow.shareTelegramText', { name: s.value.name }),
    );
    window.open(
        `https://t.me/share/url?url=${encodeURIComponent(link)}&text=${text}`,
        '_blank',
    );
}

function generate() {
    router.post(
        `/dealer/shops/${s.value.id}/invite`,
        {},
        { preserveScroll: true },
    );
}

async function del() {
    const ok = await confirm({
        title: t('pageDealer.shopsShow.deleteShopTitle'),
        description: t('pageDealer.shopsShow.deleteShopDesc', {
            name: s.value.name,
        }),
        confirmText: t('pageDealer.shopsShow.delete'),
        variant: 'destructive',
    });

    if (!ok) {
        return;
    }

    router.delete(`/dealer/shops/${s.value.id}`);
}

function pickPhoto() {
    photoInput.value?.click();
}

function onPhotoSelected(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    const data = new FormData();
    data.append('photo', file);

    photoUploading.value = true;
    router.post(`/dealer/shops/${s.value.id}/photo`, data, {
        preserveScroll: true,
        forceFormData: true,
        only: ['shop'],
        onFinish: () => {
            photoUploading.value = false;

            if (photoInput.value) {
                photoInput.value.value = '';
            }
        },
    });
}

async function deletePhoto() {
    const ok = await confirm({
        title: t('pageDealer.shopsShow.deletePhotoTitle'),
        description: t('pageDealer.shopsShow.deletePhotoDesc'),
        confirmText: t('pageDealer.shopsShow.delete'),
        variant: 'destructive',
    });

    if (!ok) {
        return;
    }

    photoUploading.value = true;
    router.delete(`/dealer/shops/${s.value.id}/photo`, {
        preserveScroll: true,
        only: ['shop'],
        onFinish: () => {
            photoUploading.value = false;
        },
    });
}

function formatMoney(n: number): string {
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function qrSrc(link: string): string {
    return `https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=10&data=${encodeURIComponent(link)}`;
}

const hasLocation = computed(
    () => s.value.latitude !== null && s.value.longitude !== null,
);

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="s.name" />

    <div
        class="mx-auto w-full max-w-6xl space-y-4 overflow-x-hidden p-4 sm:space-y-6 md:p-8"
    >
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex items-center gap-3">
                <Button
                    variant="ghost"
                    size="icon"
                    class="shrink-0"
                    @click="router.get('/dealer/shops')"
                >
                    <ArrowLeft class="h-5 w-5" />
                </Button>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                        <h1
                            class="text-xl font-bold tracking-tight sm:text-2xl"
                        >
                            {{ s.name }}
                        </h1>
                        <Badge :variant="s.is_active ? 'default' : 'outline'">
                            {{
                                s.is_active
                                    ? t('pageDealer.shopsShow.active')
                                    : t('pageDealer.shopsShow.inactive')
                            }}
                        </Badge>
                    </div>
                    <p
                        v-if="s.legal_name"
                        class="text-sm text-muted-foreground"
                    >
                        {{ s.legal_name }}
                    </p>
                </div>
            </div>
            <div
                v-if="canEdit || canRecordVisit"
                class="flex flex-wrap gap-2 sm:ml-auto"
            >
                <Button
                    v-if="canRecordVisit"
                    class="flex-1 sm:flex-initial"
                    @click="openCreateVisit"
                >
                    <MapPinned class="mr-2 h-4 w-4" />
                    {{ t('pageDealer.shopsShow.recordVisit') }}
                </Button>
                <Button
                    v-if="canEdit"
                    variant="outline"
                    class="flex-1 sm:flex-initial"
                    @click="router.get(`/dealer/shops/${s.id}/edit`)"
                >
                    <Pencil class="mr-2 h-4 w-4" />
                    {{ t('pageDealer.shopsShow.edit') }}
                </Button>
                <Button
                    v-if="canEdit"
                    variant="outline"
                    class="flex-1 text-destructive hover:bg-destructive/10 hover:text-destructive sm:flex-initial"
                    @click="del"
                >
                    <Trash2 class="mr-2 h-4 w-4" />
                    {{ t('pageDealer.shopsShow.delete') }}
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Chap — ma'lumotlar -->
            <div class="order-2 space-y-6 lg:order-1 lg:col-span-2">
                <!-- Rasm + asosiy -->
                <Card>
                    <CardContent class="p-6">
                        <div class="flex flex-row items-start gap-4 sm:gap-5">
                            <div
                                class="group relative h-24 w-24 flex-shrink-0 overflow-hidden rounded-lg border bg-muted sm:h-40 sm:w-40"
                            >
                                <img
                                    v-if="s.photo_url"
                                    :src="s.photo_url"
                                    :alt="s.name"
                                    class="h-full w-full cursor-zoom-in object-cover"
                                    @click="photoOpen = true"
                                />
                                <button
                                    v-else
                                    type="button"
                                    class="flex h-full w-full items-center justify-center"
                                    :class="
                                        canUpdatePhoto
                                            ? 'cursor-pointer hover:bg-muted/70'
                                            : ''
                                    "
                                    :disabled="
                                        !canUpdatePhoto || photoUploading
                                    "
                                    @click="pickPhoto"
                                >
                                    <Store
                                        class="h-12 w-12 text-muted-foreground/30"
                                    />
                                </button>

                                <div
                                    v-if="canUpdatePhoto"
                                    class="pointer-events-none absolute inset-0 flex items-end justify-center gap-1.5 bg-gradient-to-t from-black/70 via-black/20 to-transparent p-2 opacity-0 transition-opacity group-hover:opacity-100"
                                    :class="photoUploading ? 'opacity-100' : ''"
                                >
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="secondary"
                                        class="pointer-events-auto h-7 px-2 text-xs"
                                        :disabled="photoUploading"
                                        @click.stop="pickPhoto"
                                    >
                                        <Loader2
                                            v-if="photoUploading"
                                            class="mr-1 h-3 w-3 animate-spin"
                                        />
                                        <ImagePlus
                                            v-else
                                            class="mr-1 h-3 w-3"
                                        />
                                        {{
                                            s.photo_url
                                                ? t(
                                                      'pageDealer.shopsShow.changePhoto',
                                                  )
                                                : t(
                                                      'pageDealer.shopsShow.uploadPhoto',
                                                  )
                                        }}
                                    </Button>
                                    <Button
                                        v-if="s.photo_url"
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        class="pointer-events-auto h-7 px-2 text-xs"
                                        :disabled="photoUploading"
                                        @click.stop="deletePhoto"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                    </Button>
                                </div>

                                <input
                                    ref="photoInput"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="onPhotoSelected"
                                />
                            </div>
                            <div class="w-full min-w-0 flex-1 space-y-3">
                                <div
                                    class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2"
                                >
                                    <div>
                                        <p
                                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                        >
                                            <Phone class="h-3 w-3" />
                                            {{
                                                t('pageDealer.shopsShow.phone')
                                            }}
                                        </p>
                                        <p class="font-medium">{{ s.phone }}</p>
                                    </div>
                                    <div v-if="s.contact_person">
                                        <p
                                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                        >
                                            <User class="h-3 w-3" />
                                            {{
                                                t(
                                                    'pageDealer.shopsShow.contactPerson',
                                                )
                                            }}
                                        </p>
                                        <p class="font-medium">
                                            {{ s.contact_person }}
                                        </p>
                                    </div>
                                    <div v-if="s.inn">
                                        <p
                                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                        >
                                            <Hash class="h-3 w-3" />
                                            {{ t('pageDealer.shopsShow.inn') }}
                                        </p>
                                        <p class="font-mono font-medium">
                                            {{ s.inn }}
                                        </p>
                                    </div>
                                    <div v-if="s.region">
                                        <p
                                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                        >
                                            <Building2 class="h-3 w-3" />
                                            {{
                                                t('pageDealer.shopsShow.region')
                                            }}
                                        </p>
                                        <p class="font-medium">
                                            {{ s.region }}
                                        </p>
                                    </div>
                                    <div v-if="s.district">
                                        <p
                                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                        >
                                            <MapPin class="h-3 w-3" />
                                            {{
                                                t(
                                                    'pageDealer.shopsShow.district',
                                                )
                                            }}
                                        </p>
                                        <p class="font-medium">
                                            {{ s.district }}
                                        </p>
                                    </div>
                                    <div v-if="s.address">
                                        <p
                                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                        >
                                            <MapPin class="h-3 w-3" />
                                            {{
                                                t(
                                                    'pageDealer.shopsShow.address',
                                                )
                                            }}
                                        </p>
                                        <p class="font-medium">
                                            {{ s.address }}
                                        </p>
                                    </div>
                                    <div v-if="s.landmark">
                                        <p
                                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                        >
                                            {{
                                                t(
                                                    'pageDealer.shopsShow.landmark',
                                                )
                                            }}
                                        </p>
                                        <p class="font-medium">
                                            {{ s.landmark }}
                                        </p>
                                    </div>
                                    <div v-if="s.deliveryman">
                                        <p
                                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                        >
                                            <Truck class="h-3 w-3" />
                                            {{
                                                t(
                                                    'pageDealer.shopsShow.deliveryman',
                                                )
                                            }}
                                        </p>
                                        <p class="font-medium">
                                            {{ s.deliveryman.name }}
                                        </p>
                                    </div>
                                    <div v-if="s.parent">
                                        <p
                                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                        >
                                            <Store class="h-3 w-3" />
                                            {{
                                                t(
                                                    'pageDealer.shopsShow.mainBranch',
                                                )
                                            }}
                                        </p>
                                        <button
                                            type="button"
                                            class="font-medium text-primary hover:underline"
                                            @click="
                                                router.get(
                                                    `/dealer/shops/${s.parent.id}`,
                                                )
                                            "
                                        >
                                            {{ s.parent.name }}
                                        </button>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                t(
                                                    'pageDealer.shopsShow.balance',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="font-semibold"
                                            :class="
                                                s.balance < 0
                                                    ? 'text-amber-600'
                                                    : ''
                                            "
                                        >
                                            {{ formatMoney(s.balance) }}
                                            {{ symbol }}
                                        </p>
                                        <p
                                            v-if="
                                                s.is_main_branch &&
                                                (s.branches_balance_sum ??
                                                    0) !== 0
                                            "
                                            class="mt-0.5 text-xs"
                                            :class="
                                                (s.total_balance_with_branches ??
                                                    s.balance) < 0
                                                    ? 'text-amber-600'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            {{
                                                t(
                                                    'pageDealer.shopsShow.withBranches',
                                                )
                                            }}:
                                            {{
                                                formatMoney(
                                                    s.total_balance_with_branches ??
                                                        s.balance,
                                                )
                                            }}
                                            {{ symbol }}
                                        </p>
                                        <p
                                            v-if="(s.pending_total ?? 0) > 0"
                                            class="mt-0.5 text-xs text-sky-600"
                                        >
                                            +{{
                                                formatMoney(
                                                    s.pending_total ?? 0,
                                                )
                                            }}
                                            {{ symbol }}
                                            {{
                                                t(
                                                    'pageDealer.shopsShow.pending',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Filiallar -->
                <Card v-if="s.is_main_branch && s.branches?.data?.length">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Store class="h-4 w-4 text-primary" />
                            {{
                                t('pageDealer.shopsShow.branches', {
                                    n: s.branches.data.length,
                                })
                            }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b bg-muted/40">
                                <tr>
                                    <th class="px-4 py-2 font-medium">
                                        {{ t('pageDealer.shopsShow.colName') }}
                                    </th>
                                    <th class="px-4 py-2 font-medium">
                                        {{ t('pageDealer.shopsShow.phone') }}
                                    </th>
                                    <th class="px-4 py-2 font-medium">
                                        {{
                                            t('pageDealer.shopsShow.colRegion')
                                        }}
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        {{ t('pageDealer.shopsShow.balance') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="b in s.branches.data"
                                    :key="b.id"
                                    class="cursor-pointer hover:bg-muted/20"
                                    @click="router.get(`/dealer/shops/${b.id}`)"
                                >
                                    <td class="px-4 py-2 font-medium">
                                        {{ b.name }}
                                    </td>
                                    <td class="px-4 py-2 text-muted-foreground">
                                        {{ b.phone ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-muted-foreground">
                                        {{
                                            [b.region, b.district]
                                                .filter(Boolean)
                                                .join(', ') || '—'
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-2 text-right font-mono font-semibold"
                                        :class="
                                            b.balance < 0
                                                ? 'text-destructive'
                                                : ''
                                        "
                                    >
                                        {{ formatMoney(b.balance) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t bg-muted/20">
                                <tr>
                                    <td
                                        colspan="3"
                                        class="px-4 py-2 text-right text-xs text-muted-foreground"
                                    >
                                        {{
                                            t(
                                                'pageDealer.shopsShow.mainPlusBranchesTotal',
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-2 text-right font-mono font-bold"
                                        :class="
                                            (s.total_balance_with_branches ??
                                                s.balance) < 0
                                                ? 'text-destructive'
                                                : ''
                                        "
                                    >
                                        {{
                                            formatMoney(
                                                s.total_balance_with_branches ??
                                                    s.balance,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </CardContent>
                </Card>

                <!-- Xarita -->
                <Card v-if="hasLocation">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <MapPin class="h-4 w-4 text-primary" />
                            {{ t('pageDealer.shopsShow.mapLocation') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 p-4">
                        <LocationPicker
                            :latitude="s.latitude"
                            :longitude="s.longitude"
                            :provider="s.map_provider"
                            readonly
                            height="h-72"
                        />
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 text-xs"
                        >
                            <span class="font-mono text-muted-foreground"
                                >{{ s.latitude }}, {{ s.longitude }}</span
                            >
                            <MapLinkButton
                                :shop="{
                                    latitude: s.latitude,
                                    longitude: s.longitude,
                                    address: s.address,
                                }"
                                variant="button"
                                :label="
                                    t('pageDealer.shopsShow.openInLargeMap')
                                "
                            />
                        </div>
                    </CardContent>
                </Card>

                <!-- Mijoz vakililar -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Users class="h-4 w-4 text-primary" />
                            {{
                                t('pageDealer.shopsShow.members', {
                                    n: props.members.data.length,
                                })
                            }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="props.members.data.length > 0"
                            class="space-y-2"
                        >
                            <div
                                v-for="m in props.members.data"
                                :key="m.id"
                                class="flex items-center justify-between rounded-lg border p-3"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <div
                                        class="shrink-0 rounded-full bg-primary/10 p-2 text-primary"
                                    >
                                        <Store class="h-4 w-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">
                                            {{
                                                m.name ??
                                                m.phone ??
                                                t(
                                                    'pageDealer.shopsShow.memberFallback',
                                                    { id: m.id },
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="truncate text-xs text-muted-foreground"
                                        >
                                            <span v-if="m.username"
                                                >@{{ m.username }} ·
                                            </span>
                                            <span v-if="m.phone && m.name"
                                                >{{ m.phone }} ·
                                            </span>
                                            <span>{{
                                                channelLabel(m.channel)
                                            }}</span>
                                            <span v-if="m.joined_at">
                                                ·
                                                {{
                                                    formatDate(m.joined_at)
                                                }}</span
                                            >
                                        </p>
                                    </div>
                                </div>
                                <Badge
                                    :variant="
                                        m.is_active ? 'default' : 'outline'
                                    "
                                    class="text-xs"
                                >
                                    {{
                                        m.is_active
                                            ? t('pageDealer.shopsShow.active')
                                            : t('pageDealer.shopsShow.inactive')
                                    }}
                                </Badge>
                            </div>
                        </div>
                        <div
                            v-else
                            class="rounded-lg border border-dashed py-8 text-center text-sm text-muted-foreground"
                        >
                            {{ t('pageDealer.shopsShow.noMembers') }}
                        </div>
                    </CardContent>
                </Card>

                <!-- Vizitlar tarixi -->
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between gap-2 space-y-0"
                    >
                        <CardTitle class="flex items-center gap-2 text-base">
                            <MapPinned class="h-4 w-4 text-primary" />
                            {{
                                t('pageDealer.shopsShow.visitHistory', {
                                    n: props.visits.data.length,
                                })
                            }}
                        </CardTitle>
                        <Button
                            v-if="canRecordVisit"
                            size="sm"
                            variant="outline"
                            @click="openCreateVisit"
                        >
                            <MapPinned class="mr-1.5 h-3.5 w-3.5" />
                            {{ t('pageDealer.shopsShow.recordVisit') }}
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="props.visits.data.length > 0"
                            class="relative space-y-0"
                        >
                            <div
                                v-for="(v, idx) in props.visits.data"
                                :key="v.id"
                                class="relative flex gap-3 pb-4 last:pb-0"
                            >
                                <!-- Timeline chizig'i -->
                                <div class="flex flex-col items-center">
                                    <div
                                        class="z-10 mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-primary ring-4 ring-primary/10"
                                    />
                                    <div
                                        v-if="
                                            idx < props.visits.data.length - 1
                                        "
                                        class="w-px flex-1 bg-border"
                                    />
                                </div>
                                <div class="-mt-0.5 min-w-0 flex-1">
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-x-2 gap-y-0.5"
                                    >
                                        <span class="text-sm font-medium">{{
                                            v.visited_at
                                                ? formatDateTime(v.visited_at)
                                                : '—'
                                        }}</span>
                                        <div class="flex items-center gap-2">
                                            <span
                                                v-if="v.user"
                                                class="flex items-center gap-1 text-xs text-muted-foreground"
                                            >
                                                <User class="h-3 w-3" />
                                                {{ v.user.name }}
                                            </span>
                                            <div
                                                v-if="canModifyVisit(v)"
                                                class="flex items-center"
                                            >
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    class="h-7 w-7 text-muted-foreground"
                                                    :title="
                                                        t(
                                                            'pageDealer.shopsShow.edit',
                                                        )
                                                    "
                                                    @click="openEditVisit(v)"
                                                >
                                                    <Pencil
                                                        class="h-3.5 w-3.5"
                                                    />
                                                </Button>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    class="h-7 w-7 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                    :title="
                                                        t(
                                                            'pageDealer.shopsShow.delete',
                                                        )
                                                    "
                                                    @click="deleteVisit(v)"
                                                >
                                                    <Trash2
                                                        class="h-3.5 w-3.5"
                                                    />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                    <p
                                        v-if="v.note"
                                        class="mt-1 rounded-md bg-muted/40 p-2 text-sm whitespace-pre-line text-muted-foreground"
                                    >
                                        {{ v.note }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="rounded-lg border border-dashed py-8 text-center text-sm text-muted-foreground"
                        >
                            {{ t('pageDealer.shopsShow.noVisits') }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- O'ng — taklif link -->
            <div class="order-1 space-y-6 lg:order-2">
                <Card v-if="canInvite" class="overflow-hidden">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <LinkIcon class="h-4 w-4 text-primary" />
                            {{ t('pageDealer.shopsShow.inviteTitle') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="overflow-hidden">
                        <template
                            v-if="
                                activeInvite &&
                                activeInvite.data.is_valid &&
                                activeInvite.data.link
                            "
                        >
                            <p class="mb-3 text-sm text-muted-foreground">
                                {{ t('pageDealer.shopsShow.inviteActiveHint') }}
                            </p>
                            <div
                                class="flex justify-center rounded-lg border bg-white p-3"
                            >
                                <img
                                    :src="qrSrc(activeInvite.data.link)"
                                    alt="QR"
                                    class="h-44 w-44 sm:h-52 sm:w-52"
                                />
                            </div>
                            <div
                                class="mt-3 flex items-center gap-2 overflow-hidden rounded-md border bg-muted/30 p-2"
                            >
                                <code class="min-w-0 flex-1 truncate text-xs">{{
                                    activeInvite.data.link
                                }}</code>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    class="h-7 w-7 shrink-0"
                                    @click="copy(activeInvite!.data.link!)"
                                >
                                    <Check
                                        v-if="copied"
                                        class="h-3.5 w-3.5 text-emerald-600"
                                    />
                                    <Copy v-else class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <Button
                                    class="w-full sm:flex-1"
                                    @click="
                                        shareTelegram(activeInvite!.data.link!)
                                    "
                                >
                                    {{
                                        t('pageDealer.shopsShow.shareTelegram')
                                    }}
                                </Button>
                                <Button
                                    variant="outline"
                                    class="w-full sm:w-auto"
                                    @click="generate"
                                >
                                    {{ t('pageDealer.shopsShow.newLink') }}
                                </Button>
                            </div>
                            <p
                                class="mt-2 text-center text-xs text-muted-foreground"
                            >
                                {{ t('pageDealer.shopsShow.expiresAt') }}:
                                {{
                                    formatDateTime(activeInvite.data.expires_at)
                                }}
                            </p>
                        </template>
                        <template v-else>
                            <p class="mb-4 text-sm text-muted-foreground">
                                {{ t('pageDealer.shopsShow.inviteEmptyHint') }}
                            </p>
                            <Button class="w-full" @click="generate">
                                <LinkIcon class="mr-2 h-4 w-4" />
                                {{ t('pageDealer.shopsShow.createInviteLink') }}
                            </Button>
                        </template>
                    </CardContent>
                </Card>
            </div>
        </div>

        <ImageLightbox
            v-if="s.photo_url"
            :images="[s.photo_url]"
            :open="photoOpen"
            @close="photoOpen = false"
        />

        <VisitModal
            :open="visitOpen"
            :shop-id="s.id"
            :shop-name="s.name"
            :visit-id="editingVisit?.id ?? null"
            :initial-note="editingVisit?.note ?? ''"
            @update:open="(v) => (visitOpen = v)"
            @submitted="onVisitSubmitted"
        />
    </div>
</template>
