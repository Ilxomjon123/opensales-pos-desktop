<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Building2, Camera, MapPin, Phone, Store, User, X } from 'lucide-vue-next';
import { computed, defineAsyncComponent, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
const LocationPicker = defineAsyncComponent(() => import('@/components/LocationPicker.vue'));
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as indexRoute, resolveMapLink, reverseGeocode, update as updateRoute } from '@/routes/admin/directory';

type RegionOption = { name: string; districts: string[] };
type Entry = {
    id: number;
    name: string;
    legal_name: string | null;
    inn: string | null;
    phone: string | null;
    contact_person: string | null;
    address: string | null;
    landmark: string | null;
    region: string | null;
    district: string | null;
    latitude: number | null;
    longitude: number | null;
    photo: string | null;
    photo_url: string | null;
};

const { t } = useI18n();

const props = defineProps<{
    entry: Entry;
    regions: RegionOption[];
}>();

const form = useForm({
    name: props.entry.name ?? '',
    legal_name: props.entry.legal_name ?? '',
    phone: props.entry.phone ?? '',
    contact_person: props.entry.contact_person ?? '',
    address: props.entry.address ?? '',
    landmark: props.entry.landmark ?? '',
    region: props.entry.region ?? '',
    district: props.entry.district ?? '',
    inn: props.entry.inn ?? '',
    latitude: props.entry.latitude,
    longitude: props.entry.longitude,
    map_provider: 'yandex' as 'yandex' | 'google' | 'osm',
    photo: null as File | null,
    remove_photo: false,
});

const availableDistricts = computed<string[]>(() => {
    const found = props.regions.find((r) => r.name === form.region);

    return found?.districts ?? [];
});

const districtItems = computed(() => availableDistricts.value.map((d) => ({ value: d, label: d })));

const photoPreview = ref<string | null>(props.entry.photo_url);

function onRegionChange() {
    form.district = '';
}

function onPhotoChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];

    if (file) {
        form.photo = file;
        form.remove_photo = false;
        photoPreview.value = URL.createObjectURL(file);
    }
}

function removePhoto() {
    form.photo = null;
    form.remove_photo = true;
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
    form.transform((data) => ({ ...data, _method: 'patch' })).post(updateRoute(props.entry.id).url, { forceFormData: true });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.directoryEdit.headTitle')" />

    <div class="mx-auto w-full max-w-5xl p-4 md:p-8">
        <div class="mb-6 flex items-center gap-3 sm:mb-8 sm:gap-4">
            <Button variant="ghost" size="icon" class="shrink-0" @click="router.get(indexRoute().url)">
                <ArrowLeft class="h-5 w-5" />
            </Button>
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageAdmin.directoryEdit.title') }}</h1>
                <p class="text-sm text-muted-foreground">{{ props.entry.name }}</p>
            </div>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <!-- Asosiy -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary"><Store class="h-5 w-5" /></div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageAdmin.directoryEdit.mainSectionTitle') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageAdmin.directoryEdit.mainSectionHint') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-5 p-6">
                        <div>
                            <Label for="name" class="mb-1.5">{{ t('pageAdmin.directoryEdit.nameLabel') }} <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div>
                            <Label for="legal_name" class="mb-1.5 flex items-center gap-1.5">
                                <Building2 class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageAdmin.directoryEdit.legalName') }}
                            </Label>
                            <Input id="legal_name" v-model="form.legal_name" />
                            <InputError :message="form.errors.legal_name" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="inn" class="mb-1.5">{{ t('pageAdmin.directoryEdit.innLabel') }}</Label>
                                <Input id="inn" v-model="form.inn" maxlength="9" inputmode="numeric" class="font-mono" />
                                <InputError :message="form.errors.inn" />
                            </div>
                            <div>
                                <Label for="phone" class="mb-1.5 flex items-center gap-1.5">
                                    <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                                    {{ t('pageAdmin.directoryEdit.phoneLabel') }}
                                </Label>
                                <PhoneInput v-model="form.phone" :error="!!form.errors.phone" />
                                <InputError :message="form.errors.phone" />
                            </div>
                        </div>
                        <div>
                            <Label for="contact_person" class="mb-1.5 flex items-center gap-1.5">
                                <User class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageAdmin.directoryEdit.contactPerson') }}
                            </Label>
                            <Input id="contact_person" v-model="form.contact_person" />
                            <InputError :message="form.errors.contact_person" />
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
                            <h3 class="font-semibold">{{ t('pageAdmin.directoryEdit.locationSectionTitle') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageAdmin.directoryEdit.locationSectionHint') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-5 p-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label class="mb-1.5">{{ t('pageAdmin.directoryEdit.region') }}</Label>
                                <SearchableSelect
                                    v-model="form.region"
                                    :items="props.regions"
                                    value-key="name"
                                    label-key="name"
                                    :placeholder="t('pageAdmin.directoryEdit.selectPlaceholder')"
                                    :search-placeholder="t('pageAdmin.directoryEdit.regionSearch')"
                                    :empty-text="t('pageAdmin.directoryEdit.regionEmpty')"
                                    @change="onRegionChange"
                                />
                                <InputError :message="form.errors.region" />
                            </div>
                            <div>
                                <Label class="mb-1.5">{{ t('pageAdmin.directoryEdit.district') }}</Label>
                                <SearchableSelect
                                    v-model="form.district"
                                    :items="districtItems"
                                    :disabled="!form.region"
                                    :placeholder="t('pageAdmin.directoryEdit.selectPlaceholder')"
                                    :search-placeholder="t('pageAdmin.directoryEdit.districtSearch')"
                                    :empty-text="t('pageAdmin.directoryEdit.districtEmpty')"
                                />
                                <InputError :message="form.errors.district" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="address" class="mb-1.5">{{ t('pageAdmin.directoryEdit.address') }}</Label>
                                <Input id="address" v-model="form.address" />
                                <InputError :message="form.errors.address" />
                            </div>
                            <div>
                                <Label for="landmark" class="mb-1.5">{{ t('pageAdmin.directoryEdit.landmark') }}</Label>
                                <Input id="landmark" v-model="form.landmark" />
                                <InputError :message="form.errors.landmark" />
                            </div>
                        </div>

                        <div>
                            <Label class="mb-2 flex items-center gap-1.5">
                                <MapPin class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageAdmin.directoryEdit.mapPick') }}
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
                            <h3 class="font-semibold">{{ t('pageAdmin.directoryEdit.photoSectionTitle') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('pageAdmin.directoryEdit.photoSectionHint') }}</p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-5 p-6">
                        <div>
                            <Label class="mb-2">{{ t('pageAdmin.directoryEdit.photoLabel') }}</Label>
                            <div class="flex gap-4">
                                <label class="group flex h-28 w-28 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed transition-colors hover:border-primary hover:bg-muted/50">
                                    <img v-if="photoPreview" :src="photoPreview" class="h-full w-full object-cover" />
                                    <template v-else>
                                        <Camera class="mb-1 h-6 w-6 text-muted-foreground/50 group-hover:text-primary" />
                                        <span class="text-[10px] text-muted-foreground">{{ t('pageAdmin.directoryEdit.photoShort') }}</span>
                                    </template>
                                    <input type="file" accept="image/*" class="hidden" @change="onPhotoChange" />
                                </label>
                                <div class="flex-1 space-y-2 text-sm text-muted-foreground">
                                    <p>{{ t('pageAdmin.directoryEdit.photoFormatHint') }}</p>
                                    <Button v-if="photoPreview" variant="ghost" size="sm" type="button" class="text-destructive" @click="removePhoto">
                                        <X class="mr-1 h-3.5 w-3.5" />
                                        {{ t('pageAdmin.directoryEdit.photoRemove') }}
                                    </Button>
                                </div>
                            </div>
                            <InputError :message="form.errors.photo" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="sticky bottom-0 z-10 -mx-4 flex items-center justify-end gap-3 border-t bg-background/80 px-4 py-4 backdrop-blur md:-mx-8 md:px-8">
                <Button variant="outline" type="button" @click="router.get(indexRoute().url)">{{ t('pageAdmin.directoryEdit.cancel') }}</Button>
                <Button type="submit" :disabled="form.processing" class="min-w-[140px]">
                    <Spinner v-if="form.processing" class="mr-2" />
                    {{ form.processing ? t('pageAdmin.directoryEdit.saving') : t('pageAdmin.directoryEdit.save') }}
                </Button>
            </div>
        </form>
    </div>
</template>
