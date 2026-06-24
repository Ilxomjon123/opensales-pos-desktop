<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount } from 'vue';
import { X } from 'lucide-vue-next';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useNavItems } from '@/composables/useNavItems';

const open = defineModel<boolean>('open', { default: false });

const { navGroups } = useNavItems();
const { isCurrentUrl } = useCurrentUrl();

const stopListener = router.on('start', () => {
    open.value = false;
});

onBeforeUnmount(() => {
    stopListener();
});
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            side="bottom"
            class="inset-0 h-svh max-h-svh w-full max-w-full overflow-hidden rounded-none border-0 bg-gradient-to-b from-background via-background to-muted/30 p-0 [&>button:last-of-type]:hidden"
        >
            <SheetHeader
                class="relative z-20 border-b border-sidebar-border/40 bg-background/80 px-4 pt-[calc(0.75rem+env(safe-area-inset-top))] pb-3 backdrop-blur-xl"
            >
                <SheetTitle class="text-center text-base font-semibold tracking-tight">Menyu</SheetTitle>
                <SheetDescription class="sr-only">Barcha bo'limlar ro'yxati</SheetDescription>
                <SheetClose
                    class="absolute right-3 top-[calc(0.5rem+env(safe-area-inset-top))] flex h-10 w-10 items-center justify-center rounded-full bg-muted/70 text-foreground/80 shadow-sm ring-1 ring-sidebar-border/50 backdrop-blur transition-all hover:bg-muted hover:text-foreground active:scale-95"
                >
                    <X class="h-5 w-5" />
                    <span class="sr-only">Yopish</span>
                </SheetClose>
            </SheetHeader>

            <div class="flex-1 overflow-y-auto px-4 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
                <div
                    v-for="(group, index) in navGroups"
                    :key="group.label ?? index"
                    class="mb-5 last:mb-0"
                >
                    <h3
                        v-if="group.label"
                        class="mb-2.5 flex items-center gap-2 px-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                    >
                        <span class="h-1 w-1 rounded-full bg-primary/70" />
                        {{ group.label }}
                    </h3>
                    <div class="grid grid-cols-3 gap-2.5">
                        <Link
                            v-for="item in group.items"
                            :key="item.title"
                            :href="item.href"
                            prefetch="hover"
                            :cache-for="['30s', '1m']"
                            class="group relative flex flex-col items-center justify-center gap-2 overflow-hidden rounded-2xl border border-sidebar-border/40 bg-gradient-to-b from-card to-card/40 px-2 py-3.5 text-center shadow-sm transition-all duration-200 active:scale-[0.97] active:shadow-none"
                            :class="isCurrentUrl(item.href)
                                ? 'border-primary/50 bg-gradient-to-b from-primary/15 to-primary/5 shadow-[0_4px_16px_-6px_rgba(0,0,0,0.2)]'
                                : 'hover:border-sidebar-border/70 hover:bg-card'"
                        >
                            <span
                                v-if="item.badge !== null && item.badge !== undefined && item.badge !== '' && item.badge !== 0"
                                class="absolute right-1.5 top-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-orange-500 px-1.5 text-[10px] font-bold leading-none text-white shadow"
                            >
                                {{ item.badge }}
                            </span>
                            <span
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-primary/20 to-primary/5 text-primary ring-1 ring-inset ring-primary/15 transition-transform duration-200 group-active:scale-95"
                                :class="isCurrentUrl(item.href) ? 'from-primary/30 to-primary/10 ring-primary/25' : ''"
                            >
                                <component :is="item.icon" class="h-5 w-5" :stroke-width="isCurrentUrl(item.href) ? 2.4 : 2" />
                            </span>
                            <span
                                class="line-clamp-2 text-[11px] font-medium leading-tight"
                                :class="isCurrentUrl(item.href) ? 'text-foreground' : 'text-foreground/85'"
                            >
                                {{ item.title }}
                            </span>
                        </Link>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
