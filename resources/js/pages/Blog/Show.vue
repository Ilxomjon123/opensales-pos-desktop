<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicHeader from '@/components/PublicHeader.vue';
import { useLocalePath } from '@/composables/useLocalePath';

interface BlogPost {
    slug: string;
    title: string;
    title_ru: string | null;
    excerpt: string;
    excerpt_ru: string | null;
    body: string;
    body_ru: string | null;
    cover_image: string | null;
    meta_title: string | null;
    meta_description: string | null;
    author_name: string;
    views: number;
    read_minutes: number;
    published_at: string | null;
    updated_at: string;
    url: string;
}

const props = defineProps<{
    post: BlogPost;
}>();

defineOptions({ layout: null });

const { locale } = useI18n();
const { lp } = useLocalePath();

const titleLocalized = computed(() =>
    locale.value === 'ru' ? props.post.title_ru ?? props.post.title : props.post.title,
);

const excerptLocalized = computed(() =>
    locale.value === 'ru' ? props.post.excerpt_ru ?? props.post.excerpt : props.post.excerpt,
);

const bodyLocalized = computed(() =>
    locale.value === 'ru' ? props.post.body_ru ?? props.post.body : props.post.body,
);

const bodyParagraphs = computed(() => bodyLocalized.value.split(/\n\s*\n/).filter(Boolean));

const formatter = computed(() =>
    new Intl.DateTimeFormat(locale.value === 'ru' ? 'ru-RU' : 'uz-UZ', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }),
);

const publishedLabel = computed(() =>
    props.post.published_at ? formatter.value.format(new Date(props.post.published_at)) : '',
);

const canonicalUrl = computed(() => `https://opensales.uz/blog/${props.post.slug}`);
const ogImage = computed(() => props.post.cover_image ?? 'https://opensales.uz/og-image.png');

const jsonLd = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: titleLocalized.value,
    description: props.post.meta_description ?? excerptLocalized.value,
    image: ogImage.value,
    datePublished: props.post.published_at,
    dateModified: props.post.updated_at,
    inLanguage: locale.value === 'ru' ? 'ru' : 'uz',
    mainEntityOfPage: {
        '@type': 'WebPage',
        '@id': canonicalUrl.value,
    },
    author: {
        '@type': 'Organization',
        name: props.post.author_name,
        url: 'https://opensales.uz',
    },
    publisher: {
        '@type': 'Organization',
        name: 'OpenSales',
        logo: {
            '@type': 'ImageObject',
            url: 'https://opensales.uz/apple-touch-icon.png',
        },
    },
}));

const breadcrumbLd = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
        { '@type': 'ListItem', position: 1, name: 'Bosh sahifa', item: 'https://opensales.uz/' },
        { '@type': 'ListItem', position: 2, name: 'Blog', item: 'https://opensales.uz/blog' },
        { '@type': 'ListItem', position: 3, name: titleLocalized.value, item: canonicalUrl.value },
    ],
}));

const jsonLdString = computed(() => JSON.stringify(jsonLd.value));
const breadcrumbLdString = computed(() => JSON.stringify(breadcrumbLd.value));
</script>

<template>
    <Head :title="post.meta_title ?? titleLocalized">
        <meta name="description" :content="post.meta_description ?? excerptLocalized" />
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1" />
        <link rel="canonical" :href="canonicalUrl" />
        <meta property="og:type" content="article" />
        <meta property="og:title" :content="titleLocalized" />
        <meta property="og:description" :content="post.meta_description ?? excerptLocalized" />
        <meta property="og:url" :content="canonicalUrl" />
        <meta property="og:image" :content="ogImage" />
        <meta property="article:published_time" :content="post.published_at ?? ''" />
        <meta property="article:modified_time" :content="post.updated_at" />
        <meta property="article:author" :content="post.author_name" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="titleLocalized" />
        <meta name="twitter:description" :content="post.meta_description ?? excerptLocalized" />
        <meta name="twitter:image" :content="ogImage" />

        <component :is="'script'" type="application/ld+json" :inner-html="jsonLdString" />
        <component :is="'script'" type="application/ld+json" :inner-html="breadcrumbLdString" />
    </Head>

    <div class="min-h-svh bg-background pt-16 text-foreground">
        <PublicHeader />

        <article class="mx-auto w-full max-w-3xl px-4 py-12 md:px-6 md:py-16">
            <nav aria-label="Breadcrumb" class="mb-6 text-sm text-muted-foreground">
                <ol class="flex flex-wrap items-center gap-2">
                    <li><Link href="/" class="hover:text-foreground">Bosh sahifa</Link></li>
                    <li aria-hidden="true">/</li>
                    <li><Link :href="lp('/blog')" class="hover:text-foreground">Blog</Link></li>
                    <li aria-hidden="true">/</li>
                    <li class="text-foreground">{{ titleLocalized }}</li>
                </ol>
            </nav>

            <header class="mb-8">
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">{{ titleLocalized }}</h1>
                <div class="mt-4 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                    <span>{{ post.author_name }}</span>
                    <span aria-hidden="true">•</span>
                    <time :datetime="post.published_at ?? ''">{{ publishedLabel }}</time>
                    <span aria-hidden="true">•</span>
                    <span>{{ post.read_minutes }} daqiqa o'qish</span>
                </div>
            </header>

            <img
                v-if="post.cover_image"
                :src="post.cover_image"
                :alt="titleLocalized"
                width="1200"
                height="630"
                class="mb-8 aspect-[16/9] w-full rounded-xl object-cover"
            />

            <div class="prose prose-neutral max-w-none dark:prose-invert">
                <p v-for="(paragraph, idx) in bodyParagraphs" :key="idx" class="leading-relaxed">
                    {{ paragraph }}
                </p>
            </div>

            <footer class="mt-12 flex flex-wrap items-center justify-between gap-4 border-t border-border/60 pt-6">
                <Link :href="lp('/blog')" class="text-sm text-primary hover:underline">← Barcha maqolalar</Link>
                <a
                    :href="`${lp('/')}#contact`"
                    class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    Demo so'rash
                </a>
            </footer>
        </article>
    </div>
</template>
