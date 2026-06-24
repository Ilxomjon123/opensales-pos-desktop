import { ref } from 'vue';

export type MapProvider = 'yandex' | 'google';

export type MapWaypoint = { latitude: number; longitude: number };

const STORAGE_KEY = 'map_provider';

function readInitial(): MapProvider {
    if (typeof window === 'undefined') {
        return 'yandex';
    }

    const stored = window.localStorage.getItem(STORAGE_KEY);

    return stored === 'google' || stored === 'yandex' ? stored : 'yandex';
}

const provider = ref<MapProvider>(readInitial());

export function useMapProvider() {
    function setProvider(next: MapProvider): void {
        provider.value = next;

        if (typeof window !== 'undefined') {
            window.localStorage.setItem(STORAGE_KEY, next);
        }
    }

    function pointUrl(latitude: number, longitude: number, p: MapProvider = provider.value): string {
        if (p === 'google') {
            return `https://www.google.com/maps/search/?api=1&query=${latitude},${longitude}`;
        }

        return `https://yandex.uz/maps/?pt=${longitude},${latitude}&z=16&l=map`;
    }

    function addressUrl(address: string, p: MapProvider = provider.value): string {
        const encoded = encodeURIComponent(address);

        if (p === 'google') {
            return `https://www.google.com/maps/search/?api=1&query=${encoded}`;
        }

        return `https://yandex.uz/maps/?text=${encoded}`;
    }

    function shopUrl(
        shop: { latitude: number | null; longitude: number | null; address: string | null },
        p: MapProvider = provider.value,
    ): string | null {
        if (shop.latitude !== null && shop.longitude !== null) {
            return pointUrl(shop.latitude, shop.longitude, p);
        }

        if (shop.address) {
            return addressUrl(shop.address, p);
        }

        return null;
    }

    function routeUrl(waypoints: MapWaypoint[], p: MapProvider = provider.value): string | null {
        if (waypoints.length === 0) {
            return null;
        }

        if (p === 'google') {
            const limited = waypoints.slice(0, 25);
            const origin = limited[0];
            const destination = limited[limited.length - 1];
            // Boshlanish (joriy joylashuv) va manzil orasidagi oraliq to'xtashlar.
            const intermediate = limited.slice(1, -1);
            const params = new URLSearchParams({
                api: '1',
                origin: `${origin.latitude},${origin.longitude}`,
                destination: `${destination.latitude},${destination.longitude}`,
                travelmode: 'driving',
            });

            if (intermediate.length > 0) {
                params.append(
                    'waypoints',
                    intermediate.map((w) => `${w.latitude},${w.longitude}`).join('|'),
                );
            }

            return `https://www.google.com/maps/dir/?${params.toString()}`;
        }

        const limited = waypoints.slice(0, 10).map((w) => `${w.latitude},${w.longitude}`);

        return `https://yandex.uz/maps/?rtext=${limited.join('~')}&rtt=auto`;
    }

    return {
        provider,
        setProvider,
        pointUrl,
        addressUrl,
        shopUrl,
        routeUrl,
    };
}
