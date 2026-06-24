<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Calculator, Check } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useLocalePath } from '@/composables/useLocalePath';

defineOptions({ layout: null });

const { lp } = useLocalePath();

type Model = 'shop' | 'order' | 'team';

const model = ref<Model>('shop');

const shopCount = ref(50);
const orderCount = ref(1200);
const deliverymanCount = ref(5);

const PRICE_PER_SHOP = 30_000;
const PRICE_PER_ORDER = 1_500;
const PRICE_PER_DELIVERYMAN = 300_000;

const monthly = computed(() => {
    switch (model.value) {
        case 'shop':
            return shopCount.value * PRICE_PER_SHOP;
        case 'order':
            return orderCount.value * PRICE_PER_ORDER;
        case 'team':
            return deliverymanCount.value * PRICE_PER_DELIVERYMAN;
    }
});

const annual = computed(() => monthly.value * 12);

const formatter = new Intl.NumberFormat('uz-UZ');
function fmt(n: number): string {
    return formatter.format(n);
}

const seoUrl = 'https://opensales.uz/narxlar/kalkulyator';
const seoImage = 'https://opensales.uz/og-image.png';

const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'WebApplication',
    name: 'OpenSales narx kalkulyatori',
    url: seoUrl,
    applicationCategory: 'BusinessApplication',
    description: "OpenSales tariflari uchun interaktiv hisob-kitob kalkulyatori. Do'kon, buyurtma yoki jamoa hajmi bo'yicha oylik to'lov.",
    offers: {
        '@type': 'AggregateOffer',
        priceCurrency: 'UZS',
        lowPrice: '30000',
        highPrice: '300000',
        offerCount: '3',
    },
};

const jsonLdString = JSON.stringify(jsonLd);
</script>

<template>
    <Head title="Narx kalkulyatori — OpenSales tariflar hisoblash">
        <meta
            name="description"
            content="OpenSales tariflari uchun interaktiv hisob-kitob: do'kondan, buyurtmadan yoki jamoa hajmidan. Oylik va yillik to'lov darhol ko'rinadi."
        />
        <meta name="robots" content="index, follow" />
        <link rel="canonical" :href="seoUrl" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="OpenSales narx kalkulyatori" />
        <meta property="og:url" :content="seoUrl" />
        <meta property="og:image" :content="seoImage" />

        <component :is="'script'" type="application/ld+json" :inner-html="jsonLdString" />
    </Head>

    <div class="min-h-svh bg-background pt-16 text-foreground">
        <PublicHeader />

        <main class="mx-auto w-full max-w-4xl px-4 py-12 md:px-6 md:py-16">
            <nav aria-label="Breadcrumb" class="mb-6 text-sm text-muted-foreground">
                <ol class="flex flex-wrap items-center gap-2">
                    <li><Link href="/" class="hover:text-foreground">Bosh sahifa</Link></li>
                    <li aria-hidden="true">/</li>
                    <li class="text-foreground">Narx kalkulyatori</li>
                </ol>
            </nav>

            <header class="mb-10 max-w-3xl">
                <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs text-primary">
                    <Calculator class="size-3.5" /> Interaktiv hisob
                </div>
                <h1 class="mt-4 text-3xl font-bold tracking-tight md:text-4xl">
                    OpenSales narx kalkulyatori
                </h1>
                <p class="mt-4 text-base text-muted-foreground md:text-lg">
                    Sizning savdo hajmingiz va biznes modelingizga ko'ra eng mos tarifni tanlang.
                    Barcha tariflarda imkoniyatlar to'liq — farq faqat hisoblash usulida.
                </p>
            </header>

            <section class="mb-8 grid gap-4 md:grid-cols-3">
                <button
                    type="button"
                    :class="[
                        'rounded-xl border p-5 text-left transition',
                        model === 'shop' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border bg-card hover:border-primary/50',
                    ]"
                    @click="model = 'shop'"
                >
                    <div class="text-sm font-semibold">Do'kondan</div>
                    <div class="mt-2 text-xs text-muted-foreground">Har faol do'kon × oylik summa</div>
                    <div class="mt-3 text-lg font-bold">{{ fmt(PRICE_PER_SHOP) }} so'm / do'kon</div>
                </button>

                <button
                    type="button"
                    :class="[
                        'rounded-xl border p-5 text-left transition',
                        model === 'order' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border bg-card hover:border-primary/50',
                    ]"
                    @click="model = 'order'"
                >
                    <div class="text-sm font-semibold">Buyurtmadan</div>
                    <div class="mt-2 text-xs text-muted-foreground">Har buyurtma uchun summa</div>
                    <div class="mt-3 text-lg font-bold">{{ fmt(PRICE_PER_ORDER) }} so'm / buyurtma</div>
                </button>

                <button
                    type="button"
                    :class="[
                        'rounded-xl border p-5 text-left transition',
                        model === 'team' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border bg-card hover:border-primary/50',
                    ]"
                    @click="model = 'team'"
                >
                    <div class="text-sm font-semibold">Jamoa hajmidan</div>
                    <div class="mt-2 text-xs text-muted-foreground">Yetkazib beruvchi × oylik</div>
                    <div class="mt-3 text-lg font-bold">{{ fmt(PRICE_PER_DELIVERYMAN) }} so'm / kishi</div>
                </button>
            </section>

            <section class="mb-8 rounded-xl border border-border bg-card p-6 md:p-8">
                <div v-if="model === 'shop'">
                    <label class="block text-sm font-medium">Faol do'konlar soni</label>
                    <input
                        v-model.number="shopCount"
                        type="range"
                        min="10"
                        max="500"
                        step="10"
                        class="mt-3 w-full accent-primary"
                    />
                    <div class="mt-2 flex items-center justify-between text-sm text-muted-foreground">
                        <span>10</span>
                        <span class="text-lg font-bold text-foreground">{{ fmt(shopCount) }}</span>
                        <span>500</span>
                    </div>
                </div>

                <div v-else-if="model === 'order'">
                    <label class="block text-sm font-medium">Oylik buyurtma soni</label>
                    <input
                        v-model.number="orderCount"
                        type="range"
                        min="100"
                        max="5000"
                        step="100"
                        class="mt-3 w-full accent-primary"
                    />
                    <div class="mt-2 flex items-center justify-between text-sm text-muted-foreground">
                        <span>100</span>
                        <span class="text-lg font-bold text-foreground">{{ fmt(orderCount) }}</span>
                        <span>5 000</span>
                    </div>
                </div>

                <div v-else>
                    <label class="block text-sm font-medium">Yetkazib beruvchilar soni</label>
                    <input
                        v-model.number="deliverymanCount"
                        type="range"
                        min="1"
                        max="30"
                        step="1"
                        class="mt-3 w-full accent-primary"
                    />
                    <div class="mt-2 flex items-center justify-between text-sm text-muted-foreground">
                        <span>1</span>
                        <span class="text-lg font-bold text-foreground">{{ fmt(deliverymanCount) }}</span>
                        <span>30</span>
                    </div>
                </div>
            </section>

            <section class="mb-10 grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-primary/40 bg-primary/5 p-6">
                    <div class="text-xs uppercase tracking-wider text-muted-foreground">Oylik to'lov</div>
                    <div class="mt-2 text-3xl font-bold md:text-4xl">
                        {{ fmt(monthly) }}
                        <span class="text-base font-medium text-muted-foreground">so'm / oy</span>
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-card p-6">
                    <div class="text-xs uppercase tracking-wider text-muted-foreground">Yillik to'lov</div>
                    <div class="mt-2 text-3xl font-bold md:text-4xl">
                        {{ fmt(annual) }}
                        <span class="text-base font-medium text-muted-foreground">so'm / yil</span>
                    </div>
                </div>
            </section>

            <section class="mb-10 rounded-xl border border-border bg-muted/30 p-6">
                <h2 class="mb-3 text-lg font-semibold">Barcha tariflarda kiritilgan</h2>
                <ul class="grid gap-2 text-sm md:grid-cols-2">
                    <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-primary" /> Shaxsiy Telegram bot</li>
                    <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-primary" /> Katalog, savat, buyurtma</li>
                    <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-primary" /> Qarzdorlik va aging</li>
                    <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-primary" /> Yetkazib berish marshruti</li>
                    <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-primary" /> Excel/CSV/PDF eksport</li>
                    <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-primary" /> Ommaviy xabarnoma</li>
                    <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-primary" /> Audit jurnali</li>
                    <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-primary" /> Xodimlar va rollar</li>
                </ul>
            </section>

            <section class="rounded-xl border border-primary/40 bg-primary/5 p-8 text-center">
                <h2 class="text-2xl font-bold">Aniq narxni ko'ribsiz</h2>
                <p class="mx-auto mt-3 max-w-xl text-sm text-muted-foreground">
                    Demo so'rang — sizning savdoringizga moslashtirilgan tarifni va 5 daqiqada
                    ishlayotgan tizimni bir vaqtda ko'rasiz.
                </p>
                <Link
                    :href="`${lp('/')}#contact`"
                    class="mt-6 inline-flex h-11 items-center justify-center rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    Bepul demo
                </Link>
            </section>
        </main>
    </div>
</template>
