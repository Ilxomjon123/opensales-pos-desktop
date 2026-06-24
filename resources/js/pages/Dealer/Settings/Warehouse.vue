<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, Link2, Loader2, MapPin, Save, X as XIcon } from 'lucide-vue-next';
import { computed, defineAsyncComponent, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
// Leaflet `window` ga tayanadi → SSR'da yiqiladi. Faqat klientda lazy yuklanadi.
const LocationPicker = defineAsyncComponent(() => import('@/components/LocationPicker.vue'));
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

type WarehousePayload = {
    latitude: number | null;
    longitude: number | null;
    address: string | null;
    map_provider: 'yandex' | 'google' | 'manual' | null;
};

const props = defineProps<{
    warehouse: WarehousePayload;
    mapDefaults?: { lat: number; lng: number; zoom: number } | null;
}>();

const mapCenter = computed<[number, number] | null>(() =>
    props.mapDefaults ? [props.mapDefaults.lat, props.mapDefaults.lng] : null,
);

const form = reactive({
    latitude: props.warehouse.latitude,
    longitude: props.warehouse.longitude,
    address: props.warehouse.address ?? '',
    map_provider: props.warehouse.map_provider ?? 'manual',
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

const mapLink = ref('');
const resolving = ref(false);
const resolveError = ref<string | null>(null);
const resolveOk = ref(false);

async function resolveLink() {
    if (!mapLink.value.trim()) {
        return;
    }

    resolving.value = true;
    resolveError.value = null;
    resolveOk.value = false;

    try {
        const res = await fetch(
            `/dealer/settings/warehouse/resolve-map-link?url=${encodeURIComponent(mapLink.value.trim())}`,
            {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );

        const data = await res.json();

        if (!res.ok) {
            resolveError.value = (data?.message as string) ?? t('pageDealer.warehouse.resolveError');

            return;
        }

        form.latitude = Number(data.lat);
        form.longitude = Number(data.lng);

        const addressParts = [data.region, data.district, data.address].filter(Boolean);

        if (addressParts.length > 0) {
            form.address = addressParts.join(', ');
        }

        form.map_provider = /yandex\./i.test(mapLink.value) ? 'yandex' : 'google';
        resolveOk.value = true;
        mapLink.value = '';
    } catch {
        resolveError.value = t('pageDealer.warehouse.resolveError');
    } finally {
        resolving.value = false;
    }
}

function handleMapUpdate(lat: number | null, lng: number | null) {
    form.latitude = lat;
    form.longitude = lng;
}

function handleAddress(addr: { region: string | null; district: string | null; address: string | null }) {
    const parts = [addr.region, addr.district, addr.address].filter((v): v is string => Boolean(v));

    if (parts.length > 0) {
        form.address = parts.join(', ');
    }
}

function save() {
    if (form.latitude === null || form.longitude === null) {
        errors.value = { latitude: t('pageDealer.warehouse.coordsRequired') };

        return;
    }

    processing.value = true;
    errors.value = {};

    router.put('/dealer/settings/warehouse', form, {
        preserveScroll: true,
        onError: (errs) => {
            errors.value = errs as Record<string, string>;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.warehouse.headTitle')" />

    <div class="flex flex-col gap-4 p-3 sm:gap-5 sm:p-4 lg:gap-6 lg:p-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                <MapPin class="h-5 w-5 text-primary" />
            </div>
            <div class="min-w-0">
                <h1 class="text-lg font-bold tracking-tight sm:text-xl lg:text-2xl">
                    {{ t('pageDealer.warehouse.title') }}
                </h1>
                <p class="text-xs text-muted-foreground sm:text-sm">
                    {{ t('pageDealer.warehouse.subtitle') }}
                </p>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{ t('pageDealer.warehouse.linkCardTitle') }}</CardTitle>
                <CardDescription>{{ t('pageDealer.warehouse.linkCardDesc') }}</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Input
                        v-model="mapLink"
                        :placeholder="t('pageDealer.warehouse.linkPlaceholder')"
                        class="flex-1"
                        @keydown.enter.prevent="resolveLink"
                    />
                    <Button type="button" :disabled="resolving || !mapLink.trim()" @click="resolveLink">
                        <Loader2 v-if="resolving" class="mr-2 h-4 w-4 animate-spin" />
                        <Link2 v-else class="mr-2 h-4 w-4" />
                        {{ t('pageDealer.warehouse.resolveBtn') }}
                    </Button>
                </div>
                <div v-if="resolveError" class="flex items-center gap-2 rounded-md bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    <XIcon class="h-3.5 w-3.5" />
                    {{ resolveError }}
                </div>
                <div v-else-if="resolveOk" class="flex items-center gap-2 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                    <Check class="h-3.5 w-3.5" />
                    {{ t('pageDealer.warehouse.resolveOk') }}
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{ t('pageDealer.warehouse.mapCardTitle') }}</CardTitle>
                <CardDescription>{{ t('pageDealer.warehouse.mapCardDesc') }}</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <LocationPicker
                    :latitude="form.latitude"
                    :longitude="form.longitude"
                    :provider="form.map_provider === 'manual' ? 'yandex' : form.map_provider"
                    :default-center="mapCenter"
                    :default-zoom="mapDefaults?.zoom ?? 13"
                    @update="handleMapUpdate"
                    @update:provider="(p) => (form.map_provider = p === 'osm' ? 'manual' : p)"
                    @address="handleAddress"
                />
                <InputError :message="errors.latitude" />

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <Label for="lat">{{ t('pageDealer.warehouse.latitude') }}</Label>
                        <Input id="lat" :model-value="form.latitude ?? ''" readonly class="font-mono text-xs" />
                    </div>
                    <div>
                        <Label for="lng">{{ t('pageDealer.warehouse.longitude') }}</Label>
                        <Input id="lng" :model-value="form.longitude ?? ''" readonly class="font-mono text-xs" />
                    </div>
                </div>

                <div>
                    <Label for="addr">{{ t('pageDealer.warehouse.address') }}</Label>
                    <Input id="addr" v-model="form.address" :placeholder="t('pageDealer.warehouse.addressPlaceholder')" />
                    <InputError :message="errors.address" class="mt-1" />
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end">
            <Button :disabled="processing || form.latitude === null || form.longitude === null" @click="save">
                <Loader2 v-if="processing" class="mr-2 h-4 w-4 animate-spin" />
                <Save v-else class="mr-2 h-4 w-4" />
                {{ t('pageDealer.warehouse.save') }}
            </Button>
        </div>
    </div>
</template>
