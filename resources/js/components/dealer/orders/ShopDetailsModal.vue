<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    ExternalLink,
    MapPin,
    Phone,
    User,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCurrency } from '@/composables/useCurrency';
import { formatDateTime } from '@/lib/date';
import type { Shop } from '@/types';

const props = defineProps<{
    open: boolean;
    shop: Shop | null;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { t } = useI18n();
const { formatWithSymbol } = useCurrency();

const locationText = computed(() => {
    const s = props.shop;

    if (!s) {
        return null;
    }

    return [s.region, s.district]
        .filter((v): v is string => Boolean(v && v.trim()))
        .join(', ');
});

// Saldo: manfiy = qarzdor, musbat = oldindan to'lagan.
const balanceColor = computed(() => {
    const b = props.shop?.balance ?? 0;

    if (b < 0) {
        return 'text-rose-600';
    }

    if (b > 0) {
        return 'text-emerald-600';
    }

    return 'text-muted-foreground';
});

function openFull() {
    if (!props.shop) {
        return;
    }

    emit('update:open', false);
    router.get(`/dealer/shops/${props.shop.id}`);
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent
            class="flex max-h-[calc(100dvh-2rem)] flex-col gap-3 p-4 sm:max-w-lg sm:gap-4 sm:p-6"
            @open-auto-focus="(e: Event) => e.preventDefault()"
        >
            <DialogHeader>
                <DialogTitle class="pr-8 text-base sm:text-lg">
                    {{ t('pageDealer.shopDetailsModal.title') }}
                </DialogTitle>
            </DialogHeader>

            <div
                v-if="shop"
                class="-mx-4 flex-1 space-y-4 overflow-y-auto px-4 sm:-mx-6 sm:px-6"
            >
                <!-- Nomi + holat -->
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-semibold">{{ shop.name }}</h3>
                        <Badge :variant="shop.is_active ? 'secondary' : 'destructive'">
                            {{
                                shop.is_active
                                    ? t('pageDealer.shopDetailsModal.active')
                                    : t('pageDealer.shopDetailsModal.inactive')
                            }}
                        </Badge>
                    </div>
                    <p
                        v-if="shop.legal_name"
                        class="flex items-center gap-1.5 text-sm text-muted-foreground"
                    >
                        <Building2 class="h-3.5 w-3.5 shrink-0" />
                        {{ shop.legal_name }}
                    </p>
                </div>

                <!-- Saldo -->
                <div class="rounded-lg border bg-muted/20 px-3 py-2.5">
                    <p class="text-xs text-muted-foreground">
                        {{ t('pageDealer.shopDetailsModal.balance') }}
                    </p>
                    <p
                        class="font-mono text-lg font-bold tabular-nums"
                        :class="balanceColor"
                    >
                        {{ formatWithSymbol(shop.balance) }}
                    </p>
                </div>

                <!-- Ma'lumotlar ro'yxati -->
                <dl class="grid gap-2.5 text-sm">
                    <div v-if="shop.phone" class="flex items-start gap-2">
                        <Phone class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <dt class="text-xs text-muted-foreground">
                                {{ t('pageDealer.shopDetailsModal.phone') }}
                            </dt>
                            <dd>
                                <a
                                    :href="`tel:${shop.phone}`"
                                    class="text-primary hover:underline"
                                >
                                    {{ shop.phone }}
                                </a>
                            </dd>
                        </div>
                    </div>

                    <div v-if="shop.contact_person" class="flex items-start gap-2">
                        <User class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <dt class="text-xs text-muted-foreground">
                                {{ t('pageDealer.shopDetailsModal.contactPerson') }}
                            </dt>
                            <dd>{{ shop.contact_person }}</dd>
                        </div>
                    </div>

                    <div
                        v-if="locationText || shop.address || shop.landmark"
                        class="flex items-start gap-2"
                    >
                        <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 flex-1 space-y-0.5">
                            <dt class="text-xs text-muted-foreground">
                                {{ t('pageDealer.shopDetailsModal.location') }}
                            </dt>
                            <dd v-if="locationText" class="font-medium">
                                {{ locationText }}
                            </dd>
                            <dd v-if="shop.address" class="text-muted-foreground">
                                {{ shop.address }}
                            </dd>
                            <dd
                                v-if="shop.landmark"
                                class="text-xs text-muted-foreground italic"
                            >
                                {{ shop.landmark }}
                            </dd>
                        </div>
                    </div>

                    <div v-if="shop.deliveryman" class="flex items-start gap-2">
                        <User class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <dt class="text-xs text-muted-foreground">
                                {{ t('pageDealer.shopDetailsModal.deliveryman') }}
                            </dt>
                            <dd>{{ shop.deliveryman.name }}</dd>
                        </div>
                    </div>

                    <div v-if="shop.created_at" class="flex items-start gap-2">
                        <CalendarDays
                            class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
                        />
                        <div class="min-w-0 flex-1">
                            <dt class="text-xs text-muted-foreground">
                                {{ t('pageDealer.shopDetailsModal.registered') }}
                            </dt>
                            <dd>{{ formatDateTime(shop.created_at) }}</dd>
                        </div>
                    </div>
                </dl>

                <Button variant="outline" class="w-full" @click="openFull">
                    <ExternalLink class="mr-2 h-4 w-4" />
                    {{ t('pageDealer.shopDetailsModal.viewFull') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
