export type LoadingRouteStop = {
    delivery_position: number;
    loading_position: number;
    distance_from_prev_m: number;
    duration_from_prev_s: number;
    cumulative_distance_m: number;
    payload: {
        order_id: number;
        order_number: string | number;
        order_total: number;
        order_status: string;
        order_status_label: string;
        shop_id: number;
        shop_name: string;
        shop_phone: string | null;
        shop_address: string | null;
        shop_region: string | null;
        shop_district: string | null;
        shop_latitude: number;
        shop_longitude: number;
    };
};

export type LoadingRouteSkipped = {
    order_id: number;
    shop_name: string | null;
    reason: 'no_coordinates';
};

export type LoadingRouteWarehouse = {
    name: string;
    address: string | null;
    latitude: number;
    longitude: number;
};

export type LoadingRouteResult = {
    warehouse: LoadingRouteWarehouse;
    delivery_sequence: LoadingRouteStop[];
    loading_sequence: LoadingRouteStop[];
    total_distance_meters: number;
    total_duration_seconds: number;
    return_distance_meters: number;
    return_duration_seconds: number;
    skipped: LoadingRouteSkipped[];
};

export function formatDistance(meters: number): string {
    if (meters < 1000) {
        return `${meters} m`;
    }

    return `${(meters / 1000).toFixed(1)} km`;
}

export function formatDuration(seconds: number): string {
    if (seconds < 60) {
        return `${seconds} s`;
    }

    const minutes = Math.round(seconds / 60);

    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    return remainder > 0 ? `${hours} s ${remainder} min` : `${hours} s`;
}
