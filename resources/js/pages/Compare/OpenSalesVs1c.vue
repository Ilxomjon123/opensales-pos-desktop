<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Check, X } from 'lucide-vue-next';
import PublicHeader from '@/components/PublicHeader.vue';
import { useLocalePath } from '@/composables/useLocalePath';

defineOptions({ layout: null });

const { lp } = useLocalePath();

interface Row {
    feature: string;
    opensales: boolean | string;
    oneC: boolean | string;
    note?: string;
}

const rows: Row[] = [
    { feature: 'Joriy etish vaqti', opensales: '5 daqiqa', oneC: '3-6 oy' },
    { feature: 'Implementatsiya narxi', opensales: 'Kelishuv asosida (past)', oneC: '50-100 mln so\'m' },
    { feature: 'O\'zbek tilidagi interfeys', opensales: true, oneC: 'Qisman' },
    { feature: 'So\'mda hisob (UZS)', opensales: true, oneC: 'Sozlash kerak' },
    { feature: 'Telegram bot orqali buyurtma', opensales: true, oneC: false },
    { feature: 'Multi-bot arxitektura (har distribyutor o\'ziga)', opensales: true, oneC: false },
    { feature: 'Mobil ilova talab qilinmaydi', opensales: true, oneC: false },
    { feature: '24/7 buyurtma qabul', opensales: true, oneC: 'Agent vaqtida' },
    { feature: 'Real-time qarzdorlik nazorati', opensales: true, oneC: 'Kunlik yangilanish' },
    { feature: 'Aging hisoboti (0-30/30-60/60+)', opensales: true, oneC: 'Qo\'lda hisob' },
    { feature: 'Yetkazib berish marshruti', opensales: true, oneC: 'Modul qo\'shimcha' },
    { feature: 'Ommaviy xabarnoma (broadcast)', opensales: true, oneC: false },
    { feature: 'Excel/CSV/PDF eksport', opensales: true, oneC: true },
    { feature: 'Audit jurnali', opensales: true, oneC: true },
    { feature: 'Bulutda saqlanadi', opensales: true, oneC: 'On-premise' },
    { feature: 'Avtomatik yangilanishlar', opensales: true, oneC: 'Qo\'lda' },
    { feature: 'Texnik xodim talabi', opensales: 'Yo\'q', oneC: 'Doimiy IT bo\'lim' },
    { feature: 'Mahalliy qo\'llab-quvvatlash (O\'zbek)', opensales: true, oneC: 'Cheklangan' },
    { feature: 'Narx modeli', opensales: '3 xil (do\'kondan, buyurtmadan, jamoa)', oneC: 'Foydalanuvchi soni' },
    { feature: 'Bepul demo', opensales: true, oneC: 'Sotuv menejer orqali' },
];

const seoUrl = 'https://opensales.uz/taqqoslash/opensales-vs-1c';
const seoImage = 'https://opensales.uz/og-image.png';

const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: 'OpenSales vs 1C: distribyutsiya tizimi taqqoslash',
    description: 'OpenSales va 1C distribyutsiya tizimlarini taqqoslash: narx, joriy etish vaqti, mahalliy moslashuv, Telegram bot integratsiyasi.',
    image: seoImage,
    datePublished: '2026-05-21',
    inLanguage: 'uz',
    mainEntityOfPage: { '@type': 'WebPage', '@id': seoUrl },
    publisher: {
        '@type': 'Organization',
        name: 'OpenSales',
        logo: { '@type': 'ImageObject', url: 'https://opensales.uz/apple-touch-icon.png' },
    },
};

const breadcrumbLd = {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
        { '@type': 'ListItem', position: 1, name: 'Bosh sahifa', item: 'https://opensales.uz/' },
        { '@type': 'ListItem', position: 2, name: 'Taqqoslash', item: 'https://opensales.uz/taqqoslash' },
        { '@type': 'ListItem', position: 3, name: 'OpenSales vs 1C', item: seoUrl },
    ],
};

const jsonLdString = JSON.stringify(jsonLd);
const breadcrumbLdString = JSON.stringify(breadcrumbLd);
</script>

<template>
    <Head title="OpenSales vs 1C — distribyutsiya tizimi taqqoslash | OpenSales">
        <meta
            name="description"
            content="OpenSales va 1C distribyutsiya tizimlarini taqqoslash: narx, joriy etish vaqti, mahalliy moslashuv, Telegram bot, qarzdorlik nazorati."
        />
        <meta name="robots" content="index, follow, max-image-preview:large" />
        <link rel="canonical" :href="seoUrl" />
        <meta property="og:type" content="article" />
        <meta property="og:title" content="OpenSales vs 1C — distribyutsiya tizimi taqqoslash" />
        <meta property="og:url" :content="seoUrl" />
        <meta property="og:image" :content="seoImage" />

        <component :is="'script'" type="application/ld+json" :inner-html="jsonLdString" />
        <component :is="'script'" type="application/ld+json" :inner-html="breadcrumbLdString" />
    </Head>

    <div class="min-h-svh bg-background pt-16 text-foreground">
        <PublicHeader />

        <main class="mx-auto w-full max-w-4xl px-4 py-12 md:px-6 md:py-16">
            <nav aria-label="Breadcrumb" class="mb-6 text-sm text-muted-foreground">
                <ol class="flex flex-wrap items-center gap-2">
                    <li><Link href="/" class="hover:text-foreground">Bosh sahifa</Link></li>
                    <li aria-hidden="true">/</li>
                    <li class="text-foreground">OpenSales vs 1C</li>
                </ol>
            </nav>

            <header class="mb-10 max-w-3xl">
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                    OpenSales vs 1C — distribyutsiya tizimi taqqoslash
                </h1>
                <p class="mt-4 text-base text-muted-foreground md:text-lg">
                    Distribyutsiya boshqaruv tizimi tanlashda 1C bilan OpenSales o'rtasidagi farqlarni
                    aniq sonlar va imkoniyatlar bo'yicha taqqoslash. O'zbekiston FMCG bozoriga
                    moslashtirilgan.
                </p>
            </header>

            <section class="mb-10">
                <h2 class="mb-4 text-xl font-semibold">Qisqacha xulosa</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-primary/40 bg-primary/5 p-6">
                        <h3 class="text-lg font-semibold">OpenSales</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Telegram bot va veb panel asosida 5 daqiqada ishga tushadigan
                            bulutli SaaS tizim. O'zbekiston bozoriga moslashtirilgan.
                        </p>
                        <ul class="mt-4 space-y-2 text-sm">
                            <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-primary" /> Joriy etish tez, narxi past</li>
                            <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-primary" /> Mahalliy narx (so'mda)</li>
                            <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-primary" /> Telegram bot orqali buyurtma</li>
                        </ul>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-6">
                        <h3 class="text-lg font-semibold">1C</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Universal ERP — distribyutsiya moduli modullar orqali sozlanadi.
                            Implementatsiya uchun jiddiy investitsiya va vaqt talab qiladi.
                        </p>
                        <ul class="mt-4 space-y-2 text-sm">
                            <li class="flex items-start gap-2"><X class="mt-0.5 size-4 text-muted-foreground" /> 3-6 oy implementatsiya</li>
                            <li class="flex items-start gap-2"><X class="mt-0.5 size-4 text-muted-foreground" /> IT bo'lim talabi</li>
                            <li class="flex items-start gap-2"><X class="mt-0.5 size-4 text-muted-foreground" /> Telegram bot yo'q</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="mb-10">
                <h2 class="mb-4 text-xl font-semibold">Taqqoslash jadvali</h2>
                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Imkoniyat</th>
                                <th class="px-4 py-3 text-center font-medium text-primary">OpenSales</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground">1C</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.feature" class="border-t border-border/60">
                                <td class="px-4 py-3 font-medium">{{ row.feature }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="row.opensales === true" class="inline-flex items-center text-primary">
                                        <Check class="size-4" />
                                    </span>
                                    <span v-else-if="row.opensales === false" class="inline-flex items-center text-muted-foreground">
                                        <X class="size-4" />
                                    </span>
                                    <span v-else>{{ row.opensales }}</span>
                                </td>
                                <td class="px-4 py-3 text-center text-muted-foreground">
                                    <span v-if="row.oneC === true" class="inline-flex items-center">
                                        <Check class="size-4" />
                                    </span>
                                    <span v-else-if="row.oneC === false" class="inline-flex items-center">
                                        <X class="size-4" />
                                    </span>
                                    <span v-else>{{ row.oneC }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mb-10">
                <h2 class="mb-4 text-xl font-semibold">Qachon OpenSales tanlash kerak</h2>
                <ul class="space-y-3 text-sm leading-relaxed">
                    <li class="flex items-start gap-3">
                        <Check class="mt-0.5 size-5 shrink-0 text-primary" />
                        <span>Distribyutsiya bilan shug'ullanasiz va mijozlaringiz Telegram'da turibdi.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <Check class="mt-0.5 size-5 shrink-0 text-primary" />
                        <span>Tezda ishga tushirish kerak — implementatsiyaga oy berilmaydi.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <Check class="mt-0.5 size-5 shrink-0 text-primary" />
                        <span>IT bo'lim yoki doimiy texnik xodim yo'q.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <Check class="mt-0.5 size-5 shrink-0 text-primary" />
                        <span>Narx aniq va bashoratli bo'lishi kerak — kelajakda yashirin xarajat yo'q.</span>
                    </li>
                </ul>
            </section>

            <section class="mb-10">
                <h2 class="mb-4 text-xl font-semibold">Qachon 1C qulayroq</h2>
                <ul class="space-y-3 text-sm leading-relaxed text-muted-foreground">
                    <li class="flex items-start gap-3">
                        <Check class="mt-0.5 size-5 shrink-0" />
                        <span>Murakkab buxgalteriya, soliq hisoboti integratsiyasi birinchi o'rinda.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <Check class="mt-0.5 size-5 shrink-0" />
                        <span>Korxonangizda allaqachon 1C joriy etilgan va biriktirish kerak.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <Check class="mt-0.5 size-5 shrink-0" />
                        <span>On-premise yechim majburiy (mijoz ma'lumotlari serverda saqlanishi kerak).</span>
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border border-primary/40 bg-primary/5 p-8 text-center">
                <h2 class="text-2xl font-bold">Demo so'rab ko'ring</h2>
                <p class="mx-auto mt-3 max-w-xl text-sm text-muted-foreground">
                    5 daqiqada to'liq ishlayotgan tizim. Telegram bot ulanadi, katalog yuklanadi,
                    do'konchilar buyurtma berishni boshlaydi.
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
