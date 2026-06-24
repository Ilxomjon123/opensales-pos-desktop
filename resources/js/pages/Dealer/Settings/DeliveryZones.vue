<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronDown, Loader2, MapPinned, Save, Search, X } from 'lucide-vue-next';
import { computed, onUnmounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Highlight from '@/components/Highlight.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { translitNormalize } from '@/lib/translit';

const { t } = useI18n();

type RegionOption = { name: string; districts: string[] };
type ZoneSelection = { region: string; districts: string[]; whole_region: boolean };

const props = defineProps<{
    regions: RegionOption[];
    zones: ZoneSelection[];
}>();

// Har viloyat uchun tanlov holati: butun viloyatmi + tanlangan tumanlar.
type RegionState = { whole: boolean; districts: Set<string> };

const selection = reactive<Record<string, RegionState>>(
    Object.fromEntries(
        props.regions.map((r) => {
            const existing = props.zones.find((z) => z.region === r.name);

            return [
                r.name,
                {
                    whole: existing?.whole_region ?? false,
                    districts: new Set(existing && !existing.whole_region ? existing.districts : []),
                },
            ];
        }),
    ),
);

const open = reactive<Record<string, boolean>>({});
const processing = ref(false);
const search = ref(''); // input qiymati (darhol)
const query = ref(''); // filtrlash uchun debounce qilingan qiymat

// Input darhol yangilanadi; og'ir filtrlash 180ms debounce bilan — yozish silliq.
let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(search, (value) => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        query.value = value.trim();
    }, 180);
});

onUnmounted(() => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
});

// Nomlarni BIR MARTA normallashtirib keshlaymiz (har bosishda 1000+ string
// translit qilmaslik uchun). props.regions o'zgarmaydi → faqat bir marta hisob.
const index = computed(() => {
    const regionNorm = new Map<string, string>();
    const districtNorm = new Map<string, string>();

    for (const r of props.regions) {
        regionNorm.set(r.name, translitNormalize(r.name).norm);

        for (const d of r.districts) {
            districtNorm.set(`${r.name}|${d}`, translitNormalize(d).norm);
        }
    }

    return { regionNorm, districtNorm };
});

const queryNorm = computed(() => translitNormalize(query.value).norm);
const hasSearch = computed(() => queryNorm.value !== '');

// Qidiruv: viloyat nomi yoki biror tumani mos kelgan viloyatlar (keshlangan norm).
const filteredRegions = computed<RegionOption[]>(() => {
    const q = queryNorm.value;

    if (q === '') {
        return props.regions;
    }

    const { regionNorm, districtNorm } = index.value;

    return props.regions.filter(
        (r) =>
            (regionNorm.get(r.name) ?? '').includes(q) ||
            r.districts.some((d) => (districtNorm.get(`${r.name}|${d}`) ?? '').includes(q)),
    );
});

function regionMatches(region: RegionOption): boolean {
    return (index.value.regionNorm.get(region.name) ?? '').includes(queryNorm.value);
}

// Viloyat nomi mos bo'lsa — barcha tumanlar; aks holda faqat mos tumanlar.
function visibleDistricts(region: RegionOption): string[] {
    const q = queryNorm.value;

    if (q === '' || regionMatches(region)) {
        return region.districts;
    }

    const { districtNorm } = index.value;

    return region.districts.filter((d) => (districtNorm.get(`${region.name}|${d}`) ?? '').includes(q));
}

// Qidiruvda faqat TUMANI mos (lekin nomi mos emas) viloyatlar avtomatik ochiladi —
// shunda kam tugun render bo'ladi (nomi mos viloyat yopiq qoladi).
function isOpen(region: RegionOption): boolean {
    if (!hasSearch.value) {
        return open[region.name] ?? false;
    }

    return !regionMatches(region);
}

function isActive(region: string): boolean {
    const s = selection[region];

    return s.whole || s.districts.size > 0;
}

function selectedCount(region: string): number {
    const s = selection[region];
    const total = props.regions.find((r) => r.name === region)?.districts.length ?? 0;

    return s.whole ? total : s.districts.size;
}

function toggleWhole(region: string, value: boolean) {
    const s = selection[region];
    s.whole = value;

    if (value) {
        s.districts.clear();
    }
}

function toggleDistrict(region: string, district: string, value: boolean) {
    const s = selection[region];

    if (value) {
        s.districts.add(district);
    } else {
        s.districts.delete(district);
    }

    // Tuman tanlansa "butun viloyat" rejimi o'chadi.
    if (s.districts.size > 0) {
        s.whole = false;
    }
}

const activeRegionCount = computed(() => props.regions.filter((r) => isActive(r.name)).length);

function save() {
    const payload: ZoneSelection[] = props.regions
        .filter((r) => isActive(r.name))
        .map((r) => {
            const s = selection[r.name];

            return {
                region: r.name,
                whole_region: s.whole,
                districts: s.whole ? [] : Array.from(s.districts),
            };
        });

    processing.value = true;

    router.put(
        '/dealer/settings/delivery-zones',
        { zones: payload },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.deliveryZones.headTitle')" />

    <div class="flex flex-col gap-4 p-3 sm:gap-5 sm:p-4 lg:gap-6 lg:p-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                <MapPinned class="h-5 w-5 text-primary" />
            </div>
            <div class="min-w-0">
                <h1 class="text-lg font-bold tracking-tight sm:text-xl lg:text-2xl">
                    {{ t('pageDealer.deliveryZones.title') }}
                </h1>
                <p class="text-xs text-muted-foreground sm:text-sm">
                    {{ t('pageDealer.deliveryZones.subtitle') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 rounded-md border bg-muted/30 px-3 py-2 text-xs text-muted-foreground sm:text-sm">
            <span v-if="activeRegionCount === 0">{{ t('pageDealer.deliveryZones.emptyHint') }}</span>
            <span v-else>{{ t('pageDealer.deliveryZones.activeHint', { count: activeRegionCount }) }}</span>
        </div>

        <div class="relative">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                v-model="search"
                :placeholder="t('pageDealer.deliveryZones.searchPlaceholder')"
                class="pl-9 pr-9"
            />
            <button
                v-if="search !== ''"
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                @click="search = ''"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <Card>
            <CardContent class="divide-y p-0">
                <div
                    v-if="filteredRegions.length === 0"
                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    {{ t('pageDealer.deliveryZones.noResults') }}
                </div>
                <Collapsible
                    v-for="region in filteredRegions"
                    :key="region.name"
                    :open="isOpen(region)"
                    @update:open="(v: boolean) => (open[region.name] = v)"
                >
                    <div class="flex items-center gap-3 px-3 py-3 sm:px-4">
                        <Checkbox
                            :model-value="selection[region.name].whole"
                            @update:model-value="(v) => toggleWhole(region.name, Boolean(v))"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-medium">
                                    <Highlight :text="region.name" :query="query" />
                                </span>
                                <Badge v-if="isActive(region.name)" variant="secondary" class="shrink-0">
                                    {{ selectedCount(region.name) }}/{{ region.districts.length }}
                                </Badge>
                            </div>
                            <span class="text-xs text-muted-foreground">
                                {{ t('pageDealer.deliveryZones.wholeRegion') }}
                            </span>
                        </div>
                        <CollapsibleTrigger as-child>
                            <Button variant="ghost" size="sm" class="shrink-0">
                                <ChevronDown
                                    class="h-4 w-4 transition-transform"
                                    :class="isOpen(region) ? 'rotate-180' : ''"
                                />
                            </Button>
                        </CollapsibleTrigger>
                    </div>
                    <CollapsibleContent>
                        <div
                            v-if="isOpen(region)"
                            class="grid grid-cols-1 gap-x-4 gap-y-2 border-t bg-muted/20 px-3 py-3 sm:grid-cols-2 sm:px-4 lg:grid-cols-3"
                        >
                            <label
                                v-for="district in visibleDistricts(region)"
                                :key="district"
                                class="flex cursor-pointer items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    :model-value="selection[region.name].whole || selection[region.name].districts.has(district)"
                                    :disabled="selection[region.name].whole"
                                    @update:model-value="(v) => toggleDistrict(region.name, district, Boolean(v))"
                                />
                                <span class="truncate" :class="selection[region.name].whole ? 'text-muted-foreground' : ''">
                                    <Highlight :text="district" :query="query" />
                                </span>
                            </label>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            </CardContent>
        </Card>

        <div class="flex justify-end">
            <Button :disabled="processing" @click="save">
                <Loader2 v-if="processing" class="mr-2 h-4 w-4 animate-spin" />
                <Save v-else class="mr-2 h-4 w-4" />
                {{ t('pageDealer.deliveryZones.save') }}
            </Button>
        </div>
    </div>
</template>
