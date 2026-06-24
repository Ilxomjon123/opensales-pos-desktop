<?php

declare(strict_types=1);

return [
    'applogin' => [
        'ask_contact' => 'Kirish uchun telefon raqamingizni ulashing 👇',
        'share_phone' => '📱 Raqamni ulashish',
        'success' => '✅ Tasdiqlandi!',
        'invalid' => 'Havola eskirgan. Ilovadan qaytadan urinib ko\'ring.',
        'open_app' => 'Kirish uchun OpenSales ilovasini oching.',
        'open_hint' => 'Davom etish uchun tugmani bosing 👇',
        'open_button' => '🚀 Ilovani ochish',
    ],

    'currency' => 'so\'m',

    'language' => [
        'choose' => '🌐 Tilni tanlang:',
        'changed' => '✅ Til o\'zgartirildi.',
        'button' => '🌐 Til',
    ],

    'owner' => [
        'invalid' => '❌ Ulanish link noto\'g\'ri yoki muddati o\'tgan.',
        'connected' => "✅ *Bildirishnomalar ulandi!*\n\nEndi yangi buyurtmalar va muhim xabarlar shu chatga keladi.",
    ],

    'invite' => [
        'invalid' => "❌ Taklif link noto'g'ri yoki muddati o'tgan.\nYangi link uchun yetkazib beruvchiga murojaat qiling.",
        'success_title' => '✅ *Tabriklayman!*',
        'success_body' => 'Siz *:shop* mijoziga muvaffaqiyatli biriktirildingiz.',
    ],

    'menu' => [
        'greeting' => 'Assalomu alaykum, *:shop*!',
        'balance' => '💰 Saldo: *:balance*',
        'pending' => '⏳ Kutilmoqda: *:amount so\'m*',
        'order_prompt' => 'Buyurtma berish va hisobni ko\'rish uchun quyidagi tugmani bosing:',
        'order_cta' => 'Buyurtma berish uchun quyidagi tugmani bosing:',
    ],

    'start' => [
        'need_invite' => "👋 Salom!\n\nBotdan foydalanish uchun yetkazib beruvchidan *taklif link* so'rang.\nLink orqali kirsangiz, mijozga avtomatik biriktirilasiz.",
    ],

    'fallback' => [
        'need_start' => '⚠️ Avval yetkazib beruvchidan olingan /start link orqali kiring.',
        'order_prompt' => '🛍 Buyurtma berish uchun quyidagi tugmani bosing:',
    ],

    'button' => [
        'order' => '🛍 Buyurtma berish',
        'open_order' => '📦 Buyurtmani ochish',
        'send_location' => '📍 Joylashuvni yuborish',
        'send_phone' => '📞 Telefonni yuborish',
        'skip' => '⏭ O\'tkazib yuborish',
    ],

    'register' => [
        'welcome' => "👋 *Xush kelibsiz!*\n\nBuyurtma yetkazib berish uchun *manzilingizni* kiriting.\nMasalan: \"Toshkent, Yunusobod tumani, Amir Temur ko'chasi 12-uy\".\n\nYoki quyidagi tugma orqali joriy joylashuvingizni yuboring:",
        'address_too_short' => '❌ Manzil juda qisqa. Iltimos, to\'liqroq kiriting (kamida 5 belgi) yoki joylashuv tugmasini bosing.',
        'address_saved' => "✅ Manzil saqlandi.\n\nEndi telefon raqamingizni yuboring (ixtiyoriy).\nYetkazib beruvchi siz bilan bog'lanishi uchun kerak bo'ladi.",
        'done' => '✅ Tayyor! Endi katalogdan tanlang.',
        'new_customer_title' => '🎉 *:shop* — yangi mijoz sifatida ro\'yxatdan o\'tdingiz!',
        'address_line' => '📍 Manzil: :address',
        'error' => '❌ Ro\'yxatdan o\'tishda xatolik yuz berdi. Iltimos, /start ni qayta yuboring yoki yetkazib beruvchi bilan bog\'laning.',
        'fallback_name' => 'Mijoz #:id',
    ],

    'order' => [
        'message_head' => '📦 Buyurtma #:number',
        'confirmed' => "✅ *Buyurtma #:number qabul qilindi*\n\nJami: :total so'm\nHozirgi saldo: :balance so'm\n_Saldo yetkazib berilgandan so'ng yangilanadi._\n\nDiller tez orada bog'lanadi.",
        'edited_head' => '✏️ *Buyurtma #:number tahrirlandi*',
        'status_head' => ':emoji *Buyurtma #:number* — :status',
        'line_total' => 'Jami: :amount so\'m',
        'line_delivered' => 'Yetkazildi: :amount so\'m',
        'line_paid' => 'To\'landi: :amount so\'m',
        'line_discount' => 'Chegirma: :amount so\'m',
        'line_balance' => 'Saldo: :amount so\'m',
        'line_returned' => 'Saldoga qaytarildi: :amount so\'m',
        'line_new_balance' => 'Yangi saldo: :amount so\'m',
    ],

    'product' => [
        'new_title' => '🆕 <b>Yangi mahsulot</b>',
        'price_changed_title' => '💰 <b>Narx o\'zgardi</b>',
        'price_line' => 'Narx (:unit): <b>:price so\'m</b>',
        'price_change_line' => 'Narx (:unit): <s>:old</s> → <b>:new so\'m</b>',
        'pack_price_line' => 'Blok narxi: <b>:price so\'m</b>',
        'pack_price_change_line' => 'Blok narxi: <s>:old</s> → <b>:new so\'m</b>',
        'order_button' => '🛒 Buyurtma berish',
    ],

    'debt' => [
        'reminder' => "💳 *Qarz eslatmasi*\n\n🏪 *:shop*\n\n:dealer diller tomonidan hisob-kitob.\n\nJoriy qarz: *:amount so'm*\n\nIltimos, tez orada to'lovni amalga oshiring.",
    ],

    'profile_default' => [
        'short' => ':name — buyurtma boti',
        'description' => 'Buyurtma berish uchun "Buyurtma berish" tugmasini bosing yoki /start yuboring.',
    ],

];
