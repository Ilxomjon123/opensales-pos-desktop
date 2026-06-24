<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Building2, Camera, MapPin, Phone, Search, Store, User, Truck, X } from 'lucide-vue-next';
import { computed, defineAsyncComponent, ref, watch } from 'vue';
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

type RegionOption = { name: string; districts: string[] };
type ParentShopOption = { id: number; name: string; inn: string | null; region: string | null; district: string | null };

const props = defineProps<{
    deliverymen?: { data: { id: number; name: string; username: string }[] };
    regions: RegionOption[];
    mapDefaults?: { lat: number; lng: number; zoom: number } | null;
    parentShops: ParentShopOption[];
}>();

const mapCenter = computed<[number, number] | null>(() =>
    props.mapDefaults ? [props.mapDefaults.lat, props.mapDefaults.lng] : null,
);

const { t } = useI18n();
const page = usePage();
const role = computed(() => page.props.auth?.role);
const canPickDeliveryman = computed(() => role.value === 'dealer');

const form = useForm({
    name: '',
    legal_name: '',
    phone: '',
    contact_person: '',
    address: '',
    landmark: '',
    region: '',
    district: '',
    inn: '',
    latitude: null as number | null,
    longitude: null as number | null,
    map_provider: 'yandex' as 'yandex' | 'google' | 'osm',
    photo: null as File | null,
    photo_source_path: null as string | null,
    deliveryman_id: null as number | null,
    parent_shop_id: null as number | null,
});

const parentShopItems = computed(() =>
    props.parentShops.map((shop) => ({
        value: shop.id,
        label: shop.name,
        meta: [shop.region, shop.district].filter(Boolean).join(', ') || (shop.inn ? t('pageDealer.shopsForm.innPrefix', { inn: shop.inn }) : ''),
    })),
);

watch(() => form.parent_shop_id, (newId) => {
    if (!newId) {
        return;
    }

    const parent = props.parentShops.find((shop) => shop.id === newId);

    if (parent && parent.inn && !form.inn) {
        form.inn = parent.inn;
    }
});

const availableDistricts = computed<string[]>(() => {
    const found = props.regions.find((r) => r.name === form.region);

    return found?.districts ?? [];
});

const districtItems = computed(() => {
    const items = availableDistricts.value.map((d) => ({ value: d, label: d }));

    // Xaritadan/STIRdan aniqlangan tuman ro'yxatda bo'lmasa ham tanlanadigan qilamiz
    // (RU район nomi DB shahar bilan har doim mos kelmaydi).
    if (form.district && !availableDistricts.value.includes(form.district)) {
        items.unshift({ value: form.district, label: form.district });
    }

    return items;
});

const innLookupStatus = ref<'idle' | 'loading' | 'success' | 'notfound' | 'error'>('idle');
const innLookupMessage = ref('');

type PhoneLookupShop = {
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

const phoneLookupStatus = ref<'idle' | 'loading' | 'success' | 'notfound' | 'error' | 'choose'>('idle');
const phoneLookupMessage = ref('');
const phoneLookupResults = ref<PhoneLookupShop[]>([]);
const innLookupResults = ref<PhoneLookupShop[]>([]);

let phoneLookupTimer: ReturnType<typeof setTimeout> | null = null;
let innLookupTimer: ReturnType<typeof setTimeout> | null = null;
let lastLookedUpPhone = '';
let lastLookedUpInn = '';

function fillFromShop(shop: PhoneLookupShop) {
    if (shop.name) {
        form.name = shop.name;
    }

    if (shop.legal_name) {
        form.legal_name = shop.legal_name;
    }

    if (shop.phone) {
        form.phone = shop.phone;
        lastLookedUpPhone = shop.phone.trim();
    }

    if (shop.contact_person) {
        form.contact_person = shop.contact_person;
    }

    if (shop.address) {
        form.address = shop.address;
    }

    if (shop.landmark) {
        form.landmark = shop.landmark;
    }

    if (shop.region) {
        form.region = shop.region;
    }

    if (shop.district) {
        form.district = shop.district;
    }

    if (shop.inn) {
        form.inn = shop.inn;
        lastLookedUpInn = shop.inn.replace(/\D+/g, '');
    }

    if (shop.latitude != null) {
        form.latitude = shop.latitude;
    }

    if (shop.longitude != null) {
        form.longitude = shop.longitude;
    }

    if (shop.photo && shop.photo_url) {
        form.photo = null;
        form.photo_source_path = shop.photo;
        photoPreview.value = shop.photo_url;
    }
}

function applyPhoneMatch(shop: PhoneLookupShop) {
    fillFromShop(shop);

    phoneLookupResults.value = [];
    phoneLookupStatus.value = 'success';
    phoneLookupMessage.value = shop.is_own
        ? t('pageDealer.shopsForm.lookupAlreadyYours', { name: shop.name })
        : t('pageDealer.shopsForm.lookupFound', { name: shop.name });
}

function applyInnMatch(shop: PhoneLookupShop) {
    fillFromShop(shop);

    innLookupResults.value = [];
    innLookupStatus.value = 'success';
    innLookupMessage.value = shop.is_own
        ? t('pageDealer.shopsForm.lookupAlreadyYours', { name: shop.name })
        : t('pageDealer.shopsForm.lookupFound', { name: shop.name });
}

async function lookupPhone() {
    const phone = form.phone.trim();
    const digits = phone.replace(/\D+/g, '');

    if (digits.length < 7) {
        phoneLookupStatus.value = 'error';
        phoneLookupMessage.value = t('pageDealer.shopsForm.phoneEnterFull');
        phoneLookupResults.value = [];

        return;
    }

    phoneLookupStatus.value = 'loading';
    phoneLookupMessage.value = '';
    phoneLookupResults.value = [];
    lastLookedUpPhone = phone;

    try {
        const res = await fetch(`/dealer/shops-api/phone-lookup?phone=${encodeURIComponent(phone)}`, {
            headers: { Accept: 'application/json' },
        });

        if (res.status === 404) {
            phoneLookupStatus.value = 'notfound';
            phoneLookupMessage.value = t('pageDealer.shopsForm.phoneNotFound');

            return;
        }

        if (!res.ok) {
            const body = await res.json().catch(() => ({ message: t('pageDealer.shopsForm.error') }));
            phoneLookupStatus.value = 'error';
            phoneLookupMessage.value = body.message ?? t('pageDealer.shopsForm.error');

            return;
        }

        const data = await res.json() as { shops: PhoneLookupShop[] };
        const shops = data.shops ?? [];

        if (shops.length === 0) {
            phoneLookupStatus.value = 'notfound';
            phoneLookupMessage.value = t('pageDealer.shopsForm.phoneNotFound');

            return;
        }

        if (shops.length === 1) {
            applyPhoneMatch(shops[0]);

            return;
        }

        phoneLookupStatus.value = 'choose';
        phoneLookupMessage.value = t('pageDealer.shopsForm.multipleFound', { n: shops.length });
        phoneLookupResults.value = shops;
    } catch {
        phoneLookupStatus.value = 'error';
        phoneLookupMessage.value = t('pageDealer.shopsForm.networkError');
    }
}

async function lookupInn() {
    const inn = form.inn.trim();

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
            | { shops: PhoneLookupShop[] }
            | { name: string | null; legal_name: string | null; region: string | null; district: string | null; address: string | null };

        if ('shops' in data) {
            const shops = data.shops ?? [];

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
        }

        const orgInfo = data as { name: string | null; legal_name: string | null; region: string | null; district: string | null; address: string | null };

        if (!orgInfo.name) {
            innLookupStatus.value = 'notfound';
            innLookupMessage.value = t('pageDealer.shopsForm.innNotFound');

            return;
        }

        form.name = orgInfo.name;

        if (orgInfo.legal_name) {
            form.legal_name = orgInfo.legal_name;
        }

        if (orgInfo.region) {
            form.region = orgInfo.region;
        }

        if (orgInfo.district) {
            form.district = orgInfo.district;
        }

        if (orgInfo.address) {
            form.address = orgInfo.address;
        }

        innLookupStatus.value = 'success';
        innLookupMessage.value = t('pageDealer.shopsForm.foundOrginfo', { name: orgInfo.name });
    } catch {
        innLookupStatus.value = 'error';
        innLookupMessage.value = t('pageDealer.shopsForm.networkError');
    }
}

watch(() => form.phone, (val) => {
    if (phoneLookupTimer) {
        clearTimeout(phoneLookupTimer);
    }

    const trimmed = (val ?? '').trim();
    const digits = trimmed.replace(/\D+/g, '');

    if (digits.length < 9) {
        return;
    }

    if (trimmed === lastLookedUpPhone) {
        return;
    }

    if (phoneLookupStatus.value === 'loading') {
        return;
    }

    phoneLookupTimer = setTimeout(() => {
        lookupPhone();
    }, 500);
});

watch(() => form.inn, (val) => {
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
    form.district = '';
}

const photoPreview = ref<string | null>(null);

function onPhotoChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];

    if (file) {
        form.photo = file;
        form.photo_source_path = null;
        photoPreview.value = URL.createObjectURL(file);
    }
}

function removePhoto() {
    form.photo = null;
    form.photo_source_path = null;
    photoPreview.value = null;
}

function onMapUpdate(lat: number | null, lng: number | null) {
    form.latitude = lat;
    form.longitude = lng;
}

function onProviderUpdate(p: 'yandex' | 'google' | 'osm') {
    form.map_provider = p;
}

// Picker harakatidan kelgan reverse-geocode natijasi: agar yangilanish bo'lsa overwrite qilamiz
function onAddressFill(addr: { region: string | null; district: string | null; address: string | null }) {
    if (addr.region && addr.region !== form.region) {
        form.region = addr.region;
    }

    if (addr.district && addr.district !== form.district) {
        form.district = addr.district;
    }

    if (addr.address && addr.address !== form.address) {
        form.address = addr.address;
    }
}

function submit() {
    form.post('/dealer/shops', { forceFormData: true });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.shopsForm.createHeadTitle')" />

    <div class="mx-auto w-full max-w-5xl p-4 md:p-8">
        <!-- Header -->
        <div class="mb-6 flex items-center gap-3 sm:mb-8 sm:gap-4">
            <Button variant="ghost" size="icon" class="shrink-0" @click="router.get('/dealer/shops')">
                <ArrowLeft class="h-5 w-5" />
            </Button>
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageDealer.shopsForm.createTitle') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('pageDealer.shopsForm.createSubtitle') }}</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Aloqa (telefon orqali bazadan qidirish) -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <Phone class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageDealer.shopsForm.contact') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('pageDealer.shopsForm.contactHint') }}
                            </p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="grid gap-4 p-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <Label for="phone" class="mb-1.5 flex items-center gap-1.5">
                                <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageDealer.shopsForm.phone') }} <span class="text-destructive">*</span>
                            </Label>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <PhoneInput v-model="form.phone" :error="!!form.errors.phone" />
                                </div>
                                <Button
                                    type="button"
                                    :disabled="phoneLookupStatus === 'loading' || !form.phone"
                                    @click="lookupPhone"
                                >
                                    <Spinner v-if="phoneLookupStatus === 'loading'" class="mr-2 h-4 w-4" />
                                    <Search v-else class="mr-2 h-4 w-4" />
                                    {{ t('pageDealer.shopsForm.find') }}
                                </Button>
                            </div>
                            <p
                                v-if="phoneLookupMessage"
                                class="mt-1.5 text-xs"
                                :class="{
                                    'text-emerald-600': phoneLookupStatus === 'success',
                                    'text-amber-600': phoneLookupStatus === 'notfound' || phoneLookupStatus === 'choose',
                                    'text-destructive': phoneLookupStatus === 'error',
                                }"
                            >
                                {{ phoneLookupMessage }}
                            </p>
                            <div v-if="phoneLookupStatus === 'choose' && phoneLookupResults.length" class="mt-2 space-y-1.5">
                                <button
                                    v-for="shop in phoneLookupResults"
                                    :key="shop.id"
                                    type="button"
                                    class="flex w-full items-start gap-3 rounded-md border bg-card p-2.5 text-left text-sm transition-colors hover:border-primary hover:bg-muted/50"
                                    @click="applyPhoneMatch(shop)"
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
                                        <div v-if="shop.contact_person" class="truncate text-xs text-muted-foreground">
                                            {{ shop.contact_person }}
                                        </div>
                                    </div>
                                </button>
                            </div>
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="sm:col-span-2">
                            <Label for="contact_person" class="mb-1.5 flex items-center gap-1.5">
                                <User class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageDealer.shopsForm.contactPerson') }}
                            </Label>
                            <Input id="contact_person" v-model="form.contact_person" :placeholder="t('pageDealer.shopsForm.contactPersonPlaceholder')" />
                            <InputError :message="form.errors.contact_person" />
                        </div>
                    </CardContent>
                </Card>
            </div>

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
                                v-model="form.inn"
                                :placeholder="t('pageDealer.shopsForm.innPlaceholder')"
                                inputmode="numeric"
                                maxlength="9"
                                pattern="\d{9}"
                                class="font-mono"
                                @keydown.enter.prevent="lookupInn"
                            />
                            <Button
                                type="button"
                                :disabled="innLookupStatus === 'loading' || !form.inn"
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
                        <InputError :message="form.errors.inn" />
                    </CardContent>
                </Card>
            </div>

            <!-- Asosiy ma'lumotlar -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <Store class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageDealer.shopsForm.basicInfo') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageDealer.shopsForm.basicInfoHintCreate') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-5 p-6">
                        <div>
                            <Label for="name" class="mb-1.5">
                                {{ t('pageDealer.shopsForm.shopName') }} <span class="text-destructive">*</span>
                            </Label>
                            <Input id="name" v-model="form.name" :placeholder="t('pageDealer.shopsForm.shopNamePlaceholder')" required />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div>
                            <Label for="legal_name" class="mb-1.5 flex items-center gap-1.5">
                                <Building2 class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageDealer.shopsForm.legalName') }}
                            </Label>
                            <Input id="legal_name" v-model="form.legal_name" :placeholder="t('pageDealer.shopsForm.legalNamePlaceholder')" />
                            <InputError :message="form.errors.legal_name" />
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
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageDealer.shopsForm.locationHintCreate') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-5 p-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label class="mb-1.5">{{ t('pageDealer.shopsForm.region') }}</Label>
                                <SearchableSelect
                                    v-model="form.region"
                                    :items="props.regions"
                                    value-key="name"
                                    label-key="name"
                                    :placeholder="t('pageDealer.shopsForm.selectPlaceholder')"
                                    :search-placeholder="t('pageDealer.shopsForm.regionSearch')"
                                    :empty-text="t('pageDealer.shopsForm.regionEmpty')"
                                    @change="onRegionChange"
                                />
                                <InputError :message="form.errors.region" />
                            </div>
                            <div>
                                <Label class="mb-1.5">{{ t('pageDealer.shopsForm.district') }}</Label>
                                <SearchableSelect
                                    v-model="form.district"
                                    :items="districtItems"
                                    :disabled="!form.region"
                                    :placeholder="t('pageDealer.shopsForm.selectPlaceholder')"
                                    :search-placeholder="t('pageDealer.shopsForm.districtSearch')"
                                    :empty-text="t('pageDealer.shopsForm.districtEmpty')"
                                />
                                <InputError :message="form.errors.district" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="address" class="mb-1.5">{{ t('pageDealer.shopsForm.address') }}</Label>
                                <Input id="address" v-model="form.address" :placeholder="t('pageDealer.shopsForm.addressPlaceholder')" />
                                <InputError :message="form.errors.address" />
                            </div>
                            <div>
                                <Label for="landmark" class="mb-1.5">{{ t('pageDealer.shopsForm.landmark') }}</Label>
                                <Input id="landmark" v-model="form.landmark" :placeholder="t('pageDealer.shopsForm.landmarkPlaceholder')" />
                                <InputError :message="form.errors.landmark" />
                            </div>
                        </div>

                        <div>
                            <Label class="mb-2 flex items-center gap-1.5">
                                <MapPin class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageDealer.shopsForm.markOnMap') }}
                                <span class="text-rose-500">*</span>
                            </Label>
                            <LocationPicker
                                :latitude="form.latitude"
                                :longitude="form.longitude"
                                :provider="form.map_provider"
                                :default-center="mapCenter"
                                :default-zoom="mapDefaults?.zoom ?? 13"
                                @update="onMapUpdate"
                                @update:provider="onProviderUpdate"
                                @address="onAddressFill"
                            />
                            <InputError :message="form.errors.latitude || form.errors.longitude" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Rasm + Yetkazib beruvchi -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <Camera class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageDealer.shopsForm.photoAndAssign') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ canPickDeliveryman ? t('pageDealer.shopsForm.photoHintWithDeliveryman') : t('pageDealer.shopsForm.photoHint') }}
                            </p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-5 p-6">
                        <div>
                            <Label class="mb-2">{{ t('pageDealer.shopsForm.shopPhoto') }}</Label>
                            <div class="flex gap-4">
                                <label class="group flex h-28 w-28 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed transition-colors hover:border-primary hover:bg-muted/50">
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
                            <InputError :message="form.errors.photo" />
                        </div>

                        <div v-if="canPickDeliveryman && props.deliverymen">
                            <Label class="mb-1.5 flex items-center gap-1.5">
                                <Truck class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageDealer.shopsForm.deliveryman') }}
                            </Label>
                            <SearchableSelect
                                v-model="form.deliveryman_id"
                                :items="props.deliverymen.data"
                                value-key="id"
                                label-key="name"
                                :placeholder="t('pageDealer.shopsForm.notSelectedPlaceholder')"
                                :search-placeholder="t('pageDealer.shopsForm.deliverymanSearch')"
                                :empty-text="t('pageDealer.shopsForm.notFound')"
                            >
                                <template #item-suffix="{ item }">
                                    <span class="shrink-0 text-[11px] text-muted-foreground">@{{ item.username }}</span>
                                </template>
                            </SearchableSelect>
                        </div>

                        <div v-if="props.parentShops.length">
                            <Label class="mb-1.5 flex items-center gap-1.5">
                                <Store class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageDealer.shopsForm.mainBranch') }}
                            </Label>
                            <SearchableSelect
                                v-model="form.parent_shop_id"
                                :items="parentShopItems"
                                :placeholder="t('pageDealer.shopsForm.independentPlaceholder')"
                                :search-placeholder="t('pageDealer.shopsForm.mainShopSearch')"
                                :empty-text="t('pageDealer.shopsForm.notFound')"
                            >
                                <template #item-suffix="{ item }">
                                    <span class="shrink-0 text-[11px] text-muted-foreground">{{ item.meta }}</span>
                                </template>
                            </SearchableSelect>
                            <p class="mt-1.5 text-xs text-muted-foreground">
                                {{ t('pageDealer.shopsForm.mainBranchHint') }}
                            </p>
                            <InputError :message="form.errors.parent_shop_id" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Action bar -->
            <div class="sticky bottom-0 z-10 -mx-4 flex items-center justify-end gap-3 border-t bg-background/80 px-4 py-4 backdrop-blur md:-mx-8 md:px-8">
                <Button variant="outline" type="button" @click="router.get('/dealer/shops')">{{ t('pageDealer.shopsForm.cancel') }}</Button>
                <Button type="submit" :disabled="form.processing" class="min-w-[140px]">
                    <Spinner v-if="form.processing" class="mr-2" />
                    {{ form.processing ? t('pageDealer.shopsForm.saving') : t('pageDealer.shopsForm.save') }}
                </Button>
            </div>
        </form>
    </div>
</template>
