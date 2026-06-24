<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Bot,
    Box,
    Calculator,
    ChartLine,
    CheckCircle2,
    ClipboardList,
    Clock,
    FileSpreadsheet,
    Map,
    Megaphone,
    MessageSquare,
    Network,
    Package,
    PhoneCall,
    Rocket,
    Scale,
    Send,
    ShieldCheck,
    ShoppingCart,
    Smartphone,
    Sparkles,
    Store,
    TrendingUp,
    Users,
    Wallet,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useLocalePath } from '@/composables/useLocalePath';
import { login } from '@/routes';

defineOptions({ layout: null });

const { t } = useI18n();
const { lp } = useLocalePath();

const brandName = computed(() => usePage().props.project?.name ?? '');

function scrollToSection(id: string): void {
    const target = document.getElementById(id);

    if (!target) {
        return;
    }

    const headerOffset = 64;
    const top = target.getBoundingClientRect().top + window.scrollY - headerOffset;
    window.scrollTo({ top, behavior: 'smooth' });

    if (history.replaceState) {
        history.replaceState(null, '', `#${id}`);
    }
}

const audiences = computed(() => [
    {
        icon: Users,
        title: t('pageWelcome.audiences.distributors.title'),
        text: t('pageWelcome.audiences.distributors.text'),
    },
    {
        icon: Store,
        title: t('pageWelcome.audiences.shopOwners.title'),
        text: t('pageWelcome.audiences.shopOwners.text'),
    },
    {
        icon: Map,
        title: t('pageWelcome.audiences.deliverymen.title'),
        text: t('pageWelcome.audiences.deliverymen.text'),
    },
]);

const features = computed(() => [
    { icon: Bot, title: t('pageWelcome.features.bot.title'), text: t('pageWelcome.features.bot.text') },
    { icon: Smartphone, title: t('pageWelcome.features.miniApp.title'), text: t('pageWelcome.features.miniApp.text') },
    { icon: ShoppingCart, title: t('pageWelcome.features.cart.title'), text: t('pageWelcome.features.cart.text') },
    { icon: Box, title: t('pageWelcome.features.catalog.title'), text: t('pageWelcome.features.catalog.text') },
    { icon: Map, title: t('pageWelcome.features.routes.title'), text: t('pageWelcome.features.routes.text') },
    { icon: Wallet, title: t('pageWelcome.features.finance.title'), text: t('pageWelcome.features.finance.text') },
    { icon: Megaphone, title: t('pageWelcome.features.broadcast.title'), text: t('pageWelcome.features.broadcast.text') },
    { icon: Package, title: t('pageWelcome.features.promotions.title'), text: t('pageWelcome.features.promotions.text') },
    { icon: ClipboardList, title: t('pageWelcome.features.audit.title'), text: t('pageWelcome.features.audit.text') },
    { icon: Activity, title: t('pageWelcome.features.botHealth.title'), text: t('pageWelcome.features.botHealth.text') },
    { icon: Network, title: t('pageWelcome.features.roles.title'), text: t('pageWelcome.features.roles.text') },
    { icon: ShieldCheck, title: t('pageWelcome.features.security.title'), text: t('pageWelcome.features.security.text') },
]);

const advantages = computed(() => [
    { icon: Clock, title: t('pageWelcome.advantages.alwaysOn.title'), text: t('pageWelcome.advantages.alwaysOn.text') },
    { icon: TrendingUp, title: t('pageWelcome.advantages.salesGrowth.title'), text: t('pageWelcome.advantages.salesGrowth.text') },
    { icon: Zap, title: t('pageWelcome.advantages.fast.title'), text: t('pageWelcome.advantages.fast.text') },
    { icon: ChartLine, title: t('pageWelcome.advantages.reports.title'), text: t('pageWelcome.advantages.reports.text') },
    { icon: Sparkles, title: t('pageWelcome.advantages.brand.title'), text: t('pageWelcome.advantages.brand.text') },
    { icon: Rocket, title: t('pageWelcome.advantages.launch.title'), text: t('pageWelcome.advantages.launch.text') },
]);

const reports = computed(() => [
    t('pageWelcome.reports.income'),
    t('pageWelcome.reports.rating'),
    t('pageWelcome.reports.aging'),
    t('pageWelcome.reports.delivery'),
    t('pageWelcome.reports.audit'),
    t('pageWelcome.reports.botStats'),
    t('pageWelcome.reports.export'),
]);

const flow = computed(() => [
    { step: '01', title: t('pageWelcome.flow.step1.title'), text: t('pageWelcome.flow.step1.text') },
    { step: '02', title: t('pageWelcome.flow.step2.title'), text: t('pageWelcome.flow.step2.text') },
    { step: '03', title: t('pageWelcome.flow.step3.title'), text: t('pageWelcome.flow.step3.text') },
    { step: '04', title: t('pageWelcome.flow.step4.title'), text: t('pageWelcome.flow.step4.text') },
    { step: '05', title: t('pageWelcome.flow.step5.title'), text: t('pageWelcome.flow.step5.text') },
]);

type PricingPlan = {
    name: string;
    tagline: string;
    description: string;
    formula: string;
    formulaCaption: string;
    example: string;
    benefits: string[];
    icon: typeof TrendingUp;
    accent: 'primary' | 'emerald' | 'amber' | 'sky';
    highlighted: boolean;
};

const pricingPlans = computed<PricingPlan[]>(() => [
    {
        name: t('pageWelcome.pricingPlans.shop.name'),
        tagline: t('pageWelcome.pricingPlans.shop.tagline'),
        description: t('pageWelcome.pricingPlans.shop.description'),
        formula: t('pageWelcome.pricingPlans.shop.formula'),
        formulaCaption: t('pageWelcome.pricingPlans.shop.formulaCaption'),
        example: t('pageWelcome.pricingPlans.shop.example'),
        benefits: [
            t('pageWelcome.pricingPlans.shop.benefit1'),
            t('pageWelcome.pricingPlans.shop.benefit2'),
            t('pageWelcome.pricingPlans.shop.benefit3'),
        ],
        icon: Store,
        accent: 'emerald',
        highlighted: false,
    },
    {
        name: t('pageWelcome.pricingPlans.order.name'),
        tagline: t('pageWelcome.pricingPlans.order.tagline'),
        description: t('pageWelcome.pricingPlans.order.description'),
        formula: t('pageWelcome.pricingPlans.order.formula'),
        formulaCaption: t('pageWelcome.pricingPlans.order.formulaCaption'),
        example: t('pageWelcome.pricingPlans.order.example'),
        benefits: [
            t('pageWelcome.pricingPlans.order.benefit1'),
            t('pageWelcome.pricingPlans.order.benefit2'),
            t('pageWelcome.pricingPlans.order.benefit3'),
        ],
        icon: ShoppingCart,
        accent: 'primary',
        highlighted: true,
    },
    {
        name: t('pageWelcome.pricingPlans.team.name'),
        tagline: t('pageWelcome.pricingPlans.team.tagline'),
        description: t('pageWelcome.pricingPlans.team.description'),
        formula: t('pageWelcome.pricingPlans.team.formula'),
        formulaCaption: t('pageWelcome.pricingPlans.team.formulaCaption'),
        example: t('pageWelcome.pricingPlans.team.example'),
        benefits: [
            t('pageWelcome.pricingPlans.team.benefit1'),
            t('pageWelcome.pricingPlans.team.benefit2'),
            t('pageWelcome.pricingPlans.team.benefit3'),
        ],
        icon: Map,
        accent: 'sky',
        highlighted: false,
    },
]);

// JSON-LD uchun statik (uz) — SEO maqsadlari uchun ingliz/uz tilida qoladi
const faqs = [
    {
        q: 'OpenSales nima?',
        a: "OpenSales — distribyutorlar va mijoz egalari uchun mo'ljallangan O'zbekistonda ishlab chiqilgan savdo platformasi. Har bir distribyutor uchun alohida Telegram bot, veb panel va real vaqt rejimidagi hisobotlar bir tizimda jamlangan.",
    },
    {
        q: 'Tizim qanday ishlaydi?',
        a: "Distribyutor ro'yxatdan o'tadi va Telegram bot tokenini biriktiradi. Katalog yuklanadi, mijoz egalari Telegram bot yoki mobil ilova orqali ulanadi, buyurtmalar to'g'ridan-to'g'ri tashkilot paneliga tushadi, yetkazib beruvchi mahsulotni olib boradi va to'lov qayd etiladi.",
    },
    {
        q: 'OpenSales narxi qancha?',
        a: "Uch xil tarif mavjud: mijoz soniga qarab, buyurtma soniga qarab yoki yetkazib beruvchilar soniga qarab. Barcha tariflarda imkoniyatlar to'liq, farq faqat to'lovni hisoblash usulida.",
    },
    {
        q: 'Tizimni ishga tushirish uchun qancha vaqt ketadi?',
        a: "Telegram bot tokenini kiritish kifoya — 5 daqiqa ichida katalog faoliyat boshlaydi va mijoz egalari buyurtma bera oladi.",
    },
    {
        q: 'Har bir distribyutor uchun alohida bot kerakmi?',
        a: "Ha. OpenSales multi-bot arxitekturada ishlaydi: har bir distribyutor o'z brendi ostida shaxsiy Telegram bot oladi. Logotip, nom va tavsif tashkilotning o'ziga xos ko'rinishini saqlaydi.",
    },
    {
        q: "Buyurtma faqat Telegram orqaligami?",
        a: "Yo'q. Mijoz egasi buyurtmani Telegram bot orqali yoki mobil ilova (Android va iOS) orqali beradi. Ikkala kanal bitta hisobda ishlaydi, shu sababli Telegram butunlay ishlamay qolsa ham savdo mobil ilova orqali to'xtamaydi.",
    },
    {
        q: 'Qarzdorlik va to\'lovlar qanday nazorat qilinadi?',
        a: "Har bir mijozning hisob qoldig'i, to'lovlar tarixi va qarzdorlikning eskirish bo'yicha taqsimoti (0-30, 30-60, 60+ kun) avtomatik kuzatiladi. Hisobotlarni Excel, CSV va PDF formatlarida eksport qilish mumkin.",
    },
    {
        q: "O'zbekiston uchun mosmi?",
        a: "Ha, OpenSales O'zbekistonda ishlab chiqilgan va mahalliy distribyutsiya bozori uchun moslashtirilgan: so'mda hisob, o'zbek tilidagi interfeys, mahalliy logistika va ish jarayonlariga moslashgan.",
    },
];

// UI uchun lokalizatsiyalashgan FAQ ro'yxati
const faqsLocalized = computed(() => [
    { q: t('pageWelcome.faqs.q1.q'), a: t('pageWelcome.faqs.q1.a') },
    { q: t('pageWelcome.faqs.q2.q'), a: t('pageWelcome.faqs.q2.a') },
    { q: t('pageWelcome.faqs.q3.q'), a: t('pageWelcome.faqs.q3.a') },
    { q: t('pageWelcome.faqs.q4.q'), a: t('pageWelcome.faqs.q4.a') },
    { q: t('pageWelcome.faqs.q5.q'), a: t('pageWelcome.faqs.q5.a') },
    { q: t('pageWelcome.faqs.q6.q'), a: t('pageWelcome.faqs.q6.a') },
    { q: t('pageWelcome.faqs.q7.q'), a: t('pageWelcome.faqs.q7.a') },
    { q: t('pageWelcome.faqs.q8.q'), a: t('pageWelcome.faqs.q8.a') },
]);

const seoUrl = 'https://opensales.uz/';
const seoImage = 'https://opensales.uz/og-image.png';
const seoUrlRu = 'https://opensales.uz/?lang=ru';

const jsonLd = {
    '@context': 'https://schema.org',
    '@graph': [
        {
            '@type': 'Organization',
            '@id': 'https://opensales.uz/#organization',
            name: 'OpenSales',
            alternateName: ['OpenSales.uz', 'Dealer Bot'],
            url: seoUrl,
            logo: {
                '@type': 'ImageObject',
                url: 'https://opensales.uz/apple-touch-icon.png',
                width: 512,
                height: 512,
            },
            image: seoImage,
            description: "Distribyutorlar va mijoz egalari uchun Telegram bot va mobil ilova orqali buyurtma qabul qiluvchi savdo platformasi.",
            areaServed: { '@type': 'Country', name: "O'zbekiston" },
            sameAs: [
                'https://t.me/opensales_uz',
            ],
            contactPoint: {
                '@type': 'ContactPoint',
                contactType: 'sales',
                availableLanguage: ['uz', 'ru'],
                areaServed: 'UZ',
            },
        },
        {
            '@type': 'LocalBusiness',
            '@id': 'https://opensales.uz/#localbusiness',
            name: 'OpenSales',
            url: seoUrl,
            image: seoImage,
            telephone: '+998 (00) 000-00-00',
            priceRange: '30 000–300 000 UZS',
            address: {
                '@type': 'PostalAddress',
                addressCountry: 'UZ',
                addressRegion: 'Toshkent shahri',
                addressLocality: 'Toshkent',
            },
            geo: {
                '@type': 'GeoCoordinates',
                latitude: 41.2995,
                longitude: 69.2401,
            },
            openingHoursSpecification: [
                {
                    '@type': 'OpeningHoursSpecification',
                    dayOfWeek: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                    opens: '09:00',
                    closes: '19:00',
                },
            ],
        },
        {
            '@type': 'WebSite',
            '@id': 'https://opensales.uz/#website',
            url: seoUrl,
            name: 'OpenSales',
            inLanguage: 'uz',
            publisher: { '@id': 'https://opensales.uz/#organization' },
        },
        {
            '@type': 'SoftwareApplication',
            '@id': 'https://opensales.uz/#software',
            name: 'OpenSales',
            applicationCategory: 'BusinessApplication',
            applicationSubCategory: 'Distribution Management Software',
            operatingSystem: 'Web, Telegram, Android, iOS',
            url: seoUrl,
            description: "Distribyutorlar uchun Telegram bot, mobil ilova (Android va iOS) va veb panelni birlashtirgan savdo, qarzdorlik nazorati, yetkazib berish marshruti va hisobot tizimi. O'zbekiston bozoriga moslashgan.",
            offers: {
                '@type': 'AggregateOffer',
                priceCurrency: 'UZS',
                lowPrice: '30000',
                highPrice: '300000',
                offerCount: '3',
            },
            featureList: [
                "Har bir distribyutor uchun shaxsiy Telegram bot",
                "Mobil ilova (Android va iOS) orqali buyurtma",
                "Telegram ishlamay qolsa ham mobil ilova orqali savdo davom etadi",
                "Telegram ichidagi katalog va savat",
                "Buyurtmalarni 24/7 qabul qilish",
                "Qarzdorlik va to'lovlar nazorati",
                "Yetkazib berish marshruti boshqaruvi",
                "Aksiya va ommaviy xabarnomalar",
                "Real vaqt rejimidagi hisobotlar va eksport",
                "Auditni yuritish jurnali",
                "Xodimlar va rollar boshqaruvi",
            ],
        },
        {
            '@type': 'FAQPage',
            '@id': 'https://opensales.uz/#faq',
            mainEntity: faqs.map((f) => ({
                '@type': 'Question',
                name: f.q,
                acceptedAnswer: { '@type': 'Answer', text: f.a },
            })),
        },
        {
            '@type': 'BreadcrumbList',
            itemListElement: [
                { '@type': 'ListItem', position: 1, name: 'Bosh sahifa', item: seoUrl },
            ],
        },
    ],
};

const jsonLdString = JSON.stringify(jsonLd);

const accentClasses: Record<PricingPlan['accent'], { iconBg: string; iconText: string; ring: string; gradient: string }> = {
    primary: {
        iconBg: 'bg-primary',
        iconText: 'text-primary-foreground',
        ring: 'ring-primary',
        gradient: 'from-primary/10 via-primary/5 to-transparent',
    },
    emerald: {
        iconBg: 'bg-emerald-500',
        iconText: 'text-white',
        ring: 'ring-emerald-500',
        gradient: 'from-emerald-500/10 via-emerald-500/5 to-transparent',
    },
    amber: {
        iconBg: 'bg-amber-500',
        iconText: 'text-white',
        ring: 'ring-amber-500',
        gradient: 'from-amber-500/10 via-amber-500/5 to-transparent',
    },
    sky: {
        iconBg: 'bg-sky-500',
        iconText: 'text-white',
        ring: 'ring-sky-500',
        gradient: 'from-sky-500/10 via-sky-500/5 to-transparent',
    },
};
</script>

<template>
    <Head title="OpenSales — Distribyutorlar uchun Telegram bot savdo platformasi | O'zbekiston">
        <meta
            name="description"
            content="OpenSales — distribyutorlar va mijoz egalari uchun O'zbekistonda ishlab chiqilgan, Telegram bot va mobil ilova (Android va iOS) orqali buyurtma qabul qiluvchi savdo platformasi. Telegram ishlamay qolsa ham savdo ilova orqali davom etadi. Katalog, savat, qarzdorlik nazorati, yetkazib berish marshruti va real vaqt hisobotlar bir tizimda."
        />
        <meta
            name="keywords"
            content="distribyutor uchun dastur, telegram bot orqali buyurtma, savdo platformasi, distribyutsiya boshqaruv tizimi, mijoz vakililar uchun bot, qarzdorlik nazorati, yetkazib berish marshruti, opensales, dealer bot, Uzbekistan distribution software"
        />
        <meta name="author" content="OpenSales" />
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1" />
        <meta name="googlebot" content="index, follow, max-image-preview:large, max-snippet:-1" />
        <meta name="bingbot" content="index, follow" />
        <meta name="yandex" content="index, follow" />
        <link rel="canonical" :href="seoUrl" />
        <link rel="alternate" hreflang="uz" :href="seoUrl" />
        <link rel="alternate" hreflang="ru" :href="seoUrlRu" />
        <link rel="alternate" hreflang="x-default" :href="seoUrl" />
        <link rel="alternate" type="application/rss+xml" title="OpenSales Blog RSS" href="https://opensales.uz/feed.xml" />
        <meta http-equiv="content-language" content="uz" />

        <meta name="msvalidate.01" content="A27269E0D74B765936CA7EAE8950440A" />

        <meta name="geo.region" content="UZ-TK" />
        <meta name="geo.placename" content="Tashkent" />
        <meta name="geo.position" content="41.2995;69.2401" />
        <meta name="ICBM" content="41.2995, 69.2401" />

        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />

        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="OpenSales" />
        <meta property="og:locale" content="uz_UZ" />
        <meta property="og:locale:alternate" content="ru_RU" />
        <meta property="og:url" :content="seoUrl" />
        <meta property="og:title" content="OpenSales — Distribyutorlar uchun Telegram bot savdo platformasi" />
        <meta
            property="og:description"
            content="Telegram bot va mobil ilova orqali buyurtma, veb panel orqali boshqaruv, real vaqt hisobotlar, qarzdorlik nazorati va yetkazib berish marshruti bir tizimda. O'zbekiston distribyutorlari uchun."
        />
        <meta property="og:image" :content="seoImage" />
        <meta property="og:image:alt" content="OpenSales logo" />

        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="OpenSales — Distribyutorlar uchun Telegram bot savdo platformasi" />
        <meta
            name="twitter:description"
            content="Telegram bot + mobil ilova + veb panel + hisobotlar — distribyutsiya jarayonini avtomatlashtiruvchi yagona platforma."
        />
        <meta name="twitter:image" :content="seoImage" />

        <component :is="'script'" type="application/ld+json" :inner-html="jsonLdString" />
    </Head>

    <div class="min-h-svh bg-background pt-16 text-foreground">
        <PublicHeader />

        <section class="relative overflow-hidden border-b border-border/60">
            <div
                class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,theme(colors.primary/10%),transparent_60%)]"
            />
            <div class="mx-auto w-full max-w-6xl px-4 py-20 md:px-6 md:py-28">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-muted-foreground"
                        >
                            <Sparkles class="size-3.5" />
                            {{ t('pageWelcome.hero.badge') }}
                        </span>
                        <h1
                            class="mt-6 text-4xl font-bold leading-tight tracking-tight md:text-5xl lg:text-6xl"
                        >
                            {{ t('pageWelcome.hero.h1Part1') }}
                            <span class="text-primary">{{ t('pageWelcome.hero.h1Highlight') }}</span>
                        </h1>
                        <p
                            class="mt-6 max-w-xl text-lg leading-relaxed text-muted-foreground"
                        >
                            {{ t('pageWelcome.hero.subtitle') }}
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <Link
                                href="/register"
                                class="inline-flex h-11 items-center justify-center rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90"
                            >
                                {{ t('pageWelcome.hero.ctaRegister') }}
                                <Send class="ml-2 size-4" />
                            </Link>
                            <a
                                href="#features"
                                class="inline-flex h-11 items-center justify-center rounded-md border border-border bg-background px-6 text-sm font-medium transition-colors hover:bg-accent"
                                @click.prevent="scrollToSection('features')"
                            >
                                {{ t('pageWelcome.hero.ctaFeatures') }}
                            </a>
                        </div>

                        <div
                            class="mt-10 grid grid-cols-3 gap-6 border-t border-border/60 pt-6 text-sm"
                        >
                            <div>
                                <div class="text-2xl font-bold">{{ t('pageWelcome.hero.stat1Value') }}</div>
                                <div class="text-muted-foreground">{{ t('pageWelcome.hero.stat1Label') }}</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold">{{ t('pageWelcome.hero.stat2Value') }}</div>
                                <div class="text-muted-foreground">{{ t('pageWelcome.hero.stat2Label') }}</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold">{{ t('pageWelcome.hero.stat3Value') }}</div>
                                <div class="text-muted-foreground">{{ t('pageWelcome.hero.stat3Label') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <div
                            class="rounded-2xl border border-border bg-card p-6 shadow-xl"
                        >
                            <div class="flex items-center gap-2 border-b border-border pb-4">
                                <div class="size-3 rounded-full bg-red-400" />
                                <div class="size-3 rounded-full bg-yellow-400" />
                                <div class="size-3 rounded-full bg-green-400" />
                                <span class="ml-3 text-xs text-muted-foreground">{{ t('pageWelcome.hero.mockUrl') }}</span>
                            </div>
                            <div class="space-y-3 pt-4">
                                <div class="flex items-center justify-between rounded-lg bg-accent/50 p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-9 items-center justify-center rounded-md bg-primary/10 text-primary">
                                            <ShoppingCart class="size-4" />
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium">{{ t('pageWelcome.hero.mockOrder1Title') }}</div>
                                            <div class="text-xs text-muted-foreground">{{ t('pageWelcome.hero.mockOrder1Sub') }}</div>
                                        </div>
                                    </div>
                                    <span class="text-sm font-semibold">450 000</span>
                                </div>
                                <div class="flex items-center justify-between rounded-lg bg-accent/50 p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-9 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-500">
                                            <CheckCircle2 class="size-4" />
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium">{{ t('pageWelcome.hero.mockOrder2Title') }}</div>
                                            <div class="text-xs text-muted-foreground">{{ t('pageWelcome.hero.mockOrder2Sub') }}</div>
                                        </div>
                                    </div>
                                    <span class="text-sm font-semibold text-emerald-600">820 000</span>
                                </div>
                                <div class="flex items-center justify-between rounded-lg bg-accent/50 p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-9 items-center justify-center rounded-md bg-amber-500/10 text-amber-600">
                                            <Clock class="size-4" />
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium">{{ t('pageWelcome.hero.mockOrder3Title') }}</div>
                                            <div class="text-xs text-muted-foreground">{{ t('pageWelcome.hero.mockOrder3Sub') }}</div>
                                        </div>
                                    </div>
                                    <span class="text-sm font-semibold">120 000</span>
                                </div>
                            </div>
                        </div>
                        <div
                            class="absolute -bottom-6 -right-6 hidden rounded-xl border border-border bg-background p-4 shadow-lg sm:block"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                    <Bot class="size-5" />
                                </div>
                                <div class="text-left">
                                    <div class="text-xs text-muted-foreground">{{ t('pageWelcome.hero.mockBotLabel') }}</div>
                                    <div class="text-sm font-semibold">{{ t('pageWelcome.hero.mockBotName') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="audiences" class="border-b border-border/60 bg-muted/30">
            <div class="mx-auto w-full max-w-6xl px-4 py-20 md:px-6">
                <div class="mb-12 text-center">
                    <h2 class="text-3xl font-bold tracking-tight md:text-4xl">
                        {{ t('pageWelcome.audiencesHeading') }}
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        {{ t('pageWelcome.audiencesSubtitle') }}
                    </p>
                </div>
                <div class="grid gap-6 md:grid-cols-3">
                    <div
                        v-for="item in audiences"
                        :key="item.title"
                        class="rounded-xl border border-border bg-background p-6 shadow-sm transition hover:shadow-md"
                    >
                        <div
                            class="flex size-11 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <component :is="item.icon" class="size-5" />
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">{{ item.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                            {{ item.text }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="border-b border-border/60">
            <div class="mx-auto w-full max-w-6xl px-4 py-20 md:px-6">
                <div class="mb-12 text-center">
                    <h2 class="text-3xl font-bold tracking-tight md:text-4xl">
                        {{ t('pageWelcome.featuresHeading') }}
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        {{ t('pageWelcome.featuresSubtitle') }}
                    </p>
                </div>
                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="item in features"
                        :key="item.title"
                        class="rounded-xl border border-border bg-card p-6"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <component :is="item.icon" class="size-5" />
                        </div>
                        <h3 class="mt-4 font-semibold">{{ item.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                            {{ item.text }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-b border-border/60 bg-muted/30">
            <div class="mx-auto w-full max-w-6xl px-4 py-20 md:px-6">
                <div class="mb-12 text-center">
                    <h2 class="text-3xl font-bold tracking-tight md:text-4xl">
                        {{ t('pageWelcome.advantagesHeading') }}
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        {{ t('pageWelcome.advantagesSubtitle') }}
                    </p>
                </div>
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="item in advantages"
                        :key="item.title"
                        class="rounded-xl border border-border bg-background p-6"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600"
                        >
                            <component :is="item.icon" class="size-5" />
                        </div>
                        <h3 class="mt-4 font-semibold">{{ item.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                            {{ item.text }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section id="reports" class="border-b border-border/60">
            <div class="mx-auto w-full max-w-6xl px-4 py-20 md:px-6">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <h2 class="text-3xl font-bold tracking-tight md:text-4xl">
                            {{ t('pageWelcome.reportsHeading') }}
                        </h2>
                        <p class="mt-4 leading-relaxed text-muted-foreground">
                            {{ t('pageWelcome.reportsLead') }}
                        </p>
                        <ul class="mt-6 space-y-3">
                            <li
                                v-for="item in reports"
                                :key="item"
                                class="flex items-start gap-3 text-sm"
                            >
                                <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-emerald-500" />
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </div>
                    <div class="rounded-2xl border border-border bg-card p-6 shadow-lg">
                        <div class="flex items-center justify-between border-b border-border pb-4">
                            <div class="flex items-center gap-2">
                                <FileSpreadsheet class="size-4 text-muted-foreground" />
                                <span class="text-sm font-medium">{{ t('pageWelcome.reportsCard.title') }}</span>
                            </div>
                            <span class="text-xs text-muted-foreground">{{ t('pageWelcome.reportsCard.year') }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 py-4">
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('pageWelcome.reportsCard.incomeLabel') }}</div>
                                <div class="mt-1 text-2xl font-bold">{{ t('pageWelcome.reportsCard.incomeValue') }}</div>
                                <div class="text-xs text-emerald-600">{{ t('pageWelcome.reportsCard.incomeDelta') }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('pageWelcome.reportsCard.ordersLabel') }}</div>
                                <div class="mt-1 text-2xl font-bold">{{ t('pageWelcome.reportsCard.ordersValue') }}</div>
                                <div class="text-xs text-emerald-600">{{ t('pageWelcome.reportsCard.ordersDelta') }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('pageWelcome.reportsCard.shopsLabel') }}</div>
                                <div class="mt-1 text-2xl font-bold">{{ t('pageWelcome.reportsCard.shopsValue') }}</div>
                                <div class="text-xs text-emerald-600">{{ t('pageWelcome.reportsCard.shopsDelta') }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('pageWelcome.reportsCard.debtLabel') }}</div>
                                <div class="mt-1 text-2xl font-bold">{{ t('pageWelcome.reportsCard.debtValue') }}</div>
                                <div class="text-xs text-amber-600">{{ t('pageWelcome.reportsCard.debtDelta') }}</div>
                            </div>
                        </div>
                        <div class="border-t border-border pt-4">
                            <div class="mb-2 flex items-center justify-between text-xs text-muted-foreground">
                                <span>{{ t('pageWelcome.reportsCard.topProductLabel') }}</span>
                                <span>{{ t('pageWelcome.reportsCard.turnoverLabel') }}</span>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-sm">
                                    <span>Coca-Cola 1.5L</span>
                                    <span class="font-medium">12.4 mln</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span>Nestle Pure Life 1L</span>
                                    <span class="font-medium">9.1 mln</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span>Pepsi 0.5L</span>
                                    <span class="font-medium">7.8 mln</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-b border-border/60 bg-muted/30">
            <div class="mx-auto w-full max-w-6xl px-4 py-20 md:px-6">
                <div class="mb-12 text-center">
                    <h2 class="text-3xl font-bold tracking-tight md:text-4xl">
                        {{ t('pageWelcome.flowHeading') }}
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        {{ t('pageWelcome.flowSubtitle') }}
                    </p>
                </div>
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-5">
                    <div
                        v-for="item in flow"
                        :key="item.step"
                        class="rounded-xl border border-border bg-background p-6"
                    >
                        <div class="text-3xl font-bold text-primary/40">
                            {{ item.step }}
                        </div>
                        <h3 class="mt-2 font-semibold">{{ item.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                            {{ item.text }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section
            id="pricing"
            class="relative overflow-hidden border-b border-border/60 bg-muted/20"
        >
            <div
                class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,theme(colors.primary/8%),transparent_55%)]"
            />

            <div class="mx-auto w-full max-w-6xl px-4 py-20 md:px-6 md:py-24">
                <div class="mx-auto mb-14 max-w-3xl text-center">
                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-muted-foreground"
                    >
                        <Sparkles class="size-3.5 text-primary" />
                        {{ t('pageWelcome.pricingBadge') }}
                    </span>
                    <h2 class="mt-5 text-3xl font-bold tracking-tight md:text-4xl lg:text-5xl">
                        {{ t('pageWelcome.pricingHeadingPart1') }}
                        <span class="text-primary">{{ t('pageWelcome.pricingHeadingHighlight') }}</span>
                        {{ t('pageWelcome.pricingHeadingPart2') }}
                    </h2>
                    <p class="mt-5 text-base leading-relaxed text-muted-foreground md:text-lg">
                        {{ t('pageWelcome.pricingSubtitle') }}
                    </p>
                </div>

                <div class="grid items-stretch gap-6 md:grid-cols-3">
                    <div
                        v-for="plan in pricingPlans"
                        :key="plan.name"
                        :class="[
                            'group relative flex flex-col overflow-hidden rounded-2xl border bg-background p-7 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl',
                            plan.highlighted
                                ? 'border-primary/40 shadow-xl ring-2 ring-primary/20 lg:scale-[1.03]'
                                : 'border-border/60 shadow-sm',
                        ]"
                    >
                        <div
                            :class="[
                                'pointer-events-none absolute inset-x-0 top-0 -z-0 h-40 bg-gradient-to-b opacity-70',
                                accentClasses[plan.accent].gradient,
                            ]"
                        />

                        <div
                            v-if="plan.highlighted"
                            class="absolute right-4 top-4 inline-flex items-center gap-1 rounded-full bg-primary px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-primary-foreground shadow-sm"
                        >
                            <Sparkles class="size-3" />
                            {{ t('pageWelcome.pricingRecommended') }}
                        </div>

                        <div class="relative z-10 flex flex-col">
                            <div
                                :class="[
                                    'flex size-12 items-center justify-center rounded-xl shadow-md transition-transform group-hover:scale-110',
                                    accentClasses[plan.accent].iconBg,
                                    accentClasses[plan.accent].iconText,
                                ]"
                            >
                                <component :is="plan.icon" class="size-6" />
                            </div>

                            <div class="mt-5">
                                <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    {{ plan.tagline }}
                                </p>
                                <h3 class="mt-1 text-2xl font-bold leading-tight">
                                    {{ plan.name }}
                                </h3>
                            </div>

                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                {{ plan.description }}
                            </p>

                            <div
                                class="mt-6 rounded-xl border border-border/70 bg-card/80 p-4 backdrop-blur-sm"
                            >
                                <div class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">
                                    {{ t('pageWelcome.pricingFormulaTitle') }}
                                </div>
                                <div class="mt-2 text-lg font-bold tracking-tight">
                                    {{ plan.formula }}
                                </div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    {{ plan.formulaCaption }}
                                </div>
                                <div class="mt-3 rounded-lg bg-muted/60 px-3 py-2 text-xs text-foreground/80">
                                    {{ plan.example }}
                                </div>
                            </div>

                            <ul class="mt-6 space-y-2.5">
                                <li
                                    v-for="benefit in plan.benefits"
                                    :key="benefit"
                                    class="flex items-start gap-2 text-sm"
                                >
                                    <CheckCircle2 class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                    <span>{{ benefit }}</span>
                                </li>
                            </ul>
                        </div>

                        <a
                            href="#contact"
                            :class="[
                                'relative z-10 mt-7 inline-flex h-11 items-center justify-center rounded-lg px-5 text-sm font-semibold transition-all',
                                plan.highlighted
                                    ? 'bg-primary text-primary-foreground shadow-md hover:bg-primary/90 hover:shadow-lg'
                                    : 'border border-border bg-background text-foreground hover:border-primary/40 hover:bg-primary/5 hover:text-primary',
                            ]"
                            @click.prevent="scrollToSection('contact')"
                        >
                            {{ t('pageWelcome.pricingPlanCta') }}
                            <Send class="ml-2 size-4" />
                        </a>
                    </div>
                </div>

                <div
                    class="mt-12 grid items-center gap-6 rounded-2xl border border-border/60 bg-background p-6 shadow-sm md:grid-cols-[auto_1fr_auto] md:p-8"
                >
                    <div
                        class="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Sparkles class="size-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">{{ t('pageWelcome.pricingHelper.title') }}</h3>
                        <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                            {{ t('pageWelcome.pricingHelper.text') }}
                        </p>
                    </div>
                    <a
                        href="#contact"
                        class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-md transition-colors hover:bg-primary/90 md:w-auto"
                        @click.prevent="scrollToSection('contact')"
                    >
                        {{ t('pageWelcome.pricingHelper.cta') }}
                        <Send class="ml-2 size-4" />
                    </a>
                </div>
            </div>
        </section>

        <section id="compare" class="border-b border-border/60">
            <div class="mx-auto w-full max-w-6xl px-4 py-20 md:px-6 md:py-24">
                <div class="mx-auto mb-12 max-w-3xl text-center">
                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-muted-foreground"
                    >
                        <Scale class="size-3.5 text-primary" />
                        {{ t('pageWelcome.compare.badge') }}
                    </span>
                    <h2 class="mt-5 text-3xl font-bold tracking-tight md:text-4xl">
                        {{ t('pageWelcome.compare.heading') }}
                    </h2>
                    <p class="mt-4 text-base text-muted-foreground md:text-lg">
                        {{ t('pageWelcome.compare.subtitle') }}
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <Link
                        :href="lp('/taqqoslash/opensales-vs-1c')"
                        class="group flex flex-col rounded-2xl border border-border/60 bg-card p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-lg"
                    >
                        <div class="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary transition-transform group-hover:scale-110">
                            <Scale class="size-6" />
                        </div>
                        <h3 class="mt-5 text-xl font-bold">
                            {{ t('pageWelcome.compare.oneC.title') }}
                        </h3>
                        <p class="mt-3 flex-1 text-sm leading-relaxed text-muted-foreground">
                            {{ t('pageWelcome.compare.oneC.text') }}
                        </p>
                        <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-primary">
                            {{ t('pageWelcome.compare.oneC.cta') }}
                            <ArrowRight class="size-4 transition-transform group-hover:translate-x-1" />
                        </span>
                    </Link>

                    <Link
                        :href="lp('/taqqoslash/opensales-vs-sales-doctor')"
                        class="group flex flex-col rounded-2xl border border-border/60 bg-card p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-lg"
                    >
                        <div class="flex size-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 transition-transform group-hover:scale-110">
                            <Scale class="size-6" />
                        </div>
                        <h3 class="mt-5 text-xl font-bold">
                            {{ t('pageWelcome.compare.salesDoctor.title') }}
                        </h3>
                        <p class="mt-3 flex-1 text-sm leading-relaxed text-muted-foreground">
                            {{ t('pageWelcome.compare.salesDoctor.text') }}
                        </p>
                        <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-primary">
                            {{ t('pageWelcome.compare.salesDoctor.cta') }}
                            <ArrowRight class="size-4 transition-transform group-hover:translate-x-1" />
                        </span>
                    </Link>

                    <Link
                        :href="lp('/narxlar/kalkulyator')"
                        class="group flex flex-col rounded-2xl border border-primary/40 bg-primary/5 p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                    >
                        <div class="flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-md transition-transform group-hover:scale-110">
                            <Calculator class="size-6" />
                        </div>
                        <h3 class="mt-5 text-xl font-bold">
                            {{ t('pageWelcome.compare.calculator.title') }}
                        </h3>
                        <p class="mt-3 flex-1 text-sm leading-relaxed text-muted-foreground">
                            {{ t('pageWelcome.compare.calculator.text') }}
                        </p>
                        <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-primary">
                            {{ t('pageWelcome.compare.calculator.cta') }}
                            <ArrowRight class="size-4 transition-transform group-hover:translate-x-1" />
                        </span>
                    </Link>
                </div>
            </div>
        </section>

        <section id="faq" class="border-b border-border/60">
            <div class="mx-auto w-full max-w-4xl px-4 py-20 md:px-6">
                <div class="mb-12 text-center">
                    <h2 class="text-3xl font-bold tracking-tight md:text-4xl">
                        {{ t('pageWelcome.faqHeading') }}
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        {{ t('pageWelcome.faqSubtitle') }}
                    </p>
                </div>
                <div class="space-y-4">
                    <details
                        v-for="item in faqsLocalized"
                        :key="item.q"
                        class="group rounded-xl border border-border bg-card p-5 open:shadow-md"
                    >
                        <summary
                            class="flex cursor-pointer items-center justify-between gap-4 text-base font-semibold marker:hidden"
                        >
                            <span>{{ item.q }}</span>
                            <span
                                class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary transition-transform group-open:rotate-45"
                            >+</span>
                        </summary>
                        <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                            {{ item.a }}
                        </p>
                    </details>
                </div>
            </div>
        </section>

        <section id="contact" class="border-b border-border/60">
            <div class="mx-auto w-full max-w-4xl px-4 py-20 md:px-6">
                <div class="rounded-2xl border border-border bg-card p-8 shadow-lg md:p-12">
                    <div class="grid gap-10 lg:grid-cols-2">
                        <div>
                            <h2 class="text-3xl font-bold tracking-tight md:text-4xl">
                                {{ t('pageWelcome.contactHeading') }}
                            </h2>
                            <p class="mt-4 leading-relaxed text-muted-foreground">
                                {{ t('pageWelcome.contactDescription') }}
                            </p>

                            <div class="mt-8 space-y-4">
                                <div class="flex items-start gap-3">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <PhoneCall class="size-4" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium">{{ t('pageWelcome.contactQuick.title') }}</div>
                                        <div class="text-sm text-muted-foreground">
                                            {{ t('pageWelcome.contactQuick.text') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <MessageSquare class="size-4" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium">{{ t('pageWelcome.contactDemo.title') }}</div>
                                        <div class="text-sm text-muted-foreground">
                                            {{ t('pageWelcome.contactDemo.text') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <Rocket class="size-4" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium">{{ t('pageWelcome.contactLaunch.title') }}</div>
                                        <div class="text-sm text-muted-foreground">
                                            {{ t('pageWelcome.contactLaunch.text') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col justify-center rounded-xl border border-primary/20 bg-primary/5 p-8 text-center">
                            <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <Rocket class="size-7" />
                            </div>
                            <h3 class="mt-4 text-xl font-bold tracking-tight">
                                {{ t('pageWelcome.contactRegister.title') }}
                            </h3>
                            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                                {{ t('pageWelcome.contactRegister.text') }}
                            </p>

                            <Link
                                href="/register"
                                class="mt-6 inline-flex h-11 items-center justify-center rounded-md bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90"
                            >
                                {{ t('pageWelcome.contactRegister.cta') }}
                                <Send class="ml-2 size-4" />
                            </Link>

                            <p class="mt-4 text-sm text-muted-foreground">
                                {{ t('pageWelcome.contactRegister.haveAccount') }}
                                <Link href="/login" class="font-medium text-primary hover:underline">
                                    {{ t('pageWelcome.contactRegister.loginLink') }}
                                </Link>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <footer class="bg-background py-10">
            <div class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-4 px-4 md:flex-row md:px-6">
                <div class="flex items-center gap-2">
                    <div class="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <AppLogoIcon class="size-4" />
                    </div>
                    <span class="text-sm font-semibold">{{ brandName }}</span>
                    <span class="text-sm text-muted-foreground">{{ t('pageWelcome.footerCopyright') }}</span>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-muted-foreground">
                    <Link :href="login()" class="transition-colors hover:text-foreground">{{ t('pageWelcome.footerLogin') }}</Link>
                    <a
                        href="#features"
                        class="transition-colors hover:text-foreground"
                        @click.prevent="scrollToSection('features')"
                    >
                        {{ t('pageWelcome.footerFeatures') }}
                    </a>
                    <a
                        href="#compare"
                        class="transition-colors hover:text-foreground"
                        @click.prevent="scrollToSection('compare')"
                    >
                        {{ t('pageWelcome.footerCompare') }}
                    </a>
                    <Link :href="lp('/narxlar/kalkulyator')" class="transition-colors hover:text-foreground">
                        {{ t('pageWelcome.footerCalculator') }}
                    </Link>
                    <a
                        href="#contact"
                        class="transition-colors hover:text-foreground"
                        @click.prevent="scrollToSection('contact')"
                    >
                        {{ t('pageWelcome.footerContact') }}
                    </a>
                    <Link :href="lp('/blog')" class="transition-colors hover:text-foreground">Blog</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
