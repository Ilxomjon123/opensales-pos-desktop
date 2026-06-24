<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;

final class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->posts() as $i => $post) {
            BlogPost::query()->updateOrCreate(
                ['slug' => $post['slug']],
                [
                    ...$post,
                    'published_at' => now()->subDays(60 - $i * 10),
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function posts(): array
    {
        return [
            [
                'slug' => 'distribyutor-uchun-telegram-bot-afzalliklari',
                'title' => 'Distribyutor uchun Telegram bot — afzalliklari va kamchiliklari',
                'excerpt' => "FMCG distribyutorlari nima uchun savdo agenti o'rniga Telegram bot tanlamoqda? Real afzalliklari, kamchiliklari va qanday hollarda bot foyda keltirmasligi haqida amaliy tahlil.",
                'meta_title' => 'Distribyutor uchun Telegram bot — afzalliklari va kamchiliklari',
                'meta_description' => 'Telegram bot orqali distribyutsiyaning haqiqiy afzalliklari, kamchiliklari, qachon bot mos kelmasligi haqida 2026 yildagi amaliy tahlil.',
                'read_minutes' => 7,
                'body' => $this->body([
                    "O'zbekistondagi FMCG distribyutorlari oxirgi yillarda savdo agentlarini Telegram bot bilan to'ldirishni jiddiy ko'rib chiqmoqda. Bu trend tasodif emas: Telegram MAU O'zbekistonda 25 mlndan ortgan, do'kon egalari kun bo'yi messengerda turibdi.",
                    "Birinchi afzallik — 24/7 qabul. Savdo agent kelmagan kuni ham do'kondor savatga mahsulot qo'sha oladi, kechqurun bemalol zakas berib qo'yadi. Tizim hech qanday qo'shimcha ilova o'rnatishni talab qilmaydi, do'kondor o'rganib bo'lgan interfeysda ishlaydi.",
                    "Ikkinchi afzallik — narxlarda izchillik. Bot katalogida bitta haqiqat manbai bor, savdo agentdan-agentga farq qiluvchi narx aytishlar yo'qoladi. Aksiya yangilanganda butun tarmoqqa bir vaqtda yetkaziladi.",
                    "Uchinchi afzallik — moliyaviy nazorat. Har bir zakas avtomatik DB ga tushadi, qarzdorlik real vaqt rejimida ko'rinadi. 0-30, 30-60, 60+ kunlik eskirish raqamlari endi Excel emas, hisobotda chiqadi.",
                    "Kamchiliklar ham bor. Birinchi — relationship. Savdo agent do'kondor bilan munosabat quradi: yangi mahsulotni qo'lda ko'rsatadi, e'tibor jalb qiladi, ko'pincha shaxsiy ishonch asosida sotadi. Bot bu murakkab ishni qila olmaydi.",
                    "Ikkinchi kamchilik — texnik savodxonlik. 50-60 yoshli do'kondor uchun Telegram tushunarli, lekin yangi mahsulot bo'limini ochish, savatdan element olib tashlash kabi harakatlar avvaliga qiyin. Bu yerda dastlabki o'qitish kerak bo'ladi.",
                    "Xulosa — bot savdo agentning to'liq o'rnini bosolmaydi. Lekin agentning kunlik 40 ta do'konga qatnashi o'rnida tizim 1 000 do'konni qoplay oladi. To'g'ri yondashuv: bot rutin operatsiyalarni oladi, agent yangi mijozlar va relationship'ga e'tibor qaratadi.",
                    "OpenSales shu modelni amalga oshiradi. Har distribyutor uchun shaxsiy bot, do'konchi tomonda hech qanday ilova talab qilinmaydi, distribyutor paneli orqali butun jarayonni nazorat qiladi.",
                ]),
            ],
            [
                'slug' => 'savdo-agenti-vs-telegram-bot-qaysi-samaraliroq',
                'title' => 'Savdo agenti vs Telegram bot — qaysi biri samaraliroq?',
                'excerpt' => "Bir savdo agenti kunlik 40 do'konga ulguradi, Telegram bot esa 24/7 ishlaydi. Lekin raqamlar butun manzarani ko'rsatmaydi — har bir modelning kuchli va zaif tomonlari aniq.",
                'meta_title' => 'Savdo agenti vs Telegram bot: 2026 yilgi taqqoslash',
                'meta_description' => 'FMCG distribyutsiyada savdo agent vs bot taqqoslash: xarajat, qamrov, sifat, qaytar. Real raqamlar va misol holatlar.',
                'read_minutes' => 8,
                'body' => $this->body([
                    "Distribyutsiya bo'yicha har bir tashkilot rahbari oxirida bitta savolga keladi: 5 ta savdo agentga oylik 25 mln so'm beramanmi yoki bitta tizimga investitsiya qilamanmi?",
                    "Savdo agentning kunlik real qamrovi: 35-45 do'kon. Yo'lda vaqt, salomlashish, suhbat, savat to'plash, kvitansiya yozish — har biri vaqt yeydi. Oyiga taxminan 900-1 100 do'kon-tashrif chiqadi.",
                    "Telegram bot esa 24/7 ishlaydi. Cheklov yo'q. 5 ming do'kondan kuniga ham 5 ming buyurtma kelishi mumkin (real raqam emas, ammo nazariy chegara). Cheklov server resursida, agent operativ vaqtida emas.",
                    "Xarajat tomonida: 5 ta agent oylik o'rtacha 4-5 mln so'm = 20-25 mln so'm. Plus benzin, telefon, faktor xarajatlar — yana 5 mln. Jami 25-30 mln so'm/oy.",
                    "Bot tizimi: oylik abonement modelida 1-3 mln so'm orasida. Agentlarga nisbatan 90% arzon. Lekin tizim yangi mijoz topib bermaydi — bu hali ham odam ishi.",
                    "Sifat tomoni murakkabroq. Agent yangi mahsulotni qanday tanitishni biladi, mijoz e'tirozini hal qila oladi, ishonch quradi. Bot esa neytral — sotmaydi, faqat qabul qiladi.",
                    'Eng samarali model — gibrid. Agent yangi mijozlar topadi va qiyin holatlarni hal qiladi (debt collection, claim handling, kategoriya kengaytirish). Bot esa rutin qayta zakaslarni avtomatlashtiradi.',
                    "Gibrid modelda agentlar soni 5 dan 2 ga tushadi, qamrov 1 000 dan 5 000 do'konga oshadi. Tashkilot 60-70% xarajatni tejaydi va daromad 3-4 baravar oshadi. OpenSales ushbu model uchun mo'ljallangan.",
                ]),
            ],
            [
                'slug' => 'dokondan-zakas-qabul-qilish-avtomatlashtirish-5-qadam',
                'title' => "Do'kondan zakas qabul qilish: avtomatlashtirish 5 qadami",
                'excerpt' => "Qog'oz kvitansiya va telefon orqali zakas qabul qilishdan to'liq avtomatlashgan tizimga o'tish jarayoni — 5 ta amaliy qadam.",
                'meta_title' => "Do'kondan zakas qabul qilish — avtomatlashtirish 5 qadam",
                'meta_description' => "Do'kondan zakas qabul qilishni qanday avtomatlashtirish kerak: katalog, bot, qarzdorlik, yetkazib berish va hisobot bo'yicha amaliy 5 qadamli reja.",
                'read_minutes' => 6,
                'body' => $this->body([
                    "Avtomatlashtirish katta loyiha emas — har bir qadam aniq, oxirgi qadamga yetilganda butun tizim qarmoqda emas, ishda bo'ladi. Distribyutsiyada bu reja oddiy ko'rinishi mumkin.",
                    "1-qadam: Katalog raqamlashtirish. Birinchi navbatda mahsulot nomi, narx, qoldiq va birlik (dona/blok/karton) bir joyga yig'iladi. Excel emas — DB. OpenSalesda kategoriyalar va massa import birinchi kun ishga tushadi.",
                    "2-qadam: Telegram bot ulash. Har distribyutor o'z bot tokenini biriktiradi, do'kondorlar /start orqali qo'shiladi. Telefon tasdiqlash + manzil — boshlash uchun yetarli.",
                    "3-qadam: Do'kondor savatini ishga tushirish. Katalog kategoriya, mahsulot tanlash, miqdor kiritish, savat ko'rib chiqish, tasdiqlash — barcha qadam Telegram ichida. Hech qanday qo'shimcha ilova o'rnatish kerak emas.",
                    "4-qadam: Moliya va qarzdorlik. Har zakasdan keyin do'kon balansi avtomatik yangilanadi. To'lov qaydlari, qarzdorlik eskirishi (aging) 0-30, 30-60, 60+ kun bo'yicha. Bu raqamlar har ertalab hisobotda chiqadi.",
                    "5-qadam: Yetkazib berish marshruti. Zayavkalar yetkazib beruvchining telefoniga avtomatik tushadi: manzil, telefon, jami summa. Bir tugma — yetkazildi, to'lov qabul qilindi.",
                    "Ushbu 5 qadam OpenSales ichida 5 daqiqada konfiguratsiyalanadi. Bir oy ichida tashkilot zayavka tushish hajmini 2-3 marta ko'taradi, qarzdorlik 30-40% kamayadi. Bu raqamlar real mijoz keysiga asoslangan.",
                ]),
            ],
            [
                'slug' => 'fmcg-distribyutsiya-tizimi-tanlash-qollanma-2026',
                'title' => "FMCG distribyutsiya tizimi tanlash qo'llanmasi 2026",
                'excerpt' => "Distribyutsiya boshqaruv tizimini tanlashda nimaga e'tibor berish kerak — funksiya ro'yxati, integratsiya, narx, qo'llab-quvvatlash va lokal moslashuv.",
                'meta_title' => "FMCG distribyutsiya tizimi tanlash qo'llanmasi (2026)",
                'meta_description' => "Distribyutsiya boshqaruv tizimini tanlash bo'yicha 2026 yildagi to'liq qo'llanma: kriterialar, narx solishtirish, lokal yondashuv.",
                'read_minutes' => 9,
                'body' => $this->body([
                    "Tizim tanlashdagi eng katta xato — eng katta brendni tanlash. Halqaro SAP, Oracle yoki 1C mahalliy distribyutorga hech qanday afzallik bermaydi, aksincha implementatsiya 6 oy cho'ziladi va xarajat 50-100 mln so'mga yetadi.",
                    "Birinchi kriteriya — mahalliylik. Tizim so'mda hisob yuritadimi? O'zbek tili interfeysmi? Lokal soliq qoidalarini biladi? OOO ma'lumotlari uchun INN tekshiruvi bormi? Mahalliy logistika xaritalarini qo'llab-quvvatlaydimi?",
                    "Ikkinchi kriteriya — kanal moslashuvchanligi. Mijozlar Telegram'da turibdimi? Bot orqali zakas qabul qilamizmi? Yoki web orqali? Tizim qaysi kanallar bilan ishlashi sizga moslashishi kerak — siz tizimga emas.",
                    "Uchinchi kriteriya — narx modeli. Foydalanuvchi soniga qarab oylikmi (xodimlar ko'paysa qimmatlashadi)? Buyurtmadan foiz olinadimi (mavsumda yo'qotamiz)? Yoki do'kondan oylik (tarmoq kengaytirsak xarajat bashoratli)? OpenSales uchta modelni bersa, har biri haqiqiy hisob-kitobga moslashadi.",
                    "To'rtinchi kriteriya — joriy etish vaqti. 6 oylik implementatsiya — bu yo'qolgan 6 oy savdo. SaaS yondashuvda 1-5 kun yetarli bo'lishi kerak: ro'yxatdan o'tish, bot tokeni biriktirish, katalog import, ishga tayyor.",
                    "Beshinchi kriteriya — qo'llab-quvvatlash. Mahalliy tilda yordamchi bormi? WhatsApp'da javob qachon keladi? Sayt bilan teyaga muammo bo'lsa 1 soat ichida hal qilinadimi yoki 1 hafta kutamizmi?",
                    "Oltinchi kriteriya — ma'lumotlar mulki. Sizning bazaningiz qayerda? Eksport qila olasizmi? Tizim tark etganda butun ma'lumot qo'lda qoladi-mi yoki vendorga qulflanasizmi?",
                    "Ushbu 6 kriteriya bo'yicha tizim tanlasangiz, mahalliy bozorga moslashgan, narxi bashoratli, 5 daqiqada ishga tushadigan va sizning ma'lumotlaringizni boshqarib turuvchi yechimga kelasiz. Aynan shu falsafa bilan OpenSales ishlab chiqildi.",
                ]),
            ],
            [
                'slug' => 'qarzdorlik-nazorati-distribyutorlar-uchun-amaliy-yondashuv',
                'title' => 'Qarzdorlik nazorati: distribyutorlar uchun amaliy yondashuv',
                'excerpt' => "Qarzdorlik distribyutsiyaning eng katta yo'qotish manbai. 30-60-90 kun aging, sof balans, kreditbroni — har bir kontseptual atama amaliy misol bilan.",
                'meta_title' => "Qarzdorlik nazorati — distribyutorlar uchun amaliy qo'llanma",
                'meta_description' => "Distribyutsiyada qarzdorlik nazorati: aging, kredit limit, sof balans hisoblash, eskirgan qarzni qaytarish bo'yicha amaliy yondashuv.",
                'read_minutes' => 7,
                'body' => $this->body([
                    "Distribyutorda eng katta yashirin zarar — qaytarilmagan qarz. Sotuv hisobotida ko'rinmaydi, lekin har oy 5-10% zayavka shu yerda qoladi. 1 mlrd so'mlik distribyutorga yiliga 50-100 mln so'm yo'qotish.",
                    'Qarzdorlik nazorati uchta kontseptual asosga tayanadi: aging, kredit limit, sof balans.',
                    "Aging — qarzning yoshi. 0-30 kun: yangi, normal. 30-60 kun: ogohlantirish, agent qo'ng'iroq qilishi kerak. 60-90 kun: sotuv vaqtincha to'xtatiladi. 90+ kun: yuridik bo'lim ishi.",
                    "Kredit limit — har do'konga maksimal qarzdorlik miqdori. Tarixiy ma'lumotga asoslanadi: o'rtacha 1.5-2 oylik aylanma. Limit oshganda yangi zayavka qabul qilinmaydi, balans 0 ga tushishini kutiladi.",
                    "Sof balans — joriy holat. Musbat: do'kon oldindan to'lagan. Manfiy: qarz bor. OpenSales'da har zakasdan keyin avtomatik yangilanadi, har to'lov qaydi shop_balance ga tushadi.",
                    "Amaliy texnika — har juma kuni 30-60 kunlik aging hisobotini ko'rib chiqish. 30-60 kun bandidagi do'konlarga oldindan SMS yoki Telegram xabar yuborish ('to'lov muddati yaqinlashmoqda'). Bu shunchaki eslatma — 80% holatda yetarli.",
                    "60-90 kun bandida — agentni yo'naltirish. Shaxsiy uchrashuv, sabab aniqlash, to'lov rejasi tuzish. Bu yerda telefon emas, fizik uchrashuv ishlaydi.",
                    "90+ kun — yuridik. Hech qanday yangi sotuv. Yozma da'vo, kerakli holda sud. OpenSales'da auditni jurnalda har transaktsiya yozilganligi bu bosqichda muhim — dalil sifatida ishlatiladi.",
                    "Mana shu uchta darajali strategiya yiliga tashkilotga 30-50 mln so'mni qaytarib beradi. OpenSales esa bu jarayonni avtomatik kuzatadi, har juma ertalab aging hisobotini email/Telegram orqali yetkazadi.",
                ]),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $paragraphs
     */
    private function body(array $paragraphs): string
    {
        return implode("\n\n", $paragraphs);
    }
}
