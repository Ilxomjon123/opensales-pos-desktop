<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Building2, Camera, MapPin, Phone, Search, Store, User, X } from 'lucide-vue-next';
import { computed, defineAsyncComponent, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
// Leaflet `window` ga tayanadi → SSR'da yiqiladi. Faqat klientda lazy yuklanadi.
const LocationPicker = defineAsyncComponent(() => import('@/components/LocationPicker.vue'));
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';

type Shop = {
    id: number;
    name: string;
    legal_name: string | null;
    phone: string;
    address: string | null;
    landmark: string | null;
    region: string | null;
    district: string | null;
    inn: string | null;
    contact_person: string | null;
    photo_url: string | null;
    latitude: number | null;
    longitude: number | null;
    map_provider: 'yandex' | 'google' | 'osm';
    is_active: boolean;
    parent_shop_id: number | null;
    is_main_branch: boolean;
    branches_count?: number;
};

type RegionOption = { name: string; districts: string[] };
type ParentShopOption = { id: number; name: string; inn: string | null; region: string | null; district: string | null };

const props = defineProps<{
    shop: { data: Shop };
    regions: RegionOption[];
    mapDefaults?: { lat: number; lng: number; zoom: number } | null;
    parentShops: ParentShopOption[];
}>();

const { t } = useI18n();
const s = props.shop.data;

const mapCenter = computed<[number, number] | null>(() =>
    props.mapDefaults ? [props.mapDefaults.lat, props.mapDefaults.lng] : null,
);

const formData = reactive({
    name: s.name,
    legal_name: s.legal_name ?? '',
    phone: s.phone,
    contact_person: s.contact_person ?? '',
    address: s.address ?? '',
    landmark: s.landmark ?? '',
    region: s.region ?? '',
    district: s.district ?? '',
    inn: s.inn ?? '',
    latitude: s.latitude,
    longitude: s.longitude,
    map_provider: (s.map_provider ?? 'yandex') as 'yandex' | 'google' | 'osm',
    is_active: s.is_active,
    photo: null as File | null,
    remove_photo: false,
    parent_shop_id: s.parent_shop_id,
});

const parentShopItems = computed(() =>
    props.parentShops.map((shop) => ({
        value: shop.id,
        label: shop.name,
        meta: [shop.region, shop.district].filter(Boolean).join(', ') || (shop.inn ? t('pageDealer.shopsForm.innPrefix', { inn: shop.inn }) : ''),
    })),
);

const hasBranches = computed(() => (s.branches_count ?? 0) > 0);
const canPickParent = computed(() => !hasBranches.value && props.parentShops.length > 0);

const availableDistricts = computed<string[]>(() => {
    const found = props.regions.find((r) => r.name === formData.region);

    return found?.districts ?? [];
});

const districtItems = computed(() => {
    const items = availableDistricts.value.map((d) => ({ value: d, label: d }));

    // Aniqlangan tuman ro'yxatda bo'lmasa ham tanlanadigan/saqlanadigan qilamiz.
    if (formData.district && !availableDistricts.value.includes(formData.district)) {
        items.unshift({ value: formData.district, label: formData.district });
    }

    return items;
});

type InnLookupShop = {
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
    latitude: number | null;
    longitude: number | null;
    photo: string | null;
    photo_url: string | null;
    is_own: boolean;
};

const innLookupStatus = ref<'idle' | 'loading' | 'success' | 'notfound' | 'error' | 'choose'>('idle');
const innLookupMessage = ref('');
const innLookupResults = ref<InnLookupShop[]>([]);

let innLookupTimer: ReturnType<typeof setTimeout> | null = null;
let lastLookedUpInn = formData.inn.replace(/\D+/g, '');

function fillFromInnShop(shop: InnLookupShop) {
    if (shop.name) {
        formData.name = shop.name;
    }

    if (shop.legal_name) {
        formData.legal_name = shop.legal_name;
    }

    if (shop.contact_person) {
        formData.contact_person = shop.contact_person;
    }

    if (shop.address) {
        formData.address = shop.address;
    }

    if (shop.landmark) {
        formData.landmark = shop.landmark;
    }

    if (shop.region) {
        formData.region = shop.region;
    }

    if (shop.district) {
        formData.district = shop.district;
    }

    if (shop.latitude != null) {
        formData.latitude = shop.latitude;
    }

    if (shop.longitude != null) {
        formData.longitude = shop.longitude;
    }
}

function applyInnMatch(shop: InnLookupShop) {
    fillFromInnShop(shop);

    innLookupResults.value = [];
    innLookupStatus.value = 'success';
    innLookupMessage.value = shop.is_own
        ? t('pageDealer.shopsForm.lookupAlreadyYours', { name: shop.name })
        : t('pageDealer.shopsForm.lookupFound', { name: shop.name });
}

async function lookupInn() {
    const inn = formData.inn.trim();

    if (!/^\d{9}$/.test(inn)) {
        innLookupStatus.value = 'error';
        innLookupMessage.value = t('pageDealer.shopsForm.innMustBe9');

        return;
    }

    innLookupStatus.value = 'loading';
    innLookupMessage.value = '';
    innLookupResults.value = [];
    lastLookedUpInn = inn;

    try {
        const res = await fetch(`/dealer/shops-api/inn-lookup/${encodeURIComponent(inn)}`, {
            headers: { Accept: 'application/json' },
        });

        if (!res.ok) {
            const body = await res.json().catch(() => ({ message: t('pageDealer.shopsForm.serviceDown') }));
            innLookupStatus.value = 'error';
            innLookupMessage.value = body.message ?? t('pageDealer.shopsForm.error');

            return;
        }

        const data = await res.json() as
            | { shops: InnLookupShop[] }
            | { name: string | null; legal_name: string | null; region: string | null; district: string | null; address: string | null };

        if ('shops' in data) {
            const shops = (data.shops ?? []).filter((shop) => shop.id !== s.id);

            if (shops.length === 1) {
                applyInnMatch(shops[0]);

                return;
            }

            if (shops.length > 1) {
                innLookupStatus.value = 'choose';
                innLookupMessage.value = t('pageDealer.shopsForm.multipleFound', { n: shops.length });
                innLookupResults.value = shops;

                return;
            }

            if ((data.shops ?? []).length > 0) {
                innLookupStatus.value = 'success';
                innLookupMessage.value = t('pageDealer.shopsForm.innBelongsToShop');

                return;
            }
        }

        const orgInfo = data as { name: string | null; legal_name: string | null; region: string | null; district: string | null; address: string | null };

        if (!orgInfo.name) {
            innLookupStatus.value = 'notfound';
            innLookupMessage.value = t('pageDealer.shopsForm.innNotFound');

            return;
        }

        formData.name = orgInfo.name;

        if (orgInfo.legal_name) {
            formData.legal_name = orgInfo.legal_name;
        }

        if (orgInfo.region) {
            formData.region = orgInfo.region;
        }

        if (orgInfo.district) {
            formData.district = orgInfo.district;
        }

        if (orgInfo.address) {
            formData.address = orgInfo.address;
        }

        innLookupStatus.value = 'success';
        innLookupMessage.value = t('pageDealer.shopsForm.foundOrginfo', { name: orgInfo.name });
    } catch {
        innLookupStatus.value = 'error';
        innLookupMessage.value = t('pageDealer.shopsForm.networkError');
    }
}

watch(() => formData.inn, (val) => {
    if (innLookupTimer) {
        clearTimeout(innLookupTimer);
    }

    const trimmed = (val ?? '').trim();

    if (!/^\d{9}$/.test(trimmed)) {
        return;
    }

    if (trimmed === lastLookedUpInn) {
        return;
    }

    if (innLookupStatus.value === 'loading') {
        return;
    }

    innLookupTimer = setTimeout(() => {
        lookupInn();
    }, 500);
});

function onRegionChange() {
    formData.district = '';
}

const photoPreview = ref<string | null>(s.photo_url);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

function onPhotoChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];

    if (file) {
        formData.photo = file;
        formData.remove_photo = false;
        photoPreview.value = URL.createObjectURL(file);
    }
}

function removePhoto() {
    formData.photo = null;
    formData.remove_photo = true;
    photoPreview.value = null;
}

function onMapUpdate(lat: number | null, lng: number | null) {
    formData.latitude = lat;
    formData.longitude = lng;
}

function onProviderUpdate(p: 'yandex' | 'google' | 'osm') {
    formData.map_provider = p;
}

function onAddressFill(addr: { region: string | null; district: string | null; address: string | null }) {
    if (addr.region && addr.region !== formData.region) {
        formData.region = addr.region;
    }

    if (addr.district && addr.district !== formData.district) {
        formData.district = addr.district;
    }

    if (addr.address && addr.address !== formData.address) {
        formData.address = addr.address;
    }
}

function submit() {
    processing.value = true;
    const data = new FormData();
    data.append('_method', 'PUT');
    data.append('name', formData.name);
    data.append('legal_name', formData.legal_name);
    data.append('phone', formData.phone);
    data.append('contact_person', formData.contact_person);
    data.append('address', formData.address);
    data.append('landmark', formData.landmark);
    data.append('region', formData.region);
    data.append('district', formData.district);
    data.append('inn', formData.inn);

    if (formData.latitude !== null) {
data.append('latitude', String(formData.latitude));
}

    if (formData.longitude !== null) {
data.append('longitude', String(formData.longitude));
}

    data.append('map_provider', formData.map_provider);
    data.append('is_active', formData.is_active ? '1' : '0');
    data.append('parent_shop_id', formData.parent_shop_id != null ? String(formData.parent_shop_id) : '');

    if (formData.photo) {
data.append('photo', formData.photo);
}

    if (formData.remove_photo) {
data.append('remove_photo', '1');
}

    router.post(`/dealer/shops/${s.id}`, data, {
        onFinish: () => {
 processing.value = false; 
},
        onError: (e) => {
 errors.value = e; 
},
    });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.shopsForm.editHeadTitle', { name: s.name })" />

    <div class="mx-auto w-full max-w-5xl p-4 md:p-8">
        <div class="mb-6 flex items-center gap-3 sm:mb-8 sm:gap-4">
            <Button variant="ghost" size="icon" class="shrink-0" @click="router.get(`/dealer/shops/${s.id}`)">
                <ArrowLeft class="h-5 w-5" />
            </Button>
            <div class="min-w-0">
                <h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">{{ s.name }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('pageDealer.shopsForm.editSubtitle') }}</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- STIR (INN) — avtomatik to'ldirish -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <Search class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageDealer.shopsForm.innFillTitle') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('pageDealer.shopsForm.innFillHint') }}
                            </p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-2 p-6">
                        <Label for="inn" class="mb-1.5 flex items-center gap-1.5">
                            <Building2 class="h-3.5 w-3.5 text-muted-foreground" />
                            {{ t('pageDealer.shopsForm.innLabel') }}
                        </Label>
                        <div class="flex gap-2">
                            <Input
                                id="inn"
                                v-model="formData.inn"
                                :placeholder="t('pageDealer.shopsForm.innPlaceholder')"
                                inputmode="numeric"
                                maxlength="9"
                                pattern="\d{9}"
                                class="font-mono"
                                @keydown.enter.prevent="lookupInn"
                            />
                            <Button
                                type="button"
                                :disabled="innLookupStatus === 'loading' || !formData.inn"
                                @click="lookupInn"
                            >
                                <Spinner v-if="innLookupStatus === 'loading'" class="mr-2 h-4 w-4" />
                                <Search v-else class="mr-2 h-4 w-4" />
                                {{ t('pageDealer.shopsForm.find') }}
                            </Button>
                        </div>
                        <p
                            v-if="innLookupMessage"
                            class="text-xs"
                            :class="{
                                'text-emerald-600': innLookupStatus === 'success',
                                'text-amber-600': innLookupStatus === 'notfound' || innLookupStatus === 'choose',
                                'text-destructive': innLookupStatus === 'error',
                            }"
                        >
                            {{ innLookupMessage }}
                        </p>
                        <div v-if="innLookupStatus === 'choose' && innLookupResults.length" class="space-y-1.5">
                            <button
                                v-for="shop in innLookupResults"
                                :key="shop.id"
                                type="button"
                                class="flex w-full items-start gap-3 rounded-md border bg-card p-2.5 text-left text-sm transition-colors hover:border-primary hover:bg-muted/50"
                                @click="applyInnMatch(shop)"
                            >
                                <img
                                    v-if="shop.photo_url"
                                    :src="shop.photo_url"
                                    class="h-10 w-10 shrink-0 rounded object-cover"
                                    alt=""
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate font-medium">{{ shop.name }}</span>
                                        <span
                                            v-if="shop.is_own"
                                            class="shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                        >
                                            {{ t('pageDealer.shopsForm.yours') }}
                                        </span>
                                    </div>
                                    <div class="mt-0.5 truncate text-xs text-muted-foreground">
                                        {{ [shop.region, shop.district, shop.address].filter(Boolean).join(', ') || t('pageDealer.shopsForm.addressNotSet') }}
                                    </div>
                                    <div v-if="shop.phone" class="truncate text-xs text-muted-foreground">
                                        {{ shop.phone }}
                                    </div>
                                </div>
                            </button>
                        </div>
                        <InputError :message="errors.inn" />
                    </CardContent>
                </Card>
            </div>

            <!-- Asosiy -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <Store class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageDealer.shopsForm.basicInfo') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageDealer.shopsForm.basicInfoHintEdit') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-5 p-6">
                        <div>
                            <Label for="name" class="mb-1.5">{{ t('pageDealer.shopsForm.shopName') }} <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="formData.name" required />
                            <InputError :message="errors.name" />
                        </div>
                        <div>
                            <Label for="legal_name" class="mb-1.5 flex items-center gap-1.5">
                                <Building2 class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageDealer.shopsForm.legalName') }}
                            </Label>
                            <Input id="legal_name" v-model="formData.legal_name" />
                        </div>

                        <div>
                            <Label class="mb-1.5 flex items-center gap-1.5">
                                <Store class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageDealer.shopsForm.mainBranch') }}
                            </Label>
                            <SearchableSelect
                                v-if="canPickParent"
                                v-model="formData.parent_shop_id"
                                :items="parentShopItems"
                                :placeholder="t('pageDealer.shopsForm.independentPlaceholder')"
                                :search-placeholder="t('pageDealer.shopsForm.mainShopSearch')"
                                :empty-text="t('pageDealer.shopsForm.notFound')"
                            >
                                <template #item-suffix="{ item }">
                                    <span class="shrink-0 text-[11px] text-muted-foreground">{{ item.meta }}</span>
                                </template>
                            </SearchableSelect>
                            <p v-if="hasBranches" class="text-xs text-amber-600">
                                {{ t('pageDealer.shopsForm.hasBranchesHint') }}
                            </p>
                            <p v-else class="mt-1.5 text-xs text-muted-foreground">
                                {{ t('pageDealer.shopsForm.mainBranchHint') }}
                            </p>
                            <InputError :message="errors.parent_shop_id" />
                        </div>

                        <div class="flex items-center gap-2">
                            <input id="is_active" type="checkbox" v-model="formData.is_active" class="rounded border-input" />
                            <Label for="is_active">{{ t('pageDealer.shopsForm.active') }}</Label>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Aloqa -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <Phone class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageDealer.shopsForm.contact') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageDealer.shopsForm.contactHintEdit') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="grid gap-4 p-6 sm:grid-cols-2">
                        <div>
                            <Label for="contact_person" class="mb-1.5 flex items-center gap-1.5">
                                <User class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageDealer.shopsForm.contactPerson') }}
                            </Label>
                            <Input id="contact_person" v-model="formData.contact_person" />
                        </div>
                        <div>
                            <Label for="phone" class="mb-1.5 flex items-center gap-1.5">
                                <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageDealer.shopsForm.phone') }} <span class="text-destructive">*</span>
                            </Label>
                            <PhoneInput v-model="formData.phone" :error="!!errors.phone" />
                            <InputError :message="errors.phone" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Manzil + Map -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <MapPin class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageDealer.shopsForm.location') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageDealer.shopsForm.locationHintEdit') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-5 p-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label class="mb-1.5">{{ t('pageDealer.shopsForm.region') }}</Label>
                                <SearchableSelect
                                    v-model="formData.region"
                                    :items="props.regions"
                                    value-key="name"
                                    label-key="name"
                                    :placeholder="t('pageDealer.shopsForm.selectPlaceholder')"
                                    :search-placeholder="t('pageDealer.shopsForm.regionSearch')"
                                    :empty-text="t('pageDealer.shopsForm.regionEmpty')"
                                    @change="onRegionChange"
                                />
                                <InputError :message="errors.region" />
                            </div>
                            <div>
                                <Label class="mb-1.5">{{ t('pageDealer.shopsForm.district') }}</Label>
                                <SearchableSelect
                                    v-model="formData.district"
                                    :items="districtItems"
                                    :disabled="!formData.region"
                                    :placeholder="t('pageDealer.shopsForm.selectPlaceholder')"
                                    :search-placeholder="t('pageDealer.shopsForm.districtSearch')"
                                    :empty-text="t('pageDealer.shopsForm.districtEmpty')"
                                />
                                <InputError :message="errors.district" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="address" class="mb-1.5">{{ t('pageDealer.shopsForm.address') }}</Label>
                                <Input id="address" v-model="formData.address" />
                            </div>
                            <div>
                                <Label for="landmark" class="mb-1.5">{{ t('pageDealer.shopsForm.landmark') }}</Label>
                                <Input id="landmark" v-model="formData.landmark" />
                            </div>
                        </div>

                        <div>
                            <Label class="mb-2 flex items-center gap-1.5">
                                <MapPin class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageDealer.shopsForm.markOnMap') }}
                                <span class="text-rose-500">*</span>
                            </Label>
                            <LocationPicker
                                :latitude="formData.latitude"
                                :longitude="formData.longitude"
                                :provider="formData.map_provider"
                                :default-center="mapCenter"
                                :default-zoom="mapDefaults?.zoom ?? 13"
                                @update="onMapUpdate"
                                @update:provider="onProviderUpdate"
                                @address="onAddressFill"
                            />
                            <InputError :message="errors.latitude || errors.longitude" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Rasm -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <Camera class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageDealer.shopsForm.photo') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageDealer.shopsForm.photoHint') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="p-6">
                        <div class="flex gap-4">
                            <label class="group flex h-32 w-32 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed transition-colors hover:border-primary hover:bg-muted/50">
                                <img v-if="photoPreview" :src="photoPreview" class="h-full w-full object-cover" />
                                <template v-else>
                                    <Camera class="mb-1 h-6 w-6 text-muted-foreground/50 group-hover:text-primary" />
                                    <span class="text-[10px] text-muted-foreground">{{ t('pageDealer.shopsForm.photo') }}</span>
                                </template>
                                <input type="file" accept="image/*" class="hidden" @change="onPhotoChange" />
                            </label>
                            <div class="flex-1 space-y-2 text-sm text-muted-foreground">
                                <p>{{ t('pageDealer.shopsForm.photoFormats') }}</p>
                                <Button v-if="photoPreview" variant="ghost" size="sm" type="button" class="text-destructive" @click="removePhoto">
                                    <X class="mr-1 h-3.5 w-3.5" />
                                    {{ t('pageDealer.shopsForm.removePhoto') }}
                                </Button>
                            </div>
                        </div>
                        <InputError :message="errors.photo" />
                    </CardContent>
                </Card>
            </div>

            <div class="sticky bottom-0 z-10 -mx-4 flex items-center justify-end gap-3 border-t bg-background/80 px-4 py-4 backdrop-blur md:-mx-8 md:px-8">
                <Button variant="outline" type="button" @click="router.get(`/dealer/shops/${s.id}`)">{{ t('pageDealer.shopsForm.cancelShort') }}</Button>
                <Button type="submit" :disabled="processing" class="min-w-[140px]">
                    <Spinner v-if="processing" class="mr-2" />
                    {{ processing ? t('pageDealer.shopsForm.saving') : t('pageDealer.shopsForm.save') }}
                </Button>
            </div>
        </form>
    </div>
</template>
