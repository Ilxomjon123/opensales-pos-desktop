<script setup lang="ts">
import { Languages, Check } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLocale } from '@/composables/useLocale';

type Props = {
    align?: 'start' | 'center' | 'end';
    variant?: 'icon' | 'compact';
};

const props = withDefaults(defineProps<Props>(), {
    align: 'end',
    variant: 'icon',
});

const { current, locales, switchTo } = useLocale();
const { t } = useI18n();

function activeLocale() {
    return locales.value.find((entry) => entry.code === current.value);
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button
                v-if="props.variant === 'icon'"
                variant="ghost"
                size="icon"
                class="h-9 w-9"
                :aria-label="t('locale.label')"
            >
                <Languages class="size-5" />
            </Button>
            <Button
                v-else
                variant="ghost"
                size="sm"
                class="h-9 gap-2"
                :aria-label="t('locale.label')"
            >
                <Languages class="size-4" />
                <span class="text-sm">{{ activeLocale()?.native }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent :align="props.align" class="w-48">
            <DropdownMenuLabel>{{ t('locale.select') }}</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                v-for="entry in locales"
                :key="entry.code"
                class="flex items-center justify-between gap-2"
                @select="switchTo(entry.code)"
            >
                <span class="flex items-center gap-2">
                    <span aria-hidden="true">{{ entry.flag }}</span>
                    <span>{{ entry.native }}</span>
                </span>
                <Check
                    v-if="entry.code === current"
                    class="size-4 text-primary"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
