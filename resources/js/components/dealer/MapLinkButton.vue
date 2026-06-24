<script setup lang="ts">
import { ExternalLink } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useMapProvider, type MapProvider } from '@/composables/useMapProvider';

type Variant = 'icon' | 'button';

type ShopLike = {
    latitude: number | null;
    longitude: number | null;
    address: string | null;
};

const props = withDefaults(
    defineProps<{
        shop: ShopLike;
        variant?: Variant;
        title?: string;
        label?: string;
        size?: 'sm' | 'md';
    }>(),
    {
        variant: 'icon',
        title: '',
        label: '',
        size: 'md',
    },
);

const { t } = useI18n();
const { shopUrl, setProvider } = useMapProvider();

const hasAnchor = computed(() => shopUrl(props.shop) !== null);

function openWith(p: MapProvider, event: Event): void {
    event.preventDefault();
    event.stopPropagation();

    const url = shopUrl(props.shop, p);

    if (!url) {
        return;
    }

    setProvider(p);
    window.open(url, '_blank', 'noopener,noreferrer');
}

const triggerTitle = computed(() => props.title || t('mapProvider.openInMap'));
</script>

<template>
    <DropdownMenu v-if="hasAnchor">
        <DropdownMenuTrigger as-child>
            <button
                v-if="variant === 'icon'"
                type="button"
                :title="triggerTitle"
                :class="[
                    'inline-flex items-center justify-center rounded-full border bg-background transition active:scale-95 hover:bg-muted',
                    size === 'sm' ? 'h-7 w-7' : 'h-8 w-8',
                ]"
                @click.stop
            >
                <ExternalLink :class="size === 'sm' ? 'h-3 w-3' : 'h-3.5 w-3.5'" />
            </button>
            <button
                v-else
                type="button"
                :title="triggerTitle"
                class="inline-flex items-center gap-1.5 rounded-md border bg-background px-3 py-1.5 text-xs font-medium transition hover:bg-muted active:scale-[0.98]"
                @click.stop
            >
                <ExternalLink class="h-3.5 w-3.5" />
                <span>{{ label || triggerTitle }}</span>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-44">
            <DropdownMenuItem @click="(e: Event) => openWith('yandex', e)">
                <span class="font-medium">{{ t('mapProvider.yandex') }}</span>
            </DropdownMenuItem>
            <DropdownMenuItem @click="(e: Event) => openWith('google', e)">
                <span class="font-medium">{{ t('mapProvider.google') }}</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
