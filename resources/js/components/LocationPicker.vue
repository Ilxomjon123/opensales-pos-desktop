<script setup lang="ts">
// Leaflet `window` ga tayanadi — SSR (Node) da crash beradi. Shu sabab runtime'da
// faqat client tomonda (onMounted) lazy yuklanadi; modul scope'da faqat type ishlatiladi.
import type * as Leaflet from 'leaflet';
import { MapPin, Crosshair, Loader2, Link2, Check, X as XIcon, AlertTriangle } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const { t } = useI18n();

type ProviderKey = 'yandex' | 'google' | 'osm';

const props = withDefaults(defineProps<{
    latitude: number | null;
    longitude: number | null;
    provider?: ProviderKey;
    readonly?: boolean;
    height?: string;
    reverseGeocodeUrl?: string;
    resolveMapLinkUrl?: string;
    defaultCenter?: [number, number] | null;
    defaultZoom?: number;
}>(), {
    provider: 'yandex',
    readonly: false,
    height: 'h-80',
    reverseGeocodeUrl: '/dealer/shops-api/reverse-geocode',
    resolveMapLinkUrl: '/dealer/shops-api/resolve-map-link',
    defaultCenter: null,
    defaultZoom: 13,
});

const emit = defineEmits<{
    update: [lat: number | null, lng: number | null];
    'update:provider': [provider: ProviderKey];
    address: [address: { region: string | null; district: string | null; address: string | null }];
}>();

type CrsKey = 'EPSG3395' | 'EPSG3857';

type Provider = {
    key: ProviderKey;
    label: string;
    url: string;
    attribution: string;
    subdomains?: string[];
    maxZoom?: number;
    crsKey: CrsKey;
};

const providers: Record<ProviderKey, Provider> = {
    yandex: {
        key: 'yandex',
        label: 'Yandex',
        url: 'https://core-renderer-tiles.maps.yandex.net/tiles?l=map&x={x}&y={y}&z={z}&scale=1&lang=ru_RU',
        attribution: '© Yandex',
        maxZoom: 19,
        crsKey: 'EPSG3395',
    },
    google: {
        key: 'google',
        label: 'Google',
        url: 'https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',
        attribution: '© Google',
        subdomains: ['0', '1', '2', '3'],
        maxZoom: 20,
        crsKey: 'EPSG3857',
    },
    osm: {
        key: 'osm',
        label: 'OpenStreet',
        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: '© OpenStreetMap',
        maxZoom: 19,
        crsKey: 'EPSG3857',
    },
};

// Diller davlatiga mos default markaz (prop) — yo'q bo'lsa Toshkent.
const DEFAULT_CENTER: [number, number] = props.defaultCenter ?? [41.3111, 69.2797];

function toProviderKey(value: unknown): ProviderKey {
    return value === 'yandex' || value === 'google' || value === 'osm' ? value : 'yandex';
}

// Client tomonda onMounted'da yuklanadigan Leaflet runtime obyekti.
let L: typeof Leaflet;

const mapContainer = ref<HTMLDivElement>();
let map: Leaflet.Map | null = null;
let marker: Leaflet.Marker | null = null;
let tileLayer: Leaflet.TileLayer | null = null;
let meCircle: Leaflet.Circle | null = null;
let meAccuracy: number | null = null;

const currentProvider = ref<ProviderKey>(toProviderKey(props.provider));
const lat = ref<number | null>(props.latitude);
const lng = ref<number | null>(props.longitude);
const locating = ref(false);
const geoError = ref<string | null>(null);

let reverseGeocodeTimer: ReturnType<typeof setTimeout> | null = null;
let reverseGeocodeAbort: AbortController | null = null;
const geocoding = ref(false);

function scheduleReverseGeocode(latitude: number, longitude: number) {
    if (props.readonly) {
        return;
    }

    if (reverseGeocodeTimer) {
        clearTimeout(reverseGeocodeTimer);
    }

    geocoding.value = true;

    reverseGeocodeTimer = setTimeout(() => {
        void runReverseGeocode(latitude, longitude);
    }, 200);
}

async function runReverseGeocode(latitude: number, longitude: number) {
    if (reverseGeocodeAbort) {
        reverseGeocodeAbort.abort();
    }

    const ctrl = new AbortController();
    reverseGeocodeAbort = ctrl;

    try {
        const res = await fetch(
            `${props.reverseGeocodeUrl}?lat=${encodeURIComponent(latitude)}&lng=${encodeURIComponent(longitude)}`,
            {
                headers: { Accept: 'application/json' },
                signal: ctrl.signal,
            },
        );

        if (!res.ok) {
            return;
        }

        const data = await res.json() as ResolveResponse;

        emit('address', {
            region: data.region ?? null,
            district: data.district ?? null,
            address: data.address ?? null,
        });
    } catch {
        // tarmoq yoki abort xatoligi — jim o'tkazib yuboramiz
    } finally {
        if (reverseGeocodeAbort === ctrl) {
            reverseGeocodeAbort = null;
            geocoding.value = false;
        }
    }
}

function makeMarkerIcon(): Leaflet.DivIcon {
    // Aniq pin shakli — uchi tanlangan koordinataga tegadi
    const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="48" viewBox="0 0 40 48">
            <defs>
                <filter id="pinShadow" x="-50%" y="-20%" width="200%" height="200%">
                    <feGaussianBlur in="SourceAlpha" stdDeviation="1.5" />
                    <feOffset dy="2" />
                    <feComponentTransfer>
                        <feFuncA type="linear" slope="0.35" />
                    </feComponentTransfer>
                    <feMerge>
                        <feMergeNode />
                        <feMergeNode in="SourceGraphic" />
                    </feMerge>
                </filter>
            </defs>
            <g filter="url(#pinShadow)">
                <path d="M20 2 C10 2 3 9 3 19 C3 32 20 46 20 46 C20 46 37 32 37 19 C37 9 30 2 20 2 Z"
                      fill="#ef4444" stroke="#ffffff" stroke-width="2.5"/>
                <circle cx="20" cy="19" r="6" fill="#ffffff" />
                <circle cx="20" cy="19" r="3" fill="#ef4444" />
            </g>
        </svg>
    `;

    return L.divIcon({
        html: `<div class="ltp-pin">${svg}</div>`,
        className: 'ltp-pin-wrapper',
        iconSize: [40, 48],
        // Uchi bottom-center da — tanlangan lat/lng nuqtasiga tegadi
        iconAnchor: [20, 46],
        popupAnchor: [0, -46],
    });
}

function addMarker(latitude: number, longitude: number, pan = true) {
    if (!map) {
return;
}

    if (marker) {
        marker.remove();
        marker = null;
    }

    marker = L.marker([latitude, longitude], {
        icon: makeMarkerIcon(),
        draggable: !props.readonly,
    }).addTo(map);

    if (!props.readonly) {
        marker.on('dragend', () => {
            const p = marker!.getLatLng();
            lat.value = +p.lat.toFixed(7);
            lng.value = +p.lng.toFixed(7);
            emit('update', lat.value, lng.value);
            scheduleReverseGeocode(lat.value, lng.value);
        });
    }

    if (pan) {
map.panTo([latitude, longitude]);
}
}

function updateMarkerPosition(latitude: number, longitude: number, pan = true) {
    if (!map) {
return;
}

    if (!marker) {
        addMarker(latitude, longitude, pan);
    } else {
        marker.setLatLng([latitude, longitude]);

        if (pan) {
map.panTo([latitude, longitude]);
}
    }
}

function addMeCircle(latitude: number, longitude: number, accuracy: number) {
    if (!map) {
return;
}

    if (meCircle) {
        meCircle.remove();
    }

    meCircle = L.circle([latitude, longitude], {
        radius: accuracy,
        color: '#3b82f6',
        fillColor: '#3b82f6',
        fillOpacity: 0.15,
        weight: 1,
    }).addTo(map);
    meAccuracy = accuracy;
}

function buildMap(providerKey: ProviderKey, center: [number, number], zoom: number) {
    if (!mapContainer.value) {
return;
}

    const p = providers[providerKey];

    map = L.map(mapContainer.value, {
        zoomControl: true,
        crs: L.CRS[p.crsKey],
        maxZoom: p.maxZoom ?? 19,
        dragging: !props.readonly,
        touchZoom: !props.readonly,
        scrollWheelZoom: !props.readonly,
        doubleClickZoom: !props.readonly,
        boxZoom: !props.readonly,
        keyboard: !props.readonly,
    }).setView(center, zoom);

    tileLayer = L.tileLayer(p.url, {
        attribution: p.attribution,
        subdomains: p.subdomains ?? 'abc',
        maxZoom: p.maxZoom ?? 19,
    }).addTo(map);

    if (!props.readonly) {
        map.on('click', (e: Leaflet.LeafletMouseEvent) => {
            lat.value = +e.latlng.lat.toFixed(7);
            lng.value = +e.latlng.lng.toFixed(7);
            updateMarkerPosition(lat.value, lng.value, false);
            emit('update', lat.value, lng.value);
            scheduleReverseGeocode(lat.value, lng.value);
        });
    }
}

function setProvider(key: ProviderKey) {
    if (!map) {
        currentProvider.value = key;
        emit('update:provider', key);

        return;
    }

    const oldProvider = providers[currentProvider.value];
    const newProvider = providers[key];

    const center = map.getCenter();
    const centerLatLng: [number, number] = [center.lat, center.lng];
    const currentZoom = map.getZoom();
    const hadMarker = marker !== null;
    const hadMeCircle = meCircle !== null && lat.value !== null && lng.value !== null;

    if (oldProvider.crsKey === newProvider.crsKey) {
        if (tileLayer) {
map.removeLayer(tileLayer);
}

        tileLayer = L.tileLayer(newProvider.url, {
            attribution: newProvider.attribution,
            subdomains: newProvider.subdomains ?? 'abc',
            maxZoom: newProvider.maxZoom ?? 19,
        }).addTo(map);
        currentProvider.value = key;
        emit('update:provider', key);

        return;
    }

    map.remove();
    map = null;
    marker = null;
    tileLayer = null;
    meCircle = null;

    currentProvider.value = key;
    emit('update:provider', key);

    buildMap(key, centerLatLng, currentZoom);

    if (hadMarker && lat.value !== null && lng.value !== null) {
        addMarker(lat.value, lng.value, false);
    }

    if (hadMeCircle && meAccuracy !== null && lat.value !== null && lng.value !== null) {
        addMeCircle(lat.value, lng.value, meAccuracy);
    }
}

onMounted(async () => {
    const mod = await import('leaflet');
    L = (mod as unknown as { default?: typeof Leaflet }).default ?? mod;
    await import('leaflet/dist/leaflet.css');

    const initial: [number, number] = (lat.value !== null && lng.value !== null)
        ? [lat.value, lng.value]
        : DEFAULT_CENTER;

    buildMap(currentProvider.value, initial, lat.value !== null ? 16 : props.defaultZoom);

    if (lat.value !== null && lng.value !== null) {
        addMarker(lat.value, lng.value, false);
    }
});

onBeforeUnmount(() => {
    if (reverseGeocodeTimer) {
        clearTimeout(reverseGeocodeTimer);
        reverseGeocodeTimer = null;
    }

    if (reverseGeocodeAbort) {
        reverseGeocodeAbort.abort();
        reverseGeocodeAbort = null;
    }

    map?.remove();
    map = null;
    marker = null;
    tileLayer = null;
    meCircle = null;
});

watch(() => [props.latitude, props.longitude], ([newLat, newLng]) => {
    if (newLat !== null && newLng !== null && (newLat !== lat.value || newLng !== lng.value)) {
        lat.value = newLat;
        lng.value = newLng;
        updateMarkerPosition(newLat, newLng);
    }
});

watch(() => props.provider, (p) => {
    const next = toProviderKey(p);

    if (next !== currentProvider.value) {
        setProvider(next);
    }
});

function findMe() {
    if (!navigator.geolocation || !map || props.readonly) {
        return;
    }

    locating.value = true;
    geoError.value = null;

    const onSuccess = (pos: GeolocationPosition) => {
        locating.value = false;
        const newLat = +pos.coords.latitude.toFixed(7);
        const newLng = +pos.coords.longitude.toFixed(7);
        lat.value = newLat;
        lng.value = newLng;

        updateMarkerPosition(newLat, newLng);
        map!.setView([newLat, newLng], 17);
        addMeCircle(newLat, newLng, pos.coords.accuracy);

        emit('update', newLat, newLng);
        scheduleReverseGeocode(newLat, newLng);
    };

    const messageFor = (err: GeolocationPositionError): string => {
        switch (err.code) {
            case err.PERMISSION_DENIED:
                return t('component.locationPicker.geoPermissionDenied');
            case err.POSITION_UNAVAILABLE:
                return t('component.locationPicker.geoPositionUnavailable');
            case err.TIMEOUT:
                return t('component.locationPicker.geoTimeout');
            default:
                return t('component.locationPicker.geoError');
        }
    };

    const tryIpFallback = async (origMessage: string) => {
        try {
            const res = await fetch('https://ipapi.co/json/', { headers: { Accept: 'application/json' } });

            if (!res.ok) {
                throw new Error('ip lookup failed');
            }

            const data = await res.json() as { latitude?: number; longitude?: number; city?: string };

            if (typeof data.latitude !== 'number' || typeof data.longitude !== 'number') {
                throw new Error('no coords');
            }

            const newLat = +data.latitude.toFixed(7);
            const newLng = +data.longitude.toFixed(7);
            lat.value = newLat;
            lng.value = newLng;

            updateMarkerPosition(newLat, newLng);
            map!.setView([newLat, newLng], 13);
            addMeCircle(newLat, newLng, 5000);

            emit('update', newLat, newLng);
            scheduleReverseGeocode(newLat, newLng);

            geoError.value = `${origMessage} ${t('component.locationPicker.geoIpFallback', { city: data.city ? ` (${data.city})` : '' })}`;
        } catch {
            geoError.value = origMessage;
        } finally {
            locating.value = false;
        }
    };

    navigator.geolocation.getCurrentPosition(
        onSuccess,
        (err) => {
            // kCLErrorLocationUnknown -> POSITION_UNAVAILABLE: high-accuracy ko'p hollarda macOS da
            // muvaffaqiyatsiz. Low-accuracy bilan qayta urinib ko'ramiz.
            if (err.code === err.POSITION_UNAVAILABLE) {
                navigator.geolocation.getCurrentPosition(
                    onSuccess,
                    (err2) => {
                        void tryIpFallback(messageFor(err2));
                    },
                    { enableHighAccuracy: false, timeout: 15_000, maximumAge: 60_000 },
                );

                return;
            }

            if (err.code === err.PERMISSION_DENIED) {
                locating.value = false;
                geoError.value = messageFor(err);

                return;
            }

            void tryIpFallback(messageFor(err));
        },
        { enableHighAccuracy: true, timeout: 10_000, maximumAge: 0 },
    );
}

function clear() {
    if (props.readonly) {
return;
}

    lat.value = null;
    lng.value = null;

    if (marker && map) {
        marker.remove();
        marker = null;
    }

    if (meCircle && map) {
        meCircle.remove();
        meCircle = null;
        meAccuracy = null;
    }

    emit('update', null, null);
}

type ParsedCoords = { lat: number; lng: number };

function isValidCoord(lat: number, lng: number): boolean {
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
return false;
}

    if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
return false;
}

    if (lat === 0 && lng === 0) {
return false;
}

    return true;
}

// Google va Yandex map linklaridan koordinatalarni ajratish.
// Yandex `ll`/`pt` da tartibi: lng,lat. Google `@`/`q`/`ll` da: lat,lng.
function parseMapLink(input: string): ParsedCoords | null {
    const text = input.trim();

    if (!text) {
return null;
}

    const plain = text.match(/^(-?\d+(?:\.\d+)?)\s*[,\s]\s*(-?\d+(?:\.\d+)?)$/);

    if (plain) {
        const lat = +plain[1];
        const lng = +plain[2];

        if (isValidCoord(lat, lng)) {
return { lat, lng };
}
    }

    let decoded = text;

    try {
        decoded = decodeURIComponent(text);
    } catch {
        // ignore
    }

    const isYandex = /yandex\.[a-z.]+\/maps/i.test(decoded);

    // `\+?` — `+` belgisi probelni URL-encoded shakli
    const googlePatterns: RegExp[] = [
        /!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/,
        /\/maps\/search\/(-?\d+\.\d+),\s*\+?(-?\d+\.\d+)/,
        /\/maps\/dir\/(?:[^/]*\/)*?(-?\d+\.\d+),\s*\+?(-?\d+\.\d+)/,
        /\/maps\/place\/(?:[^/]+\/)?@(-?\d+\.\d+),(-?\d+\.\d+)/,
        /@(-?\d+\.\d+),(-?\d+\.\d+)/,
        /q=loc:(-?\d+\.\d+),\s*\+?(-?\d+\.\d+)/,
        /[?&]q=(-?\d+\.\d+),\s*\+?(-?\d+\.\d+)/,
    ];

    let m: RegExpMatchArray | null = null;

    if (!isYandex) {
        for (const pattern of googlePatterns) {
            m = decoded.match(pattern);

            if (m && isValidCoord(+m[1], +m[2])) {
return { lat: +m[1], lng: +m[2] };
}
        }
    } else {
        m = decoded.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);

        if (m && isValidCoord(+m[1], +m[2])) {
return { lat: +m[1], lng: +m[2] };
}
    }

    m = decoded.match(/[?&]ll=(-?\d+\.\d+),\s*\+?(-?\d+\.\d+)/);

    if (m) {
        const a = +m[1];
        const b = +m[2];
        const lat = isYandex ? b : a;
        const lng = isYandex ? a : b;

        if (isValidCoord(lat, lng)) {
return { lat, lng };
}
    }

    m = decoded.match(/[?&]pt=(-?\d+\.\d+),(-?\d+\.\d+)/);

    if (m && isValidCoord(+m[2], +m[1])) {
return { lat: +m[2], lng: +m[1] };
}

    return null;
}

const linkInput = ref('');
const linkStatus = ref<'idle' | 'loading' | 'success' | 'error' | 'warning'>('idle');
const linkMessage = ref('');

function applyCoords(coords: ParsedCoords) {
    lat.value = +coords.lat.toFixed(7);
    lng.value = +coords.lng.toFixed(7);
    updateMarkerPosition(lat.value, lng.value);
    map?.setView([lat.value, lng.value], Math.max(map.getZoom(), 16));
    emit('update', lat.value, lng.value);
}

type ResolveResponse = {
    lat: number;
    lng: number;
    region?: string | null;
    district?: string | null;
    address?: string | null;
    outside_uz?: boolean;
};

async function applyLink() {
    const value = linkInput.value.trim();

    if (!value) {
        linkStatus.value = 'idle';
        linkMessage.value = '';

        return;
    }

    const local = parseMapLink(value);

    if (local) {
        applyCoords(local);
        linkStatus.value = 'loading';
        linkMessage.value = t('component.locationPicker.resolvingAddress');
    } else if (!/^https?:\/\//i.test(value)) {
        linkStatus.value = 'error';
        linkMessage.value = t('component.locationPicker.coordsNotFound');

        return;
    } else {
        linkStatus.value = 'loading';
        linkMessage.value = '';
    }

    try {
        const res = await fetch(`${props.resolveMapLinkUrl}?url=${encodeURIComponent(value)}`, {
            headers: { Accept: 'application/json' },
        });

        if (!res.ok) {
            const body = await res.json().catch(() => ({ message: t('component.locationPicker.genericError') }));

            if (local) {
                linkStatus.value = 'success';
                linkMessage.value = t('component.locationPicker.coordsNoAddress', { lat: local.lat, lng: local.lng });
                linkInput.value = '';
            } else {
                linkStatus.value = 'error';
                linkMessage.value = body.message ?? t('component.locationPicker.genericError');
            }

            return;
        }

        const data = await res.json() as ResolveResponse;

        if (!isValidCoord(data.lat, data.lng)) {
            linkStatus.value = 'error';
            linkMessage.value = t('component.locationPicker.coordsNotFound');

            return;
        }

        applyCoords({ lat: data.lat, lng: data.lng });
        emit('address', {
            region: data.region ?? null,
            district: data.district ?? null,
            address: data.address ?? null,
        });

        const addrParts = [data.region, data.district, data.address].filter(Boolean).join(', ');

        if (data.outside_uz) {
            linkStatus.value = 'warning';
            linkMessage.value = t('component.locationPicker.outsideUz', { lat: data.lat, lng: data.lng });
        } else {
            linkStatus.value = 'success';
            linkMessage.value = addrParts
                ? t('component.locationPicker.found', { address: addrParts })
                : t('component.locationPicker.coords', { lat: data.lat, lng: data.lng });
        }

        linkInput.value = '';
    } catch {
        if (local) {
            linkStatus.value = 'success';
            linkMessage.value = t('component.locationPicker.coordsNoAddress', { lat: local.lat, lng: local.lng });
            linkInput.value = '';
        } else {
            linkStatus.value = 'error';
            linkMessage.value = t('component.locationPicker.networkError');
        }
    }
}

function clearLinkFeedback() {
    if (linkStatus.value !== 'loading') {
        linkStatus.value = 'idle';
        linkMessage.value = '';
    }
}

function onLatInput(v: string) {
    const n = Number(v);

    if (!Number.isFinite(n) || n < -90 || n > 90) {
return;
}

    lat.value = n;

    if (lng.value !== null) {
        updateMarkerPosition(n, lng.value);
        emit('update', n, lng.value);
        scheduleReverseGeocode(n, lng.value);
    }
}

function onLngInput(v: string) {
    const n = Number(v);

    if (!Number.isFinite(n) || n < -180 || n > 180) {
return;
}

    lng.value = n;

    if (lat.value !== null) {
        updateMarkerPosition(lat.value, n);
        emit('update', lat.value, n);
        scheduleReverseGeocode(lat.value, n);
    }
}
</script>

<template>
    <div class="space-y-3">
        <!-- Readonly: faqat provider yorlig'ini ko'rsatish -->
        <div v-if="readonly" class="flex items-center justify-between gap-2">
            <div class="inline-flex rounded-md border bg-muted/30 px-3 py-1.5 text-xs font-medium text-muted-foreground">
                {{ providers[currentProvider].label }}
            </div>
        </div>

        <!-- Edit mode: provider switcher -->
        <div v-else class="flex flex-wrap items-center justify-between gap-2">
            <div class="inline-flex rounded-md border p-0.5">
                <button
                    v-for="p in (['yandex', 'google', 'osm'] as ProviderKey[])"
                    :key="p"
                    type="button"
                    class="px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="currentProvider === p
                        ? 'rounded bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:text-foreground'"
                    @click="setProvider(p)"
                >
                    {{ providers[p].label }}
                </button>
            </div>

            <p class="text-xs text-muted-foreground">
                {{ t('component.locationPicker.clickOrDragHint') }}
            </p>
        </div>

        <!-- Map link paste -->
        <div v-if="!readonly" class="space-y-1.5">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <Link2 class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="linkInput"
                        :placeholder="t('component.locationPicker.linkPlaceholder')"
                        class="pl-8 text-sm"
                        :disabled="linkStatus === 'loading'"
                        @keydown.enter.prevent="applyLink"
                        @input="clearLinkFeedback"
                    />
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="linkStatus === 'loading' || !linkInput.trim()"
                    @click="applyLink"
                >
                    <Loader2 v-if="linkStatus === 'loading'" class="mr-1.5 h-3.5 w-3.5 animate-spin" />
                    <Check v-else-if="linkStatus === 'success'" class="mr-1.5 h-3.5 w-3.5 text-emerald-600" />
                    <AlertTriangle v-else-if="linkStatus === 'warning'" class="mr-1.5 h-3.5 w-3.5 text-amber-600" />
                    <XIcon v-else-if="linkStatus === 'error'" class="mr-1.5 h-3.5 w-3.5 text-destructive" />
                    {{ t('component.locationPicker.apply') }}
                </Button>
            </div>
            <p
                v-if="linkMessage"
                class="flex items-start gap-1 text-xs"
                :class="{
                    'text-emerald-600': linkStatus === 'success',
                    'text-amber-600': linkStatus === 'warning',
                    'text-destructive': linkStatus === 'error',
                    'text-muted-foreground': linkStatus === 'loading',
                }"
            >
                <AlertTriangle v-if="linkStatus === 'warning'" class="mt-0.5 h-3 w-3 shrink-0" />
                <span>{{ linkMessage }}</span>
            </p>
        </div>

        <!-- Geolocation error -->
        <p v-if="geoError" class="flex items-start gap-1 text-xs text-destructive">
            <AlertTriangle class="mt-0.5 h-3 w-3 shrink-0" />
            <span>{{ geoError }}</span>
        </p>

        <!-- Map container -->
        <div class="relative isolate">
            <div ref="mapContainer" class="w-full overflow-hidden rounded-lg border" :class="height" />

            <div v-if="!readonly" class="absolute right-3 top-3 z-[400] flex flex-col gap-2">
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full border bg-background shadow-md transition-colors hover:bg-muted"
                    :disabled="locating"
                    :title="t('component.locationPicker.findMe')"
                    @click="findMe"
                >
                    <Loader2 v-if="locating" class="h-4 w-4 animate-spin text-primary" />
                    <Crosshair v-else class="h-4 w-4 text-primary" />
                </button>
                <button
                    v-if="lat !== null"
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full border bg-background text-xs shadow-md transition-colors hover:bg-muted"
                    :title="t('component.locationPicker.clear')"
                    @click="clear"
                >
                    ✕
                </button>
            </div>
        </div>

        <!-- Koordinatalar inputi (readonly da yashirin) -->
        <div v-if="!readonly" class="grid grid-cols-2 gap-3">
            <div>
                <Label for="lat">{{ t('component.locationPicker.latitude') }}</Label>
                <Input
                    id="lat"
                    type="number"
                    step="0.0000001"
                    :model-value="lat ?? ''"
                    placeholder="41.3111"
                    class="mt-1 font-mono text-sm"
                    @update:model-value="(v) => onLatInput(String(v))"
                />
            </div>
            <div>
                <Label for="lng">{{ t('component.locationPicker.longitude') }}</Label>
                <Input
                    id="lng"
                    type="number"
                    step="0.0000001"
                    :model-value="lng ?? ''"
                    placeholder="69.2797"
                    class="mt-1 font-mono text-sm"
                    @update:model-value="(v) => onLngInput(String(v))"
                />
            </div>
        </div>

        <div v-if="!readonly && lat !== null" class="flex items-center justify-between gap-2 rounded-md bg-muted/40 px-3 py-2 text-xs">
            <div class="flex items-center gap-2 text-muted-foreground">
                <MapPin class="h-3.5 w-3.5 text-primary" />
                <span>{{ t('component.locationPicker.selected') }} <span class="font-mono text-foreground">{{ lat }}, {{ lng }}</span></span>
                <span v-if="geocoding" class="flex items-center gap-1 text-primary">
                    <Loader2 class="h-3 w-3 animate-spin" />
                    {{ t('component.locationPicker.resolvingAddress') }}
                </span>
            </div>
            <Button type="button" variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="findMe" :disabled="locating">
                <Crosshair class="mr-1 h-3 w-3" />
                {{ t('component.locationPicker.findMe') }}
            </Button>
        </div>
    </div>
</template>
