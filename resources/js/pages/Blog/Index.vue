<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicHeader from '@/components/PublicHeader.vue';

interface BlogPostListItem {
    slug: string;
    title: string;
    title_ru: string | null;
    excerpt: string;
    excerpt_ru: string | null;
    cover_image: string | null;
    author_name: string;
    read_minutes: number;
    published_at: string | null;
    url: string;
}

interface PaginatedPosts {
    data: BlogPostListItem[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

defineProps<{
    posts: PaginatedPosts;
}>();

defineOptions({ layout: null });

const { locale } = useI18n();

function localized(item: BlogPostListItem, field: 'title' | 'excerpt'): string {
    if (locale.value === 'ru') {
        return (item[`${field}_ru` as 'title_ru' | 'excerpt_ru'] ?? item[field]) as string;
    }

    return item[field];
}

const formatter = computed(() =>
    new Intl.DateTimeFormat(locale.value === 'ru' ? 'ru-RU' : 'uz-UZ', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }),
);

function formatDate(iso: string | null): string {
    return iso ? formatter.value.format(new Date(iso)) : '';
}

const seoUrl = 'https://opensales.uz/blog';
const seoImage = 'https://opensales.uz/og-image.png';
</script>

<template>
    <Head title="OpenSales Blog — Distribyutsiya, Telegram bot, savdo avtomatlashtirish">
        <meta
            name="description"
            content="Distribyutsiya, savdo avtomatlashtirish, Telegram bot orqali buyurtma qabul qilish, qarzdorlik nazorati va FMCG sektoridagi yangi yondashuvlar haqida amaliy maqolalar."
        />
        <meta name="robots" content="index, follow, max-image-preview:large" />
        <link rel="canonical" :href="seoUrl" />
        <link rel="alternate" type="application/rss+xml" title="OpenSales Blog RSS" href="https://opensales.uz/feed.xml" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="OpenSales Blog" />
        <meta property="og:url" :content="seoUrl" />
        <meta property="og:image" :content="seoImage" />
    </Head>

    <div class="min-h-svh bg-background pt-16 text-foreground">
        <PublicHeader />

        <main class="mx-auto w-full max-w-6xl px-4 py-12 md:px-6 md:py-16">
            <div class="mb-10 max-w-2xl">
                <h1 class="text-4xl font-bold tracking-tight md:text-5xl">OpenSales Blog</h1>
                <p class="mt-3 text-base text-muted-foreground md:text-lg">
                    Distribyutsiya, Telegram bot orqali savdo, qarzdorlik nazorati va FMCG sektoridagi amaliy yo'naltirilgan maqolalar.
                </p>
            </div>

            <div v-if="posts.data.length === 0" class="rounded-xl border border-dashed py-16 text-center text-muted-foreground">
                Hozircha maqolalar yo'q.
            </div>

            <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="post in posts.data"
                    :key="post.slug"
                    class="group flex flex-col overflow-hidden rounded-xl border border-border/60 bg-card transition hover:border-primary/50 hover:shadow-lg"
                >
                    <Link :href="post.url" class="block">
                        <div v-if="post.cover_image" class="aspect-[16/9] overflow-hidden bg-muted">
                            <img
                                :src="post.cover_image"
                                :alt="localized(post, 'title')"
                                loading="lazy"
                                width="800"
                                height="450"
                                class="size-full object-cover transition group-hover:scale-105"
                            />
                        </div>
                        <div class="flex flex-1 flex-col gap-3 p-5">
                            <div class="flex items-center gap-3 text-xs text-muted-foreground">
                                <time :datetime="post.published_at ?? ''">{{ formatDate(post.published_at) }}</time>
                                <span aria-hidden="true">•</span>
                                <span>{{ post.read_minutes }} daqiqa</span>
                            </div>
                            <h2 class="text-lg font-semibold leading-snug group-hover:text-primary">
                                {{ localized(post, 'title') }}
                            </h2>
                            <p class="line-clamp-3 text-sm text-muted-foreground">
                                {{ localized(post, 'excerpt') }}
                            </p>
                        </div>
                    </Link>
                </article>
            </div>

            <nav v-if="posts.last_page > 1" class="mt-10 flex justify-center gap-2">
                <Link
                    v-if="posts.prev_page_url"
                    :href="posts.prev_page_url"
                    rel="prev"
                    class="rounded-md border px-4 py-2 text-sm hover:bg-muted"
                >
                    ← Oldingi
                </Link>
                <Link
                    v-if="posts.next_page_url"
                    :href="posts.next_page_url"
                    rel="next"
                    class="rounded-md border px-4 py-2 text-sm hover:bg-muted"
                >
                    Keyingi →
                </Link>
            </nav>
        </main>
    </div>
</template>
