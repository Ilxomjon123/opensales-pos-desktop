<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    BellRing,
    Check,
    Globe,
    IdCard,
    Lock,
    PackageX,
    ShoppingCart,
    SlidersHorizontal,
    Store,
    Sparkles,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
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
import { useCurrency } from '@/composables/useCurrency';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();
const { symbol } = useCurrency();

type DealerItem = {
    bot_display_name: string | null;
    bot_short_description: string | null;
    bot_description: string | null;
    bot_display_name_default: string;
    bot_short_description_default: string;
    bot_description_default: string;
    visibility: 'private' | 'public';
    min_order_amount: number;
    marketplace_min_order_amount: number;
    show_out_of_stock: boolean;
    notify_on_price_change: boolean;
    notify_on_new_product: boolean;
};

const props = defineProps<{ dealer: DealerItem }>();
const d = computed(() => props.dealer);

const form = reactive({
    visibility: d.value.visibility ?? 'private',
    min_order_amount: d.value.min_order_amount ?? 0,
    marketplace_min_order_amount: d.value.marketplace_min_order_amount ?? 0,
    show_out_of_stock: d.value.show_out_of_stock ?? true,
    notify_on_price_change: d.value.notify_on_price_change ?? true,
    notify_on_new_product: d.value.notify_on_new_product ?? true,
    bot_display_name: d.value.bot_display_name ?? '',
    bot_short_description: d.value.bot_short_description ?? '',
    bot_description: d.value.bot_description ?? '',
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

const minOrderPreview = computed(() =>
    new Intl.NumberFormat('uz-UZ').format(Number(form.min_order_amount) || 0),
);

const minMarketplacePreview = computed(() =>
    new Intl.NumberFormat('uz-UZ').format(
        Number(form.marketplace_min_order_amount) || 0,
    ),
);

function submit() {
    processing.value = true;
    router.put(
        '/dealer/bot',
        { ...form },
        {
            preserveScroll: true,
            onError: (e) => {
                errors.value = e as Record<string, string>;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('nav.orderSettings')" />

    <div class="mx-auto w-full max-w-3xl p-3 sm:p-4 md:p-6">
        <div class="mb-6 flex items-start gap-3 sm:mb-8 sm:gap-4">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 ring-1 ring-primary/20"
            >
                <SlidersHorizontal class="h-5 w-5 text-primary" />
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-bold tracking-tight sm:text-2xl">
                    {{ t('nav.orderSettings') }}
                </h1>
                <p class="text-xs text-muted-foreground sm:text-sm">
                    {{ t('pageDealer.orderSettings.subtitle') }}
                </p>
            </div>
        </div>

        <form
            id="order-settings-form"
            @submit.prevent="submit"
            class="space-y-5 pb-28 sm:space-y-6"
        >
            <!-- Ko'rinish -->
            <Card class="overflow-hidden">
                <CardHeader class="pb-4">
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-500/10"
                        >
                            <Globe class="h-4.5 w-4.5 text-violet-500" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <CardTitle class="text-base">{{
                                t('pageDealer.orderSettings.visibilityTitle')
                            }}</CardTitle>
                            <CardDescription>{{
                                t('pageDealer.orderSettings.visibilityDesc')
                            }}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <label
                        class="group relative flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition-colors"
                        :class="
                            form.visibility === 'private'
                                ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                                : 'hover:border-foreground/20 hover:bg-muted/40'
                        "
                    >
                        <input
                            type="radio"
                            name="visibility"
                            value="private"
                            v-model="form.visibility"
                            class="sr-only"
                        />
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-colors"
                            :class="
                                form.visibility === 'private'
                                    ? 'bg-primary/15 text-primary'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            <Lock class="h-4.5 w-4.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">
                                {{ t('pageDealer.orderSettings.privateLabel') }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('pageDealer.orderSettings.privateDesc') }}
                            </p>
                        </div>
                        <div
                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border transition-colors"
                            :class="
                                form.visibility === 'private'
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-muted-foreground/30'
                            "
                        >
                            <Check
                                v-if="form.visibility === 'private'"
                                class="h-3 w-3"
                            />
                        </div>
                    </label>
                    <label
                        class="group relative flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition-colors"
                        :class="
                            form.visibility === 'public'
                                ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                                : 'hover:border-foreground/20 hover:bg-muted/40'
                        "
                    >
                        <input
                            type="radio"
                            name="visibility"
                            value="public"
                            v-model="form.visibility"
                            class="sr-only"
                        />
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-colors"
                            :class="
                                form.visibility === 'public'
                                    ? 'bg-primary/15 text-primary'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            <Globe class="h-4.5 w-4.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">
                                {{ t('pageDealer.orderSettings.publicLabel') }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('pageDealer.orderSettings.publicDesc') }}
                            </p>
                        </div>
                        <div
                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border transition-colors"
                            :class="
                                form.visibility === 'public'
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-muted-foreground/30'
                            "
                        >
                            <Check
                                v-if="form.visibility === 'public'"
                                class="h-3 w-3"
                            />
                        </div>
                    </label>
                    <InputError :message="errors.visibility" />
                </CardContent>
            </Card>

            <!-- Buyurtma -->
            <Card class="overflow-hidden">
                <CardHeader class="pb-4">
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10"
                        >
                            <ShoppingCart
                                class="h-4.5 w-4.5 text-emerald-500"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <CardTitle class="text-base">{{
                                t('pageDealer.orderSettings.orderTitle')
                            }}</CardTitle>
                            <CardDescription>{{
                                t('pageDealer.orderSettings.orderDesc')
                            }}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div>
                        <Label
                            for="min_order_amount"
                            class="flex items-center gap-1.5"
                        >
                            <BadgeDollarSign
                                class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                            />
                            {{ t('pageDealer.orderSettings.minOrderAmount') }}
                        </Label>
                        <div class="relative mt-1.5">
                            <Input
                                id="min_order_amount"
                                type="number"
                                min="0"
                                v-model="form.min_order_amount"
                                class="pr-14"
                            />
                            <span
                                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-medium text-muted-foreground"
                                >{{ symbol }}</span
                            >
                        </div>
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            {{
                                Number(form.min_order_amount) > 0
                                    ? t(
                                          'pageDealer.orderSettings.minOrderHint',
                                          { amount: minOrderPreview, symbol },
                                      )
                                    : t(
                                          'pageDealer.orderSettings.minOrderNoLimit',
                                      )
                            }}
                        </p>
                        <InputError :message="errors.min_order_amount" />
                    </div>

                    <div>
                        <Label
                            for="marketplace_min_order_amount"
                            class="flex items-center gap-1.5"
                        >
                            <Store
                                class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                            />
                            {{
                                t(
                                    'pageDealer.orderSettings.marketplaceMinOrderAmount',
                                )
                            }}
                        </Label>
                        <div class="relative mt-1.5">
                            <Input
                                id="marketplace_min_order_amount"
                                v-model="form.marketplace_min_order_amount"
                                type="number"
                                min="0"
                                class="pr-14"
                            />
                            <span
                                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-medium text-muted-foreground"
                                >{{ symbol }}</span
                            >
                        </div>
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            {{
                                Number(form.marketplace_min_order_amount) > 0
                                    ? t(
                                          'pageDealer.orderSettings.marketplaceMinOrderHint',
                                          {
                                              amount: minMarketplacePreview,
                                              symbol,
                                          },
                                      )
                                    : t(
                                          'pageDealer.orderSettings.marketplaceMinOrderNoLimit',
                                      )
                            }}
                        </p>
                        <InputError
                            :message="errors.marketplace_min_order_amount"
                        />
                    </div>

                    <label
                        class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border p-3.5 transition-colors hover:bg-muted/40"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10"
                            >
                                <PackageX class="h-4.5 w-4.5 text-amber-500" />
                            </div>
                            <div class="min-w-0">
                                <div class="font-medium">
                                    {{
                                        t(
                                            'pageDealer.orderSettings.showOutOfStock',
                                        )
                                    }}
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        t(
                                            'pageDealer.orderSettings.showOutOfStockDesc',
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <Switch
                            v-model="form.show_out_of_stock"
                            class="shrink-0"
                        />
                    </label>
                </CardContent>
            </Card>

            <!-- Bildirishnomalar -->
            <Card class="overflow-hidden">
                <CardHeader class="pb-4">
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-500/10"
                        >
                            <BellRing class="h-4.5 w-4.5 text-sky-500" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <CardTitle class="text-base">{{
                                t('pageDealer.orderSettings.notificationsTitle')
                            }}</CardTitle>
                            <CardDescription>{{
                                t('pageDealer.orderSettings.notificationsDesc')
                            }}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <label
                        class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border p-3.5 transition-colors hover:bg-muted/40"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-500/10"
                            >
                                <TrendingUp class="h-4.5 w-4.5 text-rose-500" />
                            </div>
                            <div class="min-w-0">
                                <div class="font-medium">
                                    {{
                                        t(
                                            'pageDealer.orderSettings.notifyPriceChange',
                                        )
                                    }}
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        t(
                                            'pageDealer.orderSettings.notifyPriceChangeDesc',
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <Switch
                            v-model="form.notify_on_price_change"
                            class="shrink-0"
                        />
                    </label>
                    <label
                        class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border p-3.5 transition-colors hover:bg-muted/40"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-500/10"
                            >
                                <Sparkles class="h-4.5 w-4.5 text-indigo-500" />
                            </div>
                            <div class="min-w-0">
                                <div class="font-medium">
                                    {{
                                        t(
                                            'pageDealer.orderSettings.notifyNewProduct',
                                        )
                                    }}
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        t(
                                            'pageDealer.orderSettings.notifyNewProductDesc',
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <Switch
                            v-model="form.notify_on_new_product"
                            class="shrink-0"
                        />
                    </label>
                </CardContent>
            </Card>

            <!-- Profil / ko'rinish matni (bot + mobil) -->
            <Card class="overflow-hidden">
                <CardHeader class="pb-4">
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-fuchsia-500/10"
                        >
                            <IdCard class="h-4.5 w-4.5 text-fuchsia-500" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <CardTitle class="text-base">{{
                                t('pageDealer.orderSettings.profileTitle')
                            }}</CardTitle>
                            <CardDescription>{{
                                t('pageDealer.orderSettings.profileDesc')
                            }}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <Label for="bot_display_name">{{
                            t('pageDealer.orderSettings.nameLabel')
                        }}</Label>
                        <Input
                            id="bot_display_name"
                            v-model="form.bot_display_name"
                            :placeholder="d.bot_display_name_default"
                            class="mt-1.5"
                        />
                        <InputError :message="errors.bot_display_name" />
                    </div>
                    <div>
                        <Label for="bot_short_description">{{
                            t('pageDealer.orderSettings.shortDescriptionLabel')
                        }}</Label>
                        <Input
                            id="bot_short_description"
                            v-model="form.bot_short_description"
                            :placeholder="d.bot_short_description_default"
                            class="mt-1.5"
                        />
                        <InputError :message="errors.bot_short_description" />
                    </div>
                    <div>
                        <Label for="bot_description">{{
                            t('pageDealer.orderSettings.fullDescriptionLabel')
                        }}</Label>
                        <Input
                            id="bot_description"
                            v-model="form.bot_description"
                            :placeholder="d.bot_description_default"
                            class="mt-1.5"
                        />
                        <InputError :message="errors.bot_description" />
                    </div>
                </CardContent>
            </Card>
        </form>

        <!-- Float saqlash tugmasi — to'g'ridan-to'g'ri submit() (form= bog'lanishiga tayanmaymiz) -->
        <Button
            type="button"
            size="lg"
            :disabled="processing"
            class="fixed right-4 bottom-20 z-50 shadow-xl shadow-primary/20 md:right-8 md:bottom-8"
            @click="submit"
        >
            <Spinner v-if="processing" class="mr-2" />
            {{
                processing
                    ? t('pageDealer.orderSettings.saving')
                    : t('pageDealer.orderSettings.save')
            }}
        </Button>
    </div>
</template>
