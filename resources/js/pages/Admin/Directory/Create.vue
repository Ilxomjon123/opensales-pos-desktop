<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Building2, Camera, MapPin, Phone, Search, Store, User, X } from 'lucide-vue-next';
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
import { innLookup, phoneLookup, resolveMapLink, reverseGeocode, store as storeRoute } from '@/routes/admin/directory';

type RegionOption = { name: string; districts: string[] };

const { t } = useI18n();

const props = defineProps<{
    regions: RegionOption[];
}>();

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
});

const availableDistricts = computed<string[]>(() => {
    const found = props.regions.find((r) => r.name === form.region);

    return found?.districts ?? [];
});

const districtItems = computed(() => availableDistricts.value.map((d) => ({ value: d, label: d })));

type DirectoryEntry = {
    id: number;
    name: string;
    legal_name: string | null;
    phone: string | null;
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
};

const innLookupStatus = ref<'idle' | 'loading' | 'success' | 'exists' | 'notfound' | 'error'>('idle');
const innLookupMessage = ref('');
const phoneLookupStatus = ref<'idle' | 'loading' | 'success' | 'notfound' | 'error' | 'choose'>('idle');
const phoneLookupMessage = ref('');
const phoneLookupResults = ref<DirectoryEntry[]>([]);
const innLookupResults = ref<DirectoryEntry[]>([]);

let phoneLookupTimer: ReturnType<typeof setTimeout> | null = null;
let innLookupTimer: ReturnType<typeof setTimeout> | null = null;
let lastLookedUpPhone = '';
let lastLookedUpInn = '';

const photoPreview = ref<string | null>(null);

function fillFromEntry(entry: DirectoryEntry) {
    if (entry.name) {
form.name = entry.name;
}

    if (entry.legal_name) {
form.legal_name = entry.legal_name;
}

    if (entry.phone) {
        form.phone = entry.phone;
        lastLookedUpPhone = entry.phone.trim();
    }

    if (entry.contact_person) {
form.contact_person = entry.contact_person;
}

    if (entry.address) {
form.address = entry.address;
}

    if (entry.landmark) {
form.landmark = entry.landmark;
}

    if (entry.region) {
form.region = entry.region;
}

    if (entry.district) {
form.district = entry.district;
}

    if (entry.inn) {
        form.inn = entry.inn;
        lastLookedUpInn = entry.inn.replace(/\D+/g, '');
    }

    if (entry.latitude != null) {
form.latitude = entry.latitude;
}

    if (entry.longitude != null) {
form.longitude = entry.longitude;
}

    if (entry.photo && entry.photo_url) {
        form.photo = null;
        form.photo_source_path = entry.photo;
        photoPreview.value = entry.photo_url;
    }
}

function applyPhoneMatch(entry: DirectoryEntry) {
    fillFromEntry(entry);
    phoneLookupResults.value = [];
    phoneLookupStatus.value = 'success';
    phoneLookupMessage.value = t('pageAdmin.directoryCreate.phoneExists', { name: entry.name });
}

function applyInnMatch(entry: DirectoryEntry) {
    fillFromEntry(entry);
    innLookupResults.value = [];
    innLookupStatus.value = 'exists';
    innLookupMessage.value = t('pageAdmin.directoryCreate.innExists', { name: entry.name });
}

async function lookupPhone() {
    const phone = form.phone.trim();
    const digits = phone.replace(/\D+/g, '');

    if (digits.length < 7) {
        phoneLookupStatus.value = 'error';
        phoneLookupMessage.value = t('pageAdmin.directoryCreate.phoneIncomplete');
        phoneLookupResults.value = [];

        return;
    }

    phoneLookupStatus.value = 'loading';
    phoneLookupMessage.value = '';
    phoneLookupResults.value = [];
    lastLookedUpPhone = phone;

    try {
        const res = await fetch(phoneLookup({ query: { phone } }).url, {
            headers: { Accept: 'application/json' },
        });

        if (res.status === 404) {
            phoneLookupStatus.value = 'notfound';
            phoneLookupMessage.value = t('pageAdmin.directoryCreate.phoneNotFound');

            return;
        }

        if (!res.ok) {
            const body = await res.json().catch(() => ({ message: t('pageAdmin.directoryCreate.error') }));
            phoneLookupStatus.value = 'error';
            phoneLookupMessage.value = body.message ?? t('pageAdmin.directoryCreate.error');

            return;
        }

        const data = (await res.json()) as { shops: DirectoryEntry[] };
        const shops = data.shops ?? [];

        if (shops.length === 0) {
            phoneLookupStatus.value = 'notfound';
            phoneLookupMessage.value = t('pageAdmin.directoryCreate.phoneNotFound');

            return;
        }

        if (shops.length === 1) {
            applyPhoneMatch(shops[0]);

            return;
        }

        phoneLookupStatus.value = 'choose';
        phoneLookupMessage.value = t('pageAdmin.directoryCreate.multipleFound', { count: shops.length });
        phoneLookupResults.value = shops;
    } catch {
        phoneLookupStatus.value = 'error';
        phoneLookupMessage.value = t('pageAdmin.directoryCreate.networkError');
    }
}

async function lookupInn() {
    const inn = form.inn.trim();

    if (!/^\d{9}$/.test(inn)) {
        innLookupStatus.value = 'error';
        innLookupMessage.value = t('pageAdmin.directoryCreate.innInvalid');

        return;
    }

    innLookupStatus.value = 'loading';
    innLookupMessage.value = '';
    innLookupResults.value = [];
    lastLookedUpInn = inn;

    try {
        const res = await fetch(innLookup(inn).url, {
            headers: { Accept: 'application/json' },
        });

        if (!res.ok) {
            const body = await res.json().catch(() => ({ message: t('pageAdmin.directoryCreate.serviceDown') }));
            innLookupStatus.value = 'error';
            innLookupMessage.value = body.message ?? t('pageAdmin.directoryCreate.error');

            return;
        }

        const data = (await res.json()) as
            | { shops: DirectoryEntry[] }
            | { name: string | null; legal_name: string | null; region: string | null; district: string | null; address: string | null };

        // Bazada bor → mavjud mijoz, ogohlantiramiz.
        if ('shops' in data) {
            const shops = data.shops ?? [];

            if (shops.length === 1) {
                applyInnMatch(shops[0]);

                return;
            }

            if (shops.length > 1) {
                innLookupStatus.value = 'choose';
                innLookupMessage.value = t('pageAdmin.directoryCreate.multipleFound', { count: shops.length });
                innLookupResults.value = shops;

                return;
            }
        }

        // Bazada yo'q → orginfo.uz dan keladi.
        const orgInfo = data as { name: string | null; legal_name: string | null; region: string | null; district: string | null; address: string | null };

        if (!orgInfo.name) {
            innLookupStatus.value = 'notfound';
            innLookupMessage.value = t('pageAdmin.directoryCreate.innNotFound');

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
        innLookupMessage.value = t('pageAdmin.directoryCreate.innFound', { name: orgInfo.name });
    } catch {
        innLookupStatus.value = 'error';
        innLookupMessage.value = t('pageAdmin.directoryCreate.networkError');
    }
}

watch(() => form.phone, (val) => {
    if (phoneLookupTimer) {
clearTimeout(phoneLookupTimer);
}

    const trimmed = (val ?? '').trim();
    const digits = trimmed.replace(/\D+/g, '');

    if (digits.length < 9 || trimmed === lastLookedUpPhone || phoneLookupStatus.value === 'loading') {
        return;
    }

    phoneLookupTimer = setTimeout(() => lookupPhone(), 500);
});

watch(() => form.inn, (val) => {
    if (innLookupTimer) {
clearTimeout(innLookupTimer);
}

    const trimmed = (val ?? '').trim();

    if (!/^\d{9}$/.test(trimmed) || trimmed === lastLookedUpInn || innLookupStatus.value === 'loading') {
        return;
    }

    innLookupTimer = setTimeout(() => lookupInn(), 500);
});

function onRegionChange() {
    form.district = '';
}

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
    form.post(storeRoute().url, { forceFormData: true });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.directoryCreate.headTitle')" />

    <div class="mx-auto w-full max-w-5xl p-4 md:p-8">
        <div class="mb-6 flex items-center gap-3 sm:mb-8 sm:gap-4">
            <Button variant="ghost" size="icon" class="shrink-0" @click="router.get('/admin/directory')">
                <ArrowLeft class="h-5 w-5" />
            </Button>
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageAdmin.directoryCreate.title') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('pageAdmin.directoryCreate.subtitle') }}</p>
            </div>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <!-- STIR -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary"><Search class="h-5 w-5" /></div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageAdmin.directoryCreate.innSectionTitle') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('pageAdmin.directoryCreate.innSectionHint') }}
                            </p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-2 p-6">
                        <Label for="inn" class="mb-1.5 flex items-center gap-1.5">
                            <Building2 class="h-3.5 w-3.5 text-muted-foreground" />
                            {{ t('pageAdmin.directoryCreate.innLabel') }}
                        </Label>
                        <div class="flex gap-2">
                            <Input
                                id="inn"
                                v-model="form.inn"
                                :placeholder="t('pageAdmin.directoryCreate.innPlaceholder')"
                                inputmode="numeric"
                                maxlength="9"
                                class="font-mono"
                                @keydown.enter.prevent="lookupInn"
                            />
                            <Button type="button" :disabled="innLookupStatus === 'loading' || !form.inn" @click="lookupInn">
                                <Spinner v-if="innLookupStatus === 'loading'" class="mr-2 h-4 w-4" />
                                <Search v-else class="mr-2 h-4 w-4" />
                                {{ t('pageAdmin.directoryCreate.lookup') }}
                            </Button>
                        </div>
                        <p
                            v-if="innLookupMessage"
                            class="text-xs"
                            :class="{
                                'text-emerald-600': innLookupStatus === 'success',
                                'text-amber-600': innLookupStatus === 'notfound' || innLookupStatus === 'choose' || innLookupStatus === 'exists',
                                'text-destructive': innLookupStatus === 'error',
                            }"
                        >
                            {{ innLookupMessage }}
                        </p>
                        <div v-if="innLookupStatus === 'choose' && innLookupResults.length" class="space-y-1.5">
                            <button
                                v-for="entry in innLookupResults"
                                :key="entry.id"
                                type="button"
                                class="flex w-full items-start gap-3 rounded-md border bg-card p-2.5 text-left text-sm transition-colors hover:border-primary hover:bg-muted/50"
                                @click="applyInnMatch(entry)"
                            >
                                <img v-if="entry.photo_url" :src="entry.photo_url" class="h-10 w-10 shrink-0 rounded object-cover" alt="" />
                                <div class="min-w-0 flex-1">
                                    <span class="truncate font-medium">{{ entry.name }}</span>
                                    <div class="mt-0.5 truncate text-xs text-muted-foreground">
                                        {{ [entry.region, entry.district, entry.address].filter(Boolean).join(', ') || t('pageAdmin.directoryCreate.addressNotSet') }}
                                    </div>
                                    <div v-if="entry.phone" class="truncate text-xs text-muted-foreground">{{ entry.phone }}</div>
                                </div>
                            </button>
                        </div>
                        <InputError :message="form.errors.inn" />
                    </CardContent>
                </Card>
            </div>

            <!-- Aloqa -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary"><Phone class="h-5 w-5" /></div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageAdmin.directoryCreate.contactSectionTitle') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageAdmin.directoryCreate.contactSectionHint') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="grid gap-4 p-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <Label for="phone" class="mb-1.5 flex items-center gap-1.5">
                                <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageAdmin.directoryCreate.phoneLabel') }}
                            </Label>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <PhoneInput v-model="form.phone" :error="!!form.errors.phone" />
                                </div>
                                <Button type="button" :disabled="phoneLookupStatus === 'loading' || !form.phone" @click="lookupPhone">
                                    <Spinner v-if="phoneLookupStatus === 'loading'" class="mr-2 h-4 w-4" />
                                    <Search v-else class="mr-2 h-4 w-4" />
                                    {{ t('pageAdmin.directoryCreate.lookup') }}
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
                                    v-for="entry in phoneLookupResults"
                                    :key="entry.id"
                                    type="button"
                                    class="flex w-full items-start gap-3 rounded-md border bg-card p-2.5 text-left text-sm transition-colors hover:border-primary hover:bg-muted/50"
                                    @click="applyPhoneMatch(entry)"
                                >
                                    <img v-if="entry.photo_url" :src="entry.photo_url" class="h-10 w-10 shrink-0 rounded object-cover" alt="" />
                                    <div class="min-w-0 flex-1">
                                        <span class="truncate font-medium">{{ entry.name }}</span>
                                        <div class="mt-0.5 truncate text-xs text-muted-foreground">
                                            {{ [entry.region, entry.district, entry.address].filter(Boolean).join(', ') || t('pageAdmin.directoryCreate.addressNotSet') }}
                                        </div>
                                    </div>
                                </button>
                            </div>
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="sm:col-span-2">
                            <Label for="contact_person" class="mb-1.5 flex items-center gap-1.5">
                                <User class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageAdmin.directoryCreate.contactPerson') }}
                            </Label>
                            <Input id="contact_person" v-model="form.contact_person" :placeholder="t('pageAdmin.directoryCreate.contactPersonPlaceholder')" />
                            <InputError :message="form.errors.contact_person" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Asosiy -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary"><Store class="h-5 w-5" /></div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageAdmin.directoryCreate.mainSectionTitle') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageAdmin.directoryCreate.mainSectionHint') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-5 p-6">
                        <div>
                            <Label for="name" class="mb-1.5">{{ t('pageAdmin.directoryCreate.nameLabel') }} <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="form.name" :placeholder="t('pageAdmin.directoryCreate.namePlaceholder')" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div>
                            <Label for="legal_name" class="mb-1.5 flex items-center gap-1.5">
                                <Building2 class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageAdmin.directoryCreate.legalName') }}
                            </Label>
                            <Input id="legal_name" v-model="form.legal_name" :placeholder="t('pageAdmin.directoryCreate.legalNamePlaceholder')" />
                            <InputError :message="form.errors.legal_name" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Joylashuv -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary"><MapPin class="h-5 w-5" /></div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageAdmin.directoryCreate.locationSectionTitle') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageAdmin.directoryCreate.locationSectionHint') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-5 p-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label class="mb-1.5">{{ t('pageAdmin.directoryCreate.region') }}</Label>
                                <SearchableSelect
                                    v-model="form.region"
                                    :items="props.regions"
                                    value-key="name"
                                    label-key="name"
                                    :placeholder="t('pageAdmin.directoryCreate.selectPlaceholder')"
                                    :search-placeholder="t('pageAdmin.directoryCreate.regionSearch')"
                                    :empty-text="t('pageAdmin.directoryCreate.regionEmpty')"
                                    @change="onRegionChange"
                                />
                                <InputError :message="form.errors.region" />
                            </div>
                            <div>
                                <Label class="mb-1.5">{{ t('pageAdmin.directoryCreate.district') }}</Label>
                                <SearchableSelect
                                    v-model="form.district"
                                    :items="districtItems"
                                    :disabled="!form.region"
                                    :placeholder="t('pageAdmin.directoryCreate.selectPlaceholder')"
                                    :search-placeholder="t('pageAdmin.directoryCreate.districtSearch')"
                                    :empty-text="t('pageAdmin.directoryCreate.districtEmpty')"
                                />
                                <InputError :message="form.errors.district" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="address" class="mb-1.5">{{ t('pageAdmin.directoryCreate.address') }}</Label>
                                <Input id="address" v-model="form.address" :placeholder="t('pageAdmin.directoryCreate.addressPlaceholder')" />
                                <InputError :message="form.errors.address" />
                            </div>
                            <div>
                                <Label for="landmark" class="mb-1.5">{{ t('pageAdmin.directoryCreate.landmark') }}</Label>
                                <Input id="landmark" v-model="form.landmark" :placeholder="t('pageAdmin.directoryCreate.landmarkPlaceholder')" />
                                <InputError :message="form.errors.landmark" />
                            </div>
                        </div>

                        <div>
                            <Label class="mb-2 flex items-center gap-1.5">
                                <MapPin class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageAdmin.directoryCreate.mapPick') }}
                            </Label>
                            <LocationPicker
                                :latitude="form.latitude"
                                :longitude="form.longitude"
                                :provider="form.map_provider"
                                :reverse-geocode-url="reverseGeocode().url"
                                :resolve-map-link-url="resolveMapLink().url"
                                @update="onMapUpdate"
                                @update:provider="onProviderUpdate"
                                @address="onAddressFill"
                            />
                            <InputError :message="form.errors.latitude || form.errors.longitude" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Rasm -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary"><Camera class="h-5 w-5" /></div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageAdmin.directoryCreate.photoSectionTitle') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageAdmin.directoryCreate.photoSectionHint') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-5 p-6">
                        <div>
                            <Label class="mb-2">{{ t('pageAdmin.directoryCreate.photoLabel') }}</Label>
                            <div class="flex gap-4">
                                <label class="group flex h-28 w-28 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed transition-colors hover:border-primary hover:bg-muted/50">
                                    <img v-if="photoPreview" :src="photoPreview" class="h-full w-full object-cover" />
                                    <template v-else>
                                        <Camera class="mb-1 h-6 w-6 text-muted-foreground/50 group-hover:text-primary" />
                                        <span class="text-[10px] text-muted-foreground">{{ t('pageAdmin.directoryCreate.photoShort') }}</span>
                                    </template>
                                    <input type="file" accept="image/*" class="hidden" @change="onPhotoChange" />
                                </label>
                                <div class="flex-1 space-y-2 text-sm text-muted-foreground">
                                    <p>{{ t('pageAdmin.directoryCreate.photoFormatHint') }}</p>
                                    <Button v-if="photoPreview" variant="ghost" size="sm" type="button" class="text-destructive" @click="removePhoto">
                                        <X class="mr-1 h-3.5 w-3.5" />
                                        {{ t('pageAdmin.directoryCreate.photoRemove') }}
                                    </Button>
                                </div>
                            </div>
                            <InputError :message="form.errors.photo" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="sticky bottom-0 z-10 -mx-4 flex items-center justify-end gap-3 border-t bg-background/80 px-4 py-4 backdrop-blur md:-mx-8 md:px-8">
                <Button variant="outline" type="button" @click="router.get('/admin/directory')">{{ t('pageAdmin.directoryCreate.cancel') }}</Button>
                <Button type="submit" :disabled="form.processing" class="min-w-[140px]">
                    <Spinner v-if="form.processing" class="mr-2" />
                    {{ form.processing ? t('pageAdmin.directoryCreate.saving') : t('pageAdmin.directoryCreate.save') }}
                </Button>
            </div>
        </form>
    </div>
</template>
