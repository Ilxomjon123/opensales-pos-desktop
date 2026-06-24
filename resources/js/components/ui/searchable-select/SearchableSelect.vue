<script setup lang="ts" generic="T">
import { Check, ChevronDown, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { translitMatches } from '@/lib/translit';

type ItemValue = string | number | null;
type ModelValue = ItemValue | ItemValue[];

const props = withDefaults(defineProps<{
    modelValue: ModelValue;
    items: T[];
    valueKey?: keyof T;
    labelKey?: keyof T;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    clearable?: boolean;
    disabled?: boolean;
    searchable?: boolean;
    multiple?: boolean;
    maxHeight?: string;
}>(), {
    valueKey: 'value' as keyof T,
    labelKey: 'label' as keyof T,
    placeholder: 'Tanlang...',
    searchPlaceholder: 'Qidirish...',
    emptyText: 'Topilmadi',
    clearable: true,
    disabled: false,
    searchable: true,
    multiple: false,
    maxHeight: '15rem',
});

const emit = defineEmits<{
    'update:modelValue': [value: ModelValue];
    change: [value: ModelValue, item: T | T[] | null];
}>();

const open = ref(false);
const search = ref('');
const root = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const popup = ref<HTMLElement | null>(null);
const popupStyle = ref<Record<string, string>>({});
const dropUp = ref(false);

function parseMaxHeight(): number {
    const v = props.maxHeight.trim();
    if (v.endsWith('rem')) {
        const n = parseFloat(v);
        const fontSize = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;

        return n * fontSize;
    }
    if (v.endsWith('px')) {
        return parseFloat(v);
    }

    return 240;
}

function updatePosition() {
    if (!root.value) {
        return;
    }

    const rect = root.value.getBoundingClientRect();
    const viewportH = window.innerHeight;
    const viewportW = window.innerWidth;
    const margin = 8;
    const minSpace = 200;
    const spaceBelow = viewportH - rect.bottom - margin;
    const spaceAbove = rect.top - margin;
    // Drop up only if below is truly cramped AND above has meaningfully more space.
    const placeUp = spaceBelow < minSpace && spaceAbove > spaceBelow + 80;
    dropUp.value = placeUp;

    const availableH = Math.max(180, placeUp ? spaceAbove : spaceBelow);
    const minWidth = Math.min(280, viewportW - margin * 2);
    const width = Math.max(rect.width, minWidth);
    const left = Math.max(margin, Math.min(rect.left, viewportW - width - margin));

    popupStyle.value = {
        position: 'fixed',
        left: `${left}px`,
        width: `${width}px`,
        maxHeight: `${availableH}px`,
        ...(placeUp
            ? { bottom: `${viewportH - rect.top + 4}px` }
            : { top: `${rect.bottom + 4}px` }),
    };
}

const filtered = computed<T[]>(() => {
    if (!props.searchable) return props.items;

    const q = search.value.trim();
    if (!q) return props.items;

    // Kirill/lotin alifbodan qat'i nazar moslik (translit normalize).
    return props.items.filter((item) => translitMatches(String(item[props.labelKey] ?? ''), q));
});

const selectedValues = computed<ItemValue[]>(() => {
    if (props.multiple) {
        const v = props.modelValue;
        if (!Array.isArray(v)) return [];
        return v.filter((x) => x !== null && x !== undefined && x !== '');
    }

    const v = props.modelValue;
    if (v === null || v === undefined || v === '') return [];
    return [v as ItemValue];
});

const selected = computed<T | null>(() => {
    if (props.multiple) return null;
    if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') return null;

    return props.items.find((item) => String(item[props.valueKey]) === String(props.modelValue)) ?? null;
});

const selectedItems = computed<T[]>(() => {
    if (!props.multiple) return [];
    const vals = selectedValues.value.map((v) => String(v));

    return props.items.filter((item) => vals.includes(String(item[props.valueKey])));
});

function isSelected(item: T): boolean {
    const v = String(item[props.valueKey]);
    if (props.multiple) {
        return selectedValues.value.some((x) => String(x) === v);
    }

    return String(props.modelValue) === v;
}

function toggle() {
    if (props.disabled) return;
    open.value = !open.value;
}

function select(item: T) {
    const value = item[props.valueKey] as ItemValue;

    if (props.multiple) {
        const current = [...selectedValues.value];
        const idx = current.findIndex((x) => String(x) === String(value));
        if (idx >= 0) {
            current.splice(idx, 1);
        } else {
            current.push(value);
        }
        emit('update:modelValue', current);
        emit('change', current, props.items.filter((it) => current.some((c) => String(c) === String(it[props.valueKey]))));

        return;
    }

    emit('update:modelValue', value);
    emit('change', value, item);
    open.value = false;
    search.value = '';
}

function clear(e: Event) {
    e.stopPropagation();
    if (props.multiple) {
        emit('update:modelValue', []);
        emit('change', [], []);

        return;
    }
    emit('update:modelValue', null);
    emit('change', null, null);
}

const hasSelection = computed(() => {
    if (props.multiple) return selectedValues.value.length > 0;

    return selected.value !== null;
});

function onClickOutside(e: MouseEvent) {
    const target = e.target as Node;
    const insideRoot = root.value?.contains(target) ?? false;
    const insidePopup = popup.value?.contains(target) ?? false;

    if (!insideRoot && !insidePopup) {
        open.value = false;
    }
}

function onScrollOrResize() {
    if (open.value) {
        updatePosition();
    }
}

// Popup teleport'lanadi body'ga — modal Dialog FocusScope (trapped) uni
// "tashqarida" deb biladi va fokusni qaytarib oladi → search ishlamaydi.
// Capture fazada propagatsiyani to'xtatib, reka'ning bubble handler'ini bloklaymiz.
function isInPopup(node: EventTarget | null): boolean {
    return node instanceof Node && (popup.value?.contains(node) ?? false);
}
function onFocusInCapture(e: FocusEvent) {
    if (isInPopup(e.target)) {
        e.stopImmediatePropagation();
    }
}
function onFocusOutCapture(e: FocusEvent) {
    if (isInPopup(e.relatedTarget)) {
        e.stopImmediatePropagation();
    }
}

watch(open, async (val) => {
    if (val) {
        document.addEventListener('focusin', onFocusInCapture, true);
        document.addEventListener('focusout', onFocusOutCapture, true);
        await nextTick();
        updatePosition();
        await nextTick();
        if (props.searchable) {
            searchInput.value?.focus();
        }
    } else {
        document.removeEventListener('focusin', onFocusInCapture, true);
        document.removeEventListener('focusout', onFocusOutCapture, true);
        search.value = '';
    }
});

// SSR: Teleport server'da body'ga joylashmaydi -> hydration mismatch.
// Mount'gacha teleport o'chiq turadi (inline), keyin yoqiladi.
const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
    document.addEventListener('click', onClickOutside);
    window.addEventListener('resize', onScrollOrResize);
    window.addEventListener('scroll', onScrollOrResize, true);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onClickOutside);
    window.removeEventListener('resize', onScrollOrResize);
    window.removeEventListener('scroll', onScrollOrResize, true);
    document.removeEventListener('focusin', onFocusInCapture, true);
    document.removeEventListener('focusout', onFocusOutCapture, true);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            :disabled="disabled"
            class="flex h-10 w-full items-center justify-between gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background transition-colors hover:bg-muted/30 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            @click="toggle"
        >
            <template v-if="multiple">
                <span v-if="selectedItems.length === 0" class="truncate text-muted-foreground">{{ placeholder }}</span>
                <span v-else class="flex min-w-0 flex-1 items-center gap-1.5">
                    <span class="truncate">{{ selectedItems[0][labelKey] }}</span>
                    <span
                        v-if="selectedItems.length > 1"
                        class="inline-flex shrink-0 items-center rounded bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground"
                    >
                        +{{ selectedItems.length - 1 }}
                    </span>
                </span>
            </template>
            <template v-else>
                <slot v-if="selected" name="selected" :item="selected">
                    <span class="truncate">{{ selected[labelKey] }}</span>
                </slot>
                <span v-else class="truncate text-muted-foreground">{{ placeholder }}</span>
            </template>

            <span class="flex shrink-0 items-center gap-1">
                <X
                    v-if="clearable && hasSelection"
                    class="h-4 w-4 text-muted-foreground hover:text-foreground"
                    @click="clear"
                />
                <ChevronDown
                    class="h-4 w-4 text-muted-foreground transition-transform"
                    :class="open ? 'rotate-180' : ''"
                />
            </span>
        </button>

        <Teleport to="body" :disabled="!isMounted">
            <div
                v-if="open"
                ref="popup"
                data-searchable-select-popup
                class="pointer-events-auto z-[100] flex flex-col overflow-hidden rounded-md border bg-popover shadow-lg"
                :style="popupStyle"
            >
                <div v-if="searchable" class="border-b p-2">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                        <input
                            ref="searchInput"
                            v-model="search"
                            type="text"
                            :placeholder="searchPlaceholder"
                            class="h-8 w-full rounded border bg-background pl-7 pr-2 text-sm outline-none focus:ring-2 focus:ring-primary/30"
                            @click.stop
                        />
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-1">
                    <button
                        v-for="(item, idx) in filtered"
                        :key="String(item[valueKey] ?? idx)"
                        type="button"
                        class="flex min-h-[40px] w-full items-center justify-between gap-3 rounded px-3 py-2 text-left text-sm transition-colors hover:bg-muted"
                        :class="isSelected(item) ? 'bg-muted' : ''"
                        @click="select(item)"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <Check
                                v-if="isSelected(item)"
                                class="h-3.5 w-3.5 shrink-0 text-primary"
                            />
                            <slot name="item" :item="item">
                                <span class="truncate">{{ item[labelKey] }}</span>
                            </slot>
                        </span>
                        <slot name="item-suffix" :item="item" />
                    </button>
                    <div v-if="filtered.length === 0" class="py-6 text-center text-xs text-muted-foreground">
                        {{ emptyText }}
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
