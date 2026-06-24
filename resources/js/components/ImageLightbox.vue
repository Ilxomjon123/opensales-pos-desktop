<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    images: string[];
    initialIdx?: number;
    open: boolean;
    disableDoubleTap?: boolean;
}>();

const emit = defineEmits<{ close: [] }>();

const currentIdx = ref(props.initialIdx ?? 0);
const scale = ref(1);
const tx = ref(0);
const ty = ref(0);
const imgRef = ref<HTMLImageElement>();

// Surish animatsiyasi uchun — rasmni barmoq bilan surayotgan chog'ida offset
const swipeOffset = ref(0);
// Transition yoqish / o'chirish (instant reset uchun kerak)
const animating = ref(false);
// Surish chog'ida qaysi tomondan keyingi rasm ko'rinishini belgilaydi.
// null bo'lsa — qo'shimcha rasm rendered emas (idle holat).
const pendingDir = ref<'next' | 'prev' | null>(null);

const adjacentIdx = computed<number | null>(() => {
    if (!pendingDir.value || props.images.length <= 1) {
        return null;
    }

    if (pendingDir.value === 'next') {
        return (currentIdx.value + 1) % props.images.length;
    }

    return (currentIdx.value - 1 + props.images.length) % props.images.length;
});

const mode = ref<'idle' | 'pan' | 'pinch' | 'swipe'>('idle');
let startDistance = 0;
let startScale = 1;
let startX = 0;
let startY = 0;
let startTx = 0;
let startTy = 0;
let lastTap = 0;

const MIN_SCALE = 1;
const MAX_SCALE = 4;
const SWIPE_THRESHOLD = 60;
const SLIDE_DURATION = 250; // ms
const SWIPE_REVEAL_PX = 8; // shu pikseldan ortgach qo'shni rasmni render qilamiz

function getDistance(t1: Touch, t2: Touch): number {
    const dx = t1.clientX - t2.clientX;
    const dy = t1.clientY - t2.clientY;

    return Math.hypot(dx, dy);
}

function onTouchStart(e: TouchEvent) {
    if (e.touches.length === 2) {
        mode.value = 'pinch';
        animating.value = false;
        startDistance = getDistance(e.touches[0], e.touches[1]);
        startScale = scale.value;
    } else if (e.touches.length === 1) {
        const t = e.touches[0];

        if (!props.disableDoubleTap) {
            const now = Date.now();

            if (now - lastTap < 300) {
                if (scale.value > 1) {
                    resetZoomAnimated();
                } else {
                    animating.value = true;
                    scale.value = 2.5;
                }

                lastTap = 0;

                return;
            }

            lastTap = now;
        }

        if (scale.value > 1) {
            mode.value = 'pan';
            animating.value = false;
            startX = t.clientX;
            startY = t.clientY;
            startTx = tx.value;
            startTy = ty.value;
        } else {
            mode.value = 'swipe';
            animating.value = false;
            startX = t.clientX;
            swipeOffset.value = 0;
        }
    }
}

function onTouchMove(e: TouchEvent) {
    if (mode.value === 'pinch' && e.touches.length === 2) {
        e.preventDefault();
        const d = getDistance(e.touches[0], e.touches[1]);
        scale.value = Math.max(MIN_SCALE, Math.min(MAX_SCALE, startScale * (d / startDistance)));

        if (scale.value === 1) {
            tx.value = 0;
            ty.value = 0;
        }
    } else if (mode.value === 'pan' && e.touches.length === 1) {
        e.preventDefault();
        const t = e.touches[0];
        tx.value = startTx + (t.clientX - startX);
        ty.value = startTy + (t.clientY - startY);
    } else if (mode.value === 'swipe' && e.touches.length === 1) {
        const t = e.touches[0];
        swipeOffset.value = t.clientX - startX;

        if (props.images.length <= 1) {
            pendingDir.value = null;
        } else if (swipeOffset.value < -SWIPE_REVEAL_PX) {
            pendingDir.value = 'next';
        } else if (swipeOffset.value > SWIPE_REVEAL_PX) {
            pendingDir.value = 'prev';
        } else {
            pendingDir.value = null;
        }
    }
}

function onTouchEnd(e: TouchEvent) {
    if (mode.value === 'swipe') {
        const dx = swipeOffset.value;

        if (Math.abs(dx) > SWIPE_THRESHOLD && props.images.length > 1) {
            void slideAndChange(dx > 0 ? 'prev' : 'next');
        } else {
            // Threshold'ga yetmadi — animatsiya bilan markazga qaytamiz.
            // pendingDir animatsiya tugagandan keyin tozalanadi, aks holda
            // qo'shni rasm darhol DOM dan olib tashlanadi va sakrash bo'ladi.
            animating.value = true;
            swipeOffset.value = 0;
            setTimeout(() => {
                if (swipeOffset.value === 0) {
                    pendingDir.value = null;
                }
            }, SLIDE_DURATION);
        }
    }

    if (e.touches.length === 0) {
        mode.value = 'idle';
    } else if (e.touches.length === 1 && mode.value === 'pinch') {
        mode.value = scale.value > 1 ? 'pan' : 'idle';
        const t = e.touches[0];
        startX = t.clientX;
        startY = t.clientY;
        startTx = tx.value;
        startTy = ty.value;
    }
}

async function slideAndChange(dir: 'next' | 'prev') {
    const width = window.innerWidth;
    const targetIdx = dir === 'next'
        ? (currentIdx.value + 1) % props.images.length
        : (currentIdx.value - 1 + props.images.length) % props.images.length;

    // Qo'shni rasmni majburiy ravishda render qilamiz — animatsiya davomida
    // ham joriy, ham keyingi rasm bir vaqtda ko'rinib turishi uchun.
    pendingDir.value = dir;

    // Tek o'tishda ham joriy rasm tashqariga, ham qo'shni rasm markazga sirg'aladi.
    animating.value = true;
    swipeOffset.value = dir === 'next' ? -width : width;

    await new Promise((r) => setTimeout(r, SLIDE_DURATION));

    // Animatsiya tugadi — endi o'tish: indeks o'zgaradi, offset nolga qaytadi,
    // qo'shni rasm DOM dan olib tashlanadi. Transition o'chirilgani uchun
    // hech qanday yana sakrash bo'lmaydi.
    animating.value = false;
    currentIdx.value = targetIdx;
    swipeOffset.value = 0;
    pendingDir.value = null;
    resetZoomInstant();
}

function next() {
    if (props.images.length <= 1) {
return;
}

    void slideAndChange('next');
}

function prev() {
    if (props.images.length <= 1) {
return;
}

    void slideAndChange('prev');
}

function resetZoomInstant() {
    scale.value = 1;
    tx.value = 0;
    ty.value = 0;
}

function resetZoomAnimated() {
    animating.value = true;
    scale.value = 1;
    tx.value = 0;
    ty.value = 0;
}

function onWheel(e: WheelEvent) {
    e.preventDefault();
    animating.value = false;
    const delta = -e.deltaY * 0.005;
    scale.value = Math.max(MIN_SCALE, Math.min(MAX_SCALE, scale.value + delta));

    if (scale.value === 1) {
        tx.value = 0;
        ty.value = 0;
    }
}

function onDoubleClick() {
    if (props.disableDoubleTap) {
return;
}

    if (scale.value > 1) {
        resetZoomAnimated();
    } else {
        animating.value = true;
        scale.value = 2.5;
    }
}

function onKeydown(e: KeyboardEvent) {
    if (!props.open) {
return;
}

    if (e.key === 'Escape') {
emit('close');
} else if (e.key === 'ArrowLeft') {
prev();
} else if (e.key === 'ArrowRight') {
next();
}
}

// SSR: Teleport mount'gacha o'chiq (hydration mismatch'ni oldini olish).
const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
});

watch(() => props.initialIdx, (v) => {
    if (v !== undefined) {
currentIdx.value = v;
}
});

watch(() => props.open, (v) => {
    if (v) {
        currentIdx.value = props.initialIdx ?? 0;
        resetZoomInstant();
        swipeOffset.value = 0;
        animating.value = false;
        pendingDir.value = null;
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
});
</script>

<template>
    <Teleport to="body" :disabled="!isMounted">
        <div
            v-if="open"
            class="fixed inset-0 z-[70] flex select-none items-center justify-center overflow-hidden bg-black"
            style="touch-action: none; overscroll-behavior: contain;"
            @click.self="emit('close')"
            @wheel="onWheel"
        >
            <!-- Close -->
            <button
                type="button"
                class="absolute right-4 top-4 z-20 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white text-xl transition-colors hover:bg-white/20"
                @click="emit('close')"
            >✕</button>

            <!-- Prev (desktop) -->
            <button
                v-if="images.length > 1"
                type="button"
                class="absolute left-4 z-20 hidden h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white text-3xl transition-colors hover:bg-white/20 sm:flex"
                @click.stop="prev"
            >‹</button>

            <!-- Image container -->
            <div
                class="relative flex h-full w-full items-center justify-center overflow-hidden"
                @touchstart.passive="onTouchStart"
                @touchmove="onTouchMove"
                @touchend="onTouchEnd"
                @dblclick="onDoubleClick"
            >
                <!-- Qo'shni rasm: surish yoki slide animatsiyasi chog'ida joriy
                     rasm yonida ko'rinadi. translateX(±100%) — qo'shni rasm
                     o'z konteyneri viewport tashqarisidan boshlanadi. Joriy
                     rasm bilan bir vaqtda translateX(swipeOffset) bilan
                     siljiydi, shu sababli ular orasida bo'sh joy bo'lmaydi. -->
                <div
                    v-if="adjacentIdx !== null"
                    class="pointer-events-none absolute inset-0 flex items-center justify-center"
                    :style="{
                        transform: pendingDir === 'next'
                            ? `translateX(calc(100% + ${swipeOffset}px))`
                            : `translateX(calc(-100% + ${swipeOffset}px))`,
                        transition: animating ? `transform ${SLIDE_DURATION}ms ease` : 'none',
                        willChange: 'transform',
                    }"
                >
                    <img
                        :src="images[adjacentIdx]"
                        class="max-h-[95vh] max-w-[95vw] object-contain"
                        draggable="false"
                    />
                </div>

                <img
                    ref="imgRef"
                    :src="images[currentIdx]"
                    class="max-h-[95vh] max-w-[95vw] object-contain"
                    :style="{
                        transform: `translate(${tx + swipeOffset}px, ${ty}px) scale(${scale})`,
                        transition: animating ? `transform ${SLIDE_DURATION}ms ease` : 'none',
                        willChange: 'transform',
                    }"
                    draggable="false"
                />
            </div>

            <!-- Next (desktop) -->
            <button
                v-if="images.length > 1"
                type="button"
                class="absolute right-4 z-20 hidden h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white text-3xl transition-colors hover:bg-white/20 sm:flex"
                @click.stop="next"
            >›</button>

            <!-- Counter -->
            <div v-if="images.length > 1" class="pointer-events-none absolute bottom-4 left-1/2 z-10 -translate-x-1/2 rounded-full bg-white/10 px-3 py-1 text-sm text-white">
                {{ currentIdx + 1 }} / {{ images.length }}
            </div>

            <!-- Mobile hint -->
            <div class="pointer-events-none absolute top-4 left-1/2 z-10 -translate-x-1/2 rounded-full bg-white/10 px-3 py-1 text-[10px] text-white opacity-60 sm:hidden">
                Ikki barmoq — zoom · Surib — keyingi
            </div>
        </div>
    </Teleport>
</template>
