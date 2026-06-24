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
    salesDoctor: boolean | string;
    note?: string;
}

const rows: Row[] = [
    { feature: 'Joriy etish vaqti', opensales: '5 daqiqa', salesDoctor: '2-4 hafta' },
    { feature: 'Implementatsiya narxi', opensales: 'Kelishuv asosida (past)', salesDoctor: 'Setup + treyning' },
    { feature: 'Mijoz egasiga Telegram bot orqali buyurtma', opensales: true, salesDoctor: false },
    { feature: 'Mijozga alohida ilova kerakmas', opensales: true, salesDoctor: 'Mobil ilova talab' },
    { feature: 'Savdo agentisiz ishlash', opensales: true, salesDoctor: 'Agent uchun mo\'ljallangan' },
    { feature: 'Multi-bot — har distribyutor o\'z brendida', opensales: true, salesDoctor: false },
    { feature: '24/7 buyurtma qabul (avtonom)', opensales: true, salesDoctor: 'Agent vaqtida' },
    { feature: 'Mobil ilova (savdo agenti uchun)', opensales: 'Talab qilinmaydi', salesDoctor: true },
    { feature: 'Marshrut rejasi / vizit nazorati', opensales: 'Yetkazib beruvchi marshruti', salesDoctor: 'Agent marshruti — kuchli tomon' },
    { feature: 'GPS / vizit fakti', opensales: 'Kuryer GPS', salesDoctor: 'Agent GPS — kuchli tomon' },
    { feature: 'Real-time qarzdorlik nazorati', opensales: true, salesDoctor: true },
    { feature: 'Aging hisoboti (0-30/30-60/60+)', opensales: true, salesDoctor: true },
    { feature: 'Ommaviy xabarnoma (Telegram broadcast)', opensales: true, salesDoctor: false },
    { feature: 'Aksiya / chegirma (Telegram orqali)', opensales: true, salesDoctor: 'Agent qo\'lda' },
    { feature: 'Mijoz portfeli o\'sadi (self-service)', opensales: 'Mijoz o\'zi buyurtma beradi', salesDoctor: 'Agent kelishi kerak' },
    { feature: '1C / buxgalteriya integratsiyasi', opensales: 'Excel/CSV eksport', salesDoctor: '1C bilan tayyor moslar' },
    { feature: 'Bulutda saqlanadi', opensales: true, salesDoctor: true },
    { feature: 'Avtomatik yangilanishlar', opensales: true, salesDoctor: 'Agent ilovasi yangilanadi' },
    { feature: 'O\'zbek tilidagi interfeys', opensales: true, salesDoctor: true },
    { feature: 'Mahalliy qo\'llab-quvvatlash (O\'zbek)', opensales: true, salesDoctor: true },
    { feature: 'Narx modeli', opensales: '3 xil (do\'kondan, buyurtmadan, jamoa)', salesDoctor: 'Agent / litsenziya soni' },
    { feature: 'Bepul demo', opensales: true, salesDoctor: true },
];

const seoUrl = 'https://opensales.uz/taqqoslash/opensales-vs-sales-doctor';
const seoImage = 'https://opensales.uz/og-image.png';

const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: 'OpenSales vs Sales Doctor: distribyutsiya tizimi taqqoslash',
    description: 'OpenSales va Sales Doctor distribyutsiya tizimlarini taqqoslash: savdo agentisiz model, Telegram bot orqali buyurtma, marshrut nazorati va narx farqi.',
    image: seoImage,
    datePublished: '2026-05-22',
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
        { '@type': 'ListItem', position: 3, name: 'OpenSales vs Sales Doctor', item: seoUrl },
    ],
};

const jsonLdString = JSON.stringify(jsonLd);
const breadcrumbLdString = JSON.stringify(breadcrumbLd);
</script>

<template>
    <Head title="OpenSales vs Sales Doctor — distribyutsiya tizimi taqqoslash | OpenSales">
        <meta
            name="description"
            content="OpenSales va Sales Doctor: savdo agentisiz model, Telegram bot orqali avtonom buyurtma, marshrut nazorati, narx va joriy etish vaqti farqi."
        />
        <meta name="robots" content="index, follow, max-image-preview:large" />
        <link rel="canonical" :href="seoUrl" />
        <meta property="og:type" content="article" />
        <meta property="og:title" content="OpenSales vs Sales Doctor — distribyutsiya tizimi taqqoslash" />
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
                    <li class="text-foreground">OpenSales vs Sales Doctor</li>
                </ol>
            </nav>

            <header class="mb-10 max-w-3xl">
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                    OpenSales vs Sales Doctor — distribyutsiya tizimi taqqoslash
                </h1>
                <p class="mt-4 text-base text-muted-foreground md:text-lg">
                    Sales Doctor savdo agentlari uchun mobil ilova asosida ishlaydi.
                    OpenSales esa Telegram bot orqali mijoz egasining o'zi buyurtma berishini
                    nazarda tutadi — agent xarajati va vaqt yo'qoladi.
                </p>
            </header>

            <section class="mb-10">
                <h2 class="mb-4 text-xl font-semibold">Qisqacha xulosa</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-primary/40 bg-primary/5 p-6">
                        <h3 class="text-lg font-semibold">OpenSales</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Mijoz egasi Telegram bot orqali o'zi buyurtma beradi. Savdo agenti
                            shart emas — distribyutor IT xarajatini tushiradi va 24/7 buyurtma qabul qiladi.
                        </p>
                        <ul class="mt-4 space-y-2 text-sm">
                            <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-primary" /> Savdo agentisiz model</li>
                            <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-primary" /> Telegram bot — alohida ilova kerakmas</li>
                            <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-primary" /> 5 daqiqada ishga tushadi</li>
                        </ul>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-6">
                        <h3 class="text-lg font-semibold">Sales Doctor</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Savdo agentlari uchun mobil ilova: agent do'konga keladi,
                            mobil ilova orqali buyurtma tushiradi, GPS bilan vizit qayd etiladi.
                        </p>
                        <ul class="mt-4 space-y-2 text-sm">
                            <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-muted-foreground" /> Agent marshruti nazorati</li>
                            <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 text-muted-foreground" /> 1C bilan tayyor integratsiya</li>
                            <li class="flex items-start gap-2"><X class="mt-0.5 size-4 text-muted-foreground" /> Mijoz o'zi buyurtma bera olmaydi</li>
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
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground">Sales Doctor</th>
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
                                    <span v-if="row.salesDoctor === true" class="inline-flex items-center">
                                        <Check class="size-4" />
                                    </span>
                                    <span v-else-if="row.salesDoctor === false" class="inline-flex items-center">
                                        <X class="size-4" />
                                    </span>
                                    <span v-else>{{ row.salesDoctor }}</span>
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
                        <span>Mijoz egalari Telegram'da turibdi va o'zlari buyurtma berishni xohlaydi.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <Check class="mt-0.5 size-5 shrink-0 text-primary" />
                        <span>Savdo agentlar shtati katta va xarajat kamaytirish kerak.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <Check class="mt-0.5 size-5 shrink-0 text-primary" />
                        <span>Mijoz portfeli kengaytirilishi kerak — agent yetib bormaydigan do'konlar qoladi.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <Check class="mt-0.5 size-5 shrink-0 text-primary" />
                        <span>Aksiya va xabarnoma Telegram orqali bir zumda barcha mijozlarga yetkaziladi.</span>
                    </li>
                </ul>
            </section>

            <section class="mb-10">
                <h2 class="mb-4 text-xl font-semibold">Qachon Sales Doctor qulayroq</h2>
                <ul class="space-y-3 text-sm leading-relaxed text-muted-foreground">
                    <li class="flex items-start gap-3">
                        <Check class="mt-0.5 size-5 shrink-0" />
                        <span>Savdo modelingiz to'liq agent asosida — agent har do'konga shaxsan kelishi shart.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <Check class="mt-0.5 size-5 shrink-0" />
                        <span>Agent vizit fakti (GPS, vaqt) majburiy hisobot elementi.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <Check class="mt-0.5 size-5 shrink-0" />
                        <span>1C bilan chuqur ikki tomonlama integratsiya darhol kerak.</span>
                    </li>
                </ul>
            </section>

            <section class="mb-10 rounded-xl border border-border bg-card p-6">
                <h2 class="mb-3 text-lg font-semibold">Ikkala model birga ishlatish</h2>
                <p class="text-sm leading-relaxed text-muted-foreground">
                    OpenSales va agent asosli tizim qarama-qarshi emas. Faol mijozlar Telegram bot
                    orqali o'zi buyurtma bersa, agent yangi mijoz qidirish va katta buyurtmalar bilan
                    shug'ullansa — agent vaqti samaraliroq sarflanadi va distribyutor portfeli
                    o'sadi. OpenSales shu rolni bajaradi.
                </p>
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
