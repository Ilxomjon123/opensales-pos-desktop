<script setup lang="ts">
// Leaflet `window` ga tayanadi — SSR (Node) da crash beradi. Shu sabab runtime'da
// faqat client tomonda (onMounted) lazy yuklanadi; modul scope'da faqat type ishlatiladi.
import type * as Leaflet from 'leaflet';
import { Crosshair, ExternalLink, Loader2, Maximize2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useMapProvider  } from '@/composables/useMapProvider';
import type {MapProvider} from '@/composables/useMapProvider';

type ProviderKey = 'yandex' | 'google' | 'osm';

type OrderPoint = {
    id: number;
    // Buyurtma raqami (displayNumber). Popup'da `#id` o'rniga shu ko'rsatiladi.
    number?: number | string;
    status: string;
    shopName: string;
    shopAddress: string | null;
    latitude: number;
    longitude: number;
};

const props = withDefaults(
    defineProps<{
        orders: OrderPoint[];
        height?: string;
        lockToDelivering?: boolean;
        // true bo'lsa — `orders` allaqachon to'g'ri (server yetkazib berish)
        // tartibida keladi; mijoz tomonidagi nearest-neighbor qayta hisoblanmaydi.
        preordered?: boolean;
    }>(),
    {
        height: 'h-[480px]',
        lockToDelivering: false,
        preordered: false,
    },
);

const emit = defineEmits<{
    'select-order': [orderId: number];
}>();

const { t } = useI18n();

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

const STATUS_COLOR: Record<string, string> = {
    pending: '#f59e0b',
    assembling: '#0ea5e9',
    delivering: '#3b82f6',
    delivered: '#10b981',
    received: '#14b8a6',
    cancelled: '#f43f5e',
};

const STATUS_LABEL = computed<Record<string, string>>(() => ({
    pending: t('pageDealer.ordersMap.statusPending'),
    assembling: t('pageDealer.ordersMap.statusAssembling'),
    delivering: t('pageDealer.ordersMap.statusDelivering'),
    delivered: t('pageDealer.ordersMap.statusDelivered'),
    received: t('pageDealer.ordersMap.statusReceived'),
    cancelled: t('pageDealer.ordersMap.statusCancelled'),
}));

const ALL_STATUSES: string[] = ['pending', 'assembling', 'delivering', 'delivered', 'received', 'cancelled'];

const DEFAULT_CENTER: [number, number] = [41.3111, 69.2797]; // Toshkent

// Client tomonda onMounted'da yuklanadigan Leaflet runtime obyekti.
let L: typeof Leaflet;

const mapContainer = ref<HTMLDivElement>();
let map: Leaflet.Map | null = null;
let tileLayer: Leaflet.TileLayer | null = null;
let markers: Leaflet.Marker[] = [];
let routeLine: Leaflet.Polyline | null = null;
let meCircle: Leaflet.Circle | null = null;

const currentProvider = ref<ProviderKey>('yandex');
const fullscreen = ref(false);
const locating = ref(false);
const myLocation = ref<{ lat: number; lng: number } | null>(null);
const selectedStatuses = ref<Set<string>>(props.lockToDelivering ? new Set(['delivering']) : new Set(ALL_STATUSES));

const filteredOrders = computed<OrderPoint[]>(() => props.orders.filter((o) => selectedStatuses.value.has(o.status)));

// Marshrut tartibi: preordered bo'lsa server bergan tartib saqlanadi,
// aks holda mijoz tomonida nearest-neighbor bilan hisoblanadi.
const routeOrder = computed<number[]>(() =>
    props.preordered ? filteredOrders.value.map((_, i) => i) : buildNearestNeighborOrder(),
);

const statusCounts = computed<Record<string, number>>(() => {
    const counts: Record<string, number> = {};

    props.orders.forEach((o) => {
        counts[o.status] = (counts[o.status] ?? 0) + 1;
    });

    return counts;
});

const visibleStatuses = computed<string[]>(() => ALL_STATUSES.filter((s) => (statusCounts.value[s] ?? 0) > 0));

const orderedPoints = computed<OrderPoint[]>(() => {
    if (routeOrder.value.length === 0) {
        return filteredOrders.value;
    }

    return routeOrder.value.map((idx) => filteredOrders.value[idx]).filter((p): p is OrderPoint => p !== undefined);
});

type ClusterEntry = { point: OrderPoint; routeIndex: number };
type Cluster = { lat: number; lng: number; entries: ClusterEntry[] };

const STATUS_PRIORITY = ['pending', 'assembling', 'delivering', 'delivered', 'received', 'cancelled'];
const DIM_STATUSES = new Set(['delivered', 'received', 'cancelled']);

function clusterKey(p: OrderPoint): string {
    return `${p.latitude.toFixed(5)},${p.longitude.toFixed(5)}`;
}

const clusters = computed<Cluster[]>(() => {
    const map = new Map<string, Cluster>();
    const order: string[] = [];

    orderedPoints.value.forEach((p, i) => {
        const key = clusterKey(p);

        if (!map.has(key)) {
            map.set(key, { lat: p.latitude, lng: p.longitude, entries: [] });
            order.push(key);
        }

        map.get(key)!.entries.push({ point: p, routeIndex: i + 1 });
    });

    return order.map((k) => map.get(k)!);
});

function clusterColor(cluster: Cluster): string {
    const statuses = new Set(cluster.entries.map((e) => e.point.status));
    const dominant = STATUS_PRIORITY.find((s) => statuses.has(s)) ?? cluster.entries[0]!.point.status;

    return STATUS_COLOR[dominant] ?? '#6b7280';
}

function clusterIsDim(cluster: Cluster): boolean {
    return cluster.entries.every((e) => DIM_STATUSES.has(e.point.status));
}

function toggleStatus(status: string) {
    const next = new Set(selectedStatuses.value);

    if (next.has(status)) {
        next.delete(status);
    } else {
        next.add(status);
    }

    selectedStatuses.value = next;
}

function setOnlyStatus(status: string) {
    selectedStatuses.value = new Set([status]);
}

function selectAllStatuses() {
    selectedStatuses.value = new Set(ALL_STATUSES);
}

const allSelected = computed<boolean>(() => visibleStatuses.value.every((s) => selectedStatuses.value.has(s)));

function makeNumberedIcon(num: number, color: string, dim = false): Leaflet.DivIcon {
    const opacity = dim ? '0.5' : '1';
    const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" width="34" height="42" viewBox="0 0 34 42" style="opacity:${opacity}">
            <defs>
                <filter id="s${num}" x="-50%" y="-20%" width="200%" height="200%">
                    <feGaussianBlur in="SourceAlpha" stdDeviation="1.2" />
                    <feOffset dy="1.5" />
                    <feComponentTransfer><feFuncA type="linear" slope="0.35"/></feComponentTransfer>
                    <feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>
                </filter>
            </defs>
            <g filter="url(#s${num})">
                <path d="M17 1.5 C8.5 1.5 2.5 7.5 2.5 16 C2.5 27.5 17 40.5 17 40.5 C17 40.5 31.5 27.5 31.5 16 C31.5 7.5 25.5 1.5 17 1.5 Z"
                      fill="${color}" stroke="#ffffff" stroke-width="2"/>
                <text x="17" y="20" text-anchor="middle" fill="#ffffff" font-size="13" font-weight="700" font-family="system-ui, sans-serif">${num}</text>
            </g>
        </svg>
    `;

    return L.divIcon({
        html: svg,
        className: 'orders-map-pin',
        iconSize: [34, 42],
        iconAnchor: [17, 40],
        popupAnchor: [0, -38],
    });
}

function makeClusterIcon(count: number, color: string, dim = false): Leaflet.DivIcon {
    const opacity = dim ? '0.5' : '1';
    const label = count > 99 ? '99+' : String(count);
    const fontSize = count > 9 ? 11 : 13;
    const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" width="44" height="50" viewBox="0 0 44 50" style="opacity:${opacity}">
            <defs>
                <filter id="cs${count}" x="-50%" y="-20%" width="200%" height="200%">
                    <feGaussianBlur in="SourceAlpha" stdDeviation="1.4" />
                    <feOffset dy="2" />
                    <feComponentTransfer><feFuncA type="linear" slope="0.4"/></feComponentTransfer>
                    <feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>
                </filter>
            </defs>
            <g filter="url(#cs${count})">
                <path d="M30 7 C22 7 16.5 12.5 16.5 20 C16.5 30 30 41 30 41 C30 41 43.5 30 43.5 20 C43.5 12.5 38 7 30 7 Z"
                      fill="${color}" stroke="#ffffff" stroke-width="2" opacity="0.55"/>
                <path d="M17 2 C8.5 2 2.5 8 2.5 16.5 C2.5 28 17 41 17 41 C17 41 31.5 28 31.5 16.5 C31.5 8 25.5 2 17 2 Z"
                      fill="${color}" stroke="#ffffff" stroke-width="2"/>
                <text x="17" y="${16.5 + fontSize / 3}" text-anchor="middle" fill="#ffffff" font-size="${fontSize}" font-weight="700" font-family="system-ui, sans-serif">${label}</text>
            </g>
        </svg>
    `;

    return L.divIcon({
        html: svg,
        className: 'orders-map-pin orders-map-cluster',
        iconSize: [44, 50],
        iconAnchor: [17, 41],
        popupAnchor: [0, -38],
    });
}

function makeMeIcon(): Leaflet.DivIcon {
    const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">
            <circle cx="10" cy="10" r="8" fill="#3b82f6" stroke="#ffffff" stroke-width="3"/>
        </svg>
    `;

    return L.divIcon({
        html: svg,
        className: 'orders-map-me',
        iconSize: [20, 20],
        iconAnchor: [10, 10],
    });
}

function clearMarkers() {
    markers.forEach((m) => m.remove());
    markers = [];
}

function clearRouteLine() {
    if (routeLine) {
        routeLine.remove();
        routeLine = null;
    }
}

function escapeHtml(s: string): string {
    return s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function mapLinks(latitude: number, longitude: number): string {
    const yandex = pointUrl(latitude, longitude, 'yandex');
    const google = pointUrl(latitude, longitude, 'google');

    return `
        <div style="display:flex;gap:6px;margin-top:2px">
            <a href="${yandex}" target="_blank" rel="noopener noreferrer"
               style="flex:1;text-align:center;font-size:12px;font-weight:600;color:#fff;background:#fc3f1d;border-radius:6px;padding:6px 8px;text-decoration:none">Yandex</a>
            <a href="${google}" target="_blank" rel="noopener noreferrer"
               style="flex:1;text-align:center;font-size:12px;font-weight:600;color:#fff;background:#4285f4;border-radius:6px;padding:6px 8px;text-decoration:none">Google</a>
        </div>
    `;
}

function buildSinglePopup(entry: ClusterEntry): string {
    const p = entry.point;

    return `
        <div style="min-width:180px">
            <div style="font-weight:600;font-size:14px;margin-bottom:2px">#${p.number ?? p.id} — ${escapeHtml(p.shopName)}</div>
            ${p.shopAddress ? `<div style="font-size:12px;color:#6b7280;margin-bottom:6px">${escapeHtml(p.shopAddress)}</div>` : ''}
            <div style="font-size:11px;color:#6b7280;margin-bottom:4px">${escapeHtml(t('pageDealer.ordersMap.openOnMap'))}</div>
            ${mapLinks(p.latitude, p.longitude)}
        </div>
    `;
}

function buildClusterPopup(cluster: Cluster): string {
    const addr = cluster.entries[0]!.point.shopAddress;
    const items = cluster.entries
        .map((e) => {
            const color = STATUS_COLOR[e.point.status] ?? '#6b7280';
            const label = STATUS_LABEL.value[e.point.status] ?? e.point.status;

            return `
                <a href="#" data-order-id="${e.point.id}"
                   style="display:flex;align-items:center;gap:8px;padding:6px 4px;border-bottom:1px solid #f3f4f6;color:inherit;text-decoration:none">
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:${color};color:#fff;font-size:10px;font-weight:700;flex-shrink:0">${e.routeIndex}</span>
                    <span style="flex:1;min-width:0">
                        <span style="display:block;font-size:12px;font-weight:600;color:#111">#${e.point.number ?? e.point.id} ${escapeHtml(e.point.shopName)}</span>
                        <span style="display:block;font-size:11px;color:#6b7280">${label}</span>
                    </span>
                    <span style="font-size:11px;color:#3b82f6">→</span>
                </a>
            `;
        })
        .join('');

    return `
        <div style="min-width:240px;max-width:300px">
            <div style="font-weight:600;font-size:13px;margin-bottom:4px;color:#111">
                ${escapeHtml(t('pageDealer.ordersMap.ordersAtPoint', { count: cluster.entries.length }))}
            </div>
            ${addr ? `<div style="font-size:11px;color:#6b7280;margin-bottom:6px">${escapeHtml(addr)}</div>` : ''}
            <div style="max-height:240px;overflow-y:auto;margin:0 -4px">${items}</div>
        </div>
    `;
}

function renderMarkers() {
    if (!map) {
        return;
    }

    clearMarkers();

    clusters.value.forEach((cluster) => {
        const count = cluster.entries.length;
        const color = clusterColor(cluster);
        const dim = clusterIsDim(cluster);

        const icon =
            count === 1
                ? makeNumberedIcon(cluster.entries[0]!.routeIndex, color, dim)
                : makeClusterIcon(count, color, dim);

        const marker = L.marker([cluster.lat, cluster.lng], { icon }).addTo(map!);

        const html = count === 1 ? buildSinglePopup(cluster.entries[0]!) : buildClusterPopup(cluster);
        marker.bindPopup(html, { maxWidth: 320 });

        marker.on('popupopen', (e) => {
            const popupEl = e.popup.getElement() as HTMLElement | null;
            popupEl?.querySelectorAll<HTMLAnchorElement>('a[data-order-id]').forEach((link) => {
                link.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    const id = Number(link.dataset.orderId);

                    if (!Number.isNaN(id)) {
                        emit('select-order', id);
                    }
                });
            });
        });

        markers.push(marker);
    });
}

function renderRouteLine() {
    if (!map) {
        return;
    }

    clearRouteLine();

    const stops = clusters.value;

    if (stops.length === 0) {
        return;
    }

    const latlngs: Leaflet.LatLngExpression[] = stops.map((c) => [c.lat, c.lng] as Leaflet.LatLngExpression);

    if (myLocation.value) {
        latlngs.unshift([myLocation.value.lat, myLocation.value.lng]);
    }

    if (latlngs.length < 2) {
        return;
    }

    routeLine = L.polyline(latlngs, {
        color: '#3b82f6',
        weight: 3,
        opacity: 0.7,
        dashArray: '8, 8',
    }).addTo(map);
}

function fitToMarkers() {
    if (!map) {
        return;
    }

    const points = filteredOrders.value;

    if (points.length === 0) {
        map.setView(DEFAULT_CENTER, 11);

        return;
    }

    if (points.length === 1) {
        map.setView([points[0]!.latitude, points[0]!.longitude], 15);

        return;
    }

    const bounds = L.latLngBounds(points.map((p) => [p.latitude, p.longitude] as Leaflet.LatLngTuple));

    if (myLocation.value) {
        bounds.extend([myLocation.value.lat, myLocation.value.lng]);
    }

    map.fitBounds(bounds, { padding: [40, 40] });
}

function distance(a: { lat: number; lng: number }, b: { lat: number; lng: number }): number {
    const dLat = a.lat - b.lat;
    const dLng = a.lng - b.lng;

    return Math.sqrt(dLat * dLat + dLng * dLng);
}

/**
 * Nearest-neighbor TSP approximation.
 * Boshlang'ich nuqta: foydalanuvchi joylashuvi bo'lsa undan, aks holda birinchi mijoz.
 */
function buildNearestNeighborOrder(): number[] {
    const points = filteredOrders.value;

    if (points.length === 0) {
        return [];
    }

    const remaining = points.map((_, i) => i);
    const result: number[] = [];

    let cursor: { lat: number; lng: number } = myLocation.value ?? {
        lat: points[0]!.latitude,
        lng: points[0]!.longitude,
    };

    while (remaining.length > 0) {
        let bestIdx = 0;
        let bestDist = Infinity;

        remaining.forEach((idx, i) => {
            const p = points[idx]!;
            const d = distance(cursor, { lat: p.latitude, lng: p.longitude });

            if (d < bestDist) {
                bestDist = d;
                bestIdx = i;
            }
        });

        const chosen = remaining.splice(bestIdx, 1)[0]!;
        result.push(chosen);
        const cp = points[chosen]!;
        cursor = { lat: cp.latitude, lng: cp.longitude };
    }

    return result;
}


const { routeUrl: buildRouteUrl, setProvider: setMapProvider, pointUrl } = useMapProvider();

const routeWaypoints = computed(() => {
    const stops = clusters.value;
    const result: { latitude: number; longitude: number }[] = [];

    if (myLocation.value) {
        result.push({ latitude: myLocation.value.lat, longitude: myLocation.value.lng });
    }

    stops.forEach((c) => {
        result.push({ latitude: c.lat, longitude: c.lng });
    });

    return result;
});

const hasNavigatorRoute = computed(() => routeWaypoints.value.length > 0);

function openNavigator(p: MapProvider): void {
    const url = buildRouteUrl(routeWaypoints.value, p);

    if (!url) {
        return;
    }

    setMapProvider(p);
    window.open(url, '_blank', 'noopener,noreferrer');
}

function buildMap(providerKey: ProviderKey) {
    if (!mapContainer.value) {
        return;
    }

    const p = providers[providerKey];

    map = L.map(mapContainer.value, {
        zoomControl: true,
        crs: L.CRS[p.crsKey],
        maxZoom: p.maxZoom ?? 19,
    }).setView(DEFAULT_CENTER, 11);

    tileLayer = L.tileLayer(p.url, {
        attribution: p.attribution,
        subdomains: p.subdomains ?? 'abc',
        maxZoom: p.maxZoom ?? 19,
    }).addTo(map);

    renderMarkers();
    fitToMarkers();
}

function setProvider(key: ProviderKey) {
    if (!map) {
        currentProvider.value = key;

        return;
    }

    const oldProvider = providers[currentProvider.value];
    const newProvider = providers[key];

    const center = map.getCenter();
    const currentZoom = map.getZoom();

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

        return;
    }

    map.remove();
    map = null;
    tileLayer = null;
    markers = [];
    routeLine = null;
    meCircle = null;

    currentProvider.value = key;

    buildMap(key);
    // buildMap module-level `map` ni qayta o'rnatadi — TS buni kuzata olmaydi.
    (map as Leaflet.Map | null)?.setView([center.lat, center.lng], currentZoom);
    renderRouteLine();

    if (myLocation.value) {
        addMeMarker(myLocation.value.lat, myLocation.value.lng);
    }
}

function addMeMarker(lat: number, lng: number) {
    if (!map) {
        return;
    }

    if (meCircle) {
        meCircle.remove();
        meCircle = null;
    }

    L.marker([lat, lng], { icon: makeMeIcon(), zIndexOffset: 1000 }).addTo(map);
}

// Joylashuvni qabul qilib, marshrut boshlanish nuqtasi sifatida o'rnatadi.
// recenter=true — xaritani foydalanuvchiga markazlaydi (qo'lda "Meni top"),
// recenter=false — barcha nuqtalarni sig'diradi (avtomatik aniqlash).
function applyLocation(lat: number, lng: number, recenter: boolean) {
    myLocation.value = { lat, lng };

    addMeMarker(lat, lng);
    renderMarkers();
    renderRouteLine();

    if (recenter) {
        map?.setView([lat, lng], 14);
    } else {
        fitToMarkers();
    }
}

function findMe() {
    if (!navigator.geolocation || !map) {
        return;
    }

    locating.value = true;

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            locating.value = false;
            applyLocation(+pos.coords.latitude.toFixed(7), +pos.coords.longitude.toFixed(7), true);
        },
        () => {
            locating.value = false;
        },
        { enableHighAccuracy: true, timeout: 10_000 },
    );
}

// Sahifa ochilganda joylashuvni avtomatik so'raydi — deliveryman tugma
// bosmasa ham marshrut joriy joylashuvdan boshlanadi.
function autoLocate() {
    if (!navigator.geolocation) {
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            applyLocation(+pos.coords.latitude.toFixed(7), +pos.coords.longitude.toFixed(7), false);
        },
        () => {
            // Ruxsat berilmasa — birinchi mijoz boshlanish nuqtasi bo'lib qoladi.
        },
        { enableHighAccuracy: true, timeout: 10_000, maximumAge: 60_000 },
    );
}

onMounted(async () => {
    const mod = await import('leaflet');
    L = (mod as unknown as { default?: typeof Leaflet }).default ?? mod;
    await import('leaflet/dist/leaflet.css');

    buildMap(currentProvider.value);
    autoLocate();
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    tileLayer = null;
    markers = [];
    routeLine = null;
    meCircle = null;
});

watch(
    () => props.orders,
    () => {
        renderMarkers();
        renderRouteLine();
        fitToMarkers();
    },
    { deep: true },
);

watch(filteredOrders, () => {
    renderMarkers();
    renderRouteLine();
});

watch(fullscreen, () => {
    // Browser orientation o'zgargandan keyin Leaflet o'lchamini qayta hisoblash
    setTimeout(() => map?.invalidateSize(), 50);
});
</script>

<template>
    <div :class="fullscreen ? 'fixed inset-0 z-50 flex flex-col bg-background p-3 sm:p-4' : 'space-y-3'">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="inline-flex rounded-md border p-0.5">
                <button
                    v-for="p in (['yandex', 'google', 'osm'] as ProviderKey[])"
                    :key="p"
                    type="button"
                    class="px-2.5 py-1 text-xs font-medium transition-colors sm:px-3 sm:py-1.5"
                    :class="
                        currentProvider === p ? 'rounded bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="setProvider(p)"
                >
                    {{ providers[p].label }}
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                <DropdownMenu v-if="hasNavigatorRoute">
                    <DropdownMenuTrigger as-child>
                        <Button type="button" size="sm" class="h-8 px-2.5 sm:px-3">
                            <ExternalLink class="h-3.5 w-3.5 sm:mr-1.5" />
                            <span class="hidden sm:inline">{{ t('pageDealer.ordersMap.navigator') }}</span>
                            <span class="ml-1 sm:hidden">{{ t('pageDealer.ordersMap.navigatorShort') }}</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-44">
                        <DropdownMenuItem @click="() => openNavigator('yandex')">
                            <span class="font-medium">{{ t('pageDealer.ordersMap.yandexMap') }}</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="() => openNavigator('google')">
                            <span class="font-medium">{{ t('pageDealer.ordersMap.googleMap') }}</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <Button type="button" size="sm" variant="outline" class="h-8 w-8 p-0" :disabled="locating" :title="t('pageDealer.ordersMap.findMe')" @click="findMe">
                    <Loader2 v-if="locating" class="h-3.5 w-3.5 animate-spin text-primary" />
                    <Crosshair v-else class="h-3.5 w-3.5" />
                </Button>

                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    class="h-8 w-8 p-0"
                    :title="fullscreen ? t('pageDealer.ordersMap.close') : t('pageDealer.ordersMap.fullscreen')"
                    @click="fullscreen = !fullscreen"
                >
                    <X v-if="fullscreen" class="h-3.5 w-3.5" />
                    <Maximize2 v-else class="h-3.5 w-3.5" />
                </Button>
            </div>
        </div>

        <!-- Status filter chips -->
        <div v-if="!lockToDelivering && visibleStatuses.length > 1" class="flex flex-wrap items-center gap-1.5">
            <button
                v-for="s in visibleStatuses"
                :key="s"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-all"
                :class="
                    selectedStatuses.has(s)
                        ? 'border-transparent text-white shadow-sm'
                        : 'border-input bg-background text-muted-foreground hover:bg-muted'
                "
                :style="selectedStatuses.has(s) ? { backgroundColor: STATUS_COLOR[s] } : {}"
                :title="t('pageDealer.ordersMap.statusHint', { label: STATUS_LABEL[s] })"
                @click="toggleStatus(s)"
                @dblclick="setOnlyStatus(s)"
            >
                <span
                    v-if="!selectedStatuses.has(s)"
                    class="h-2 w-2 rounded-full"
                    :style="{ backgroundColor: STATUS_COLOR[s] }"
                />
                {{ STATUS_LABEL[s] }}
                <span class="rounded-full bg-black/15 px-1.5 text-[10px] tabular-nums" :class="selectedStatuses.has(s) ? '' : 'bg-muted'">
                    {{ statusCounts[s] }}
                </span>
            </button>

            <button
                v-if="!allSelected"
                type="button"
                class="ml-1 text-xs text-primary underline-offset-2 hover:underline"
                @click="selectAllStatuses"
            >
                {{ t('pageDealer.ordersMap.all') }}
            </button>
        </div>

        <!-- Info bar marshrut haqida -->
        <div
            v-if="clusters.length > 0"
            class="rounded-md border border-blue-500/30 bg-blue-500/10 px-3 py-2 text-xs text-blue-700 dark:text-blue-300"
        >
            <strong>{{ clusters.length }}</strong> {{ t('pageDealer.ordersMap.pointsArranged') }}
            <span v-if="orderedPoints.length !== clusters.length">{{ t('pageDealer.ordersMap.ordersCount', { count: orderedPoints.length }) }}</span>
            <span v-if="myLocation">{{ t('pageDealer.ordersMap.fromYourLocation') }}</span>.
            {{ t('pageDealer.ordersMap.navigatorPick') }}
            <span v-if="clusters.length > 9" class="font-medium">{{ t('pageDealer.ordersMap.navigatorWarn') }}</span>
        </div>

        <!-- Bo'sh holat: filter natijasida hech nima qolmagan -->
        <div
            v-if="filteredOrders.length === 0 && orders.length > 0"
            class="rounded-md border border-dashed px-3 py-2 text-xs text-muted-foreground"
        >
            {{ t('pageDealer.ordersMap.nothingForStatuses') }}
            <button type="button" class="ml-1 text-primary underline-offset-2 hover:underline" @click="selectAllStatuses">
                {{ t('pageDealer.ordersMap.showAll') }}
            </button>
        </div>

        <!-- Map -->
        <div ref="mapContainer" class="relative isolate w-full overflow-hidden rounded-lg border" :class="fullscreen ? 'flex-1' : height" />
    </div>
</template>
