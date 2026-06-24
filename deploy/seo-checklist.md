# SEO + GEO — manual checklist

Ushbu hujjat kod bilan bajarib bo'lmaydigan tashqi ishlarni ro'yxatlaydi.

---

## 1. Brand mention va backlink campaign (eng katta GEO ta'siri)

LLM (ChatGPT, Claude, Perplexity, Gemini) training datasi tarkibida brand mention ko'p
bo'lgan sari AI assistant savol javobida loyihangizni tavsiya qiladi.

### A. Wikipedia (eng yuqori avtoritet)

**Maqsad:** OpenSales haqida ru.wikipedia.org yoki uz.wikipedia.org sahifasi.

**Talab:** "Notability" — kompaniya jiddiy bo'lishi kerak:
- Mustaqil manbalardan kamida 3 ta yangilik (Daryo, Spot.uz, Kun.uz, IT.uz)
- Aniq sonlar (mijozlar, ishlatuvchi distribyutorlar)
- Asoschilar haqida ma'lumot

**Qadamlar:**
1. Avval 3-5 ta press release qiling (quyida ko'rsatilgan saytlar).
2. Wikipedia draftspace'da maqola yarating: ru.wikipedia.org/wiki/Project:Articles_for_creation
3. Manbalar bilan qo'llab-quvvatlang.

### B. Habr (rus tilidagi tech jamoasi)

**Maqsad:** habr.com saytida tahliliy maqola.

**Mavzu g'oyalari:**
- "Как мы построили multi-tenant Telegram bot для дистрибьюторов в Узбекистане"
- "Сравнение распределения товаров через 1C и Telegram bot — реальные цифры"
- "Inertia.js v3 + Nutgram + Laravel — стек для региональных SaaS"

**Format:** texnik chuqurlik, kod misollari, raqamlar. Marketingsiz, hikoya.

### C. Reddit

**Subreddit:** `r/Uzbekistan`, `r/laravel`, `r/SaaS`, `r/Entrepreneur`

**Yondashuv:** "Show & Tell" formati, sotuv emas, qiziqarli case study.

### D. IT.uz, Spot.uz, Daryo.uz, Kun.uz press release

**Tipi:** Yangilik/intervyu/case study.

**Pitch namuna:**
> OpenSales — O'zbekistondagi distribyutorlar uchun Telegram bot orqali zakas qabul
> qiluvchi mahalliy SaaS platforma. 5 daqiqada ishga tushadi, mahalliy bozorga
> moslashtirilgan. {Kompaniya soni} ta distribyutor foydalanmoqda.

### E. LinkedIn company page

**Profil:** linkedin.com/company/opensales-uz

**Mazmun:** haftada 1-2 post (mahsulot yangiliklar, case study, ishchi top'la).

### F. GitHub README + opensource komponentlar

**Maqsad:** dev community ichida brand mention.

**Variantlar:**
- `opensales/php-indexnow-client` — IndexNow PHP klient
- `opensales/laravel-multi-bot-routing` — Nutgram multi-bot routing helper
- README'larda OpenSales havolasi

### G. Telegram kanal + YouTube

| Kanal | URL | Mazmun |
|-------|-----|--------|
| Telegram | t.me/opensales_uz | Yangiliklar, demo, screenshot |
| YouTube | youtube.com/@opensales-uz | Demo video, ko'rsatma, case study |

---

## 2. AI assistant verification (yangi)

| Servis | Verification | Holat |
|--------|--------------|-------|
| OpenAI GPT crawler | Allowed in robots.txt | ✓ |
| ChatGPT browse | Allowed | ✓ |
| Claude/Anthropic | Allowed | ✓ |
| Perplexity | Allowed | ✓ |
| Yandex Neural | Allowed | ✓ |
| Google Bard | google-extended Allowed | ✓ |

Robots.txt allaqachon AI bot allowlist bilan. Yangi yo'naltirish kerak emas.

---

## 3. Search Console konfiguratsiya

| Servis | URL | Holat | Submit |
|--------|-----|-------|--------|
| Google Search Console | https://search.google.com/search-console | DNS verified | sitemap.xml |
| Yandex Webmaster | https://webmaster.yandex.com | File verified | sitemap.xml |
| Bing Webmaster | https://www.bing.com/webmasters | Meta verified | sitemap.xml |
| IndexNow | api.indexnow.org | Key file verified | Per-URL push |

---

## 4. Google Business Profile

Sayt mahalliy hudud uchun (Toshkent) — Google Maps'da ko'rinishi muhim.

1. https://business.google.com → Add business
2. Toshkent manzil, telefon, ish vaqti
3. Kategoriya: "Software company"
4. Foto + logotip
5. Verification (post card yoki telefon)

---

## 5. Performance va analytics

| Vazifa | Servis | URL |
|--------|--------|-----|
| Real user analytics | Plausible self-hosted yoki Yandex Metrica | plausible.io / metrica.yandex.com |
| Heatmap | Microsoft Clarity (bepul) | clarity.microsoft.com |
| Speed monitoring | PageSpeed Insights | pagespeed.web.dev |
| Schema validator | Schema.org Validator | validator.schema.org |
| Rich results | Google Rich Results Test | search.google.com/test/rich-results |

---

## 6. HSTS preload (oxirgi qadam)

HSTS deploy bo'lgandan 2-4 hafta keyin (har narsa stabil bo'lganda):

1. Nginx config'da HSTS header'iga `preload` qo'shing:
   ```
   add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
   ```
2. Tasdiqlang: https://hstspreload.org/?domain=opensales.uz
3. Submit qiling — browser'lar preload listga qo'shadi (Chrome, Firefox, Safari).

**Diqqat:** preload listga qo'shilgach, HTTPSdan HTTP'ga qaytib bo'lmaydi.

---

## 7. Doimiy ishlar (har hafta)

- [ ] Yangi blog maqola (1 ta/hafta — long-tail SEO uchun)
- [ ] IndexNow orqali yangi URL submit
- [ ] Google Search Console — Coverage Report tekshirish
- [ ] Plausible/Metrica — eng yuqori sahifalar va kalit so'zlar
- [ ] Yandex Metrica — Click Map analysis

---

## 8. Doimiy ishlar (har oy)

- [ ] PageSpeed Insights — Core Web Vitals dinamikasi
- [ ] Schema.org Validator — barcha JSON-LD sahifalarda
- [ ] Sitemap qo'lda submit (yangi sahifalar bo'lsa)
- [ ] Brand mention monitoring (Google Alerts: "opensales")
- [ ] Backlink audit (ahrefs.com / search.google.com `link:opensales.uz`)
