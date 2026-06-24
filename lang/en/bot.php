<?php

declare(strict_types=1);

return [
    'applogin' => [
        'ask_contact' => 'Share your phone number to sign in 👇',
        'share_phone' => '📱 Share phone number',
        'success' => '✅ Confirmed!',
        'invalid' => 'Link expired. Try again from the app.',
        'open_app' => 'Open the OpenSales app to sign in.',
        'open_hint' => 'Tap the button to continue 👇',
        'open_button' => '🚀 Open the app',
    ],

    'currency' => 'so\'m',

    'language' => [
        'choose' => '🌐 Choose your language:',
        'changed' => '✅ Language changed.',
        'button' => '🌐 Language',
    ],

    'owner' => [
        'invalid' => '❌ The connection link is invalid or has expired.',
        'connected' => "✅ *Notifications connected!*\n\nNew orders and important messages will now arrive in this chat.",
    ],

    'invite' => [
        'invalid' => "❌ The invite link is invalid or has expired.\nPlease contact your supplier for a new link.",
        'success_title' => '✅ *Congratulations!*',
        'success_body' => 'You have been successfully linked to *:shop*.',
    ],

    'menu' => [
        'greeting' => 'Hello, *:shop*!',
        'balance' => '💰 Balance: *:balance*',
        'pending' => '⏳ Pending: *:amount so\'m*',
        'order_prompt' => 'Tap the button below to place an order and view your account:',
        'order_cta' => 'Tap the button below to place an order:',
    ],

    'start' => [
        'need_invite' => "👋 Hello!\n\nTo use the bot, ask your supplier for an *invite link*.\nWhen you open it, you'll be linked to the customer automatically.",
    ],

    'fallback' => [
        'need_start' => '⚠️ Please first open the /start link provided by your supplier.',
        'order_prompt' => '🛍 Tap the button below to place an order:',
    ],

    'button' => [
        'order' => '🛍 Place an order',
        'open_order' => '📦 Open order',
        'send_location' => '📍 Send location',
        'send_phone' => '📞 Send phone number',
        'skip' => '⏭ Skip',
    ],

    'register' => [
        'welcome' => "👋 *Welcome!*\n\nPlease enter your *address* for order delivery.\nFor example: \"Tashkent, Yunusabad district, Amir Temur street, house 12\".\n\nOr send your current location using the button below:",
        'address_too_short' => '❌ The address is too short. Please enter it in more detail (at least 5 characters) or tap the location button.',
        'address_saved' => "✅ Address saved.\n\nNow send your phone number (optional).\nYour supplier will need it to contact you.",
        'done' => '✅ All set! Now choose from the catalog.',
        'new_customer_title' => '🎉 *:shop* — you have registered as a new customer!',
        'address_line' => '📍 Address: :address',
        'error' => '❌ Something went wrong during registration. Please send /start again or contact your supplier.',
        'fallback_name' => 'Customer #:id',
    ],

    'order' => [
        'message_head' => '📦 Order #:number',
        'confirmed' => "✅ *Order #:number accepted*\n\nTotal: :total so'm\nCurrent balance: :balance so'm\n_The balance updates after delivery._\n\nThe dealer will contact you shortly.",
        'edited_head' => '✏️ *Order #:number edited*',
        'status_head' => ':emoji *Order #:number* — :status',
        'line_total' => 'Total: :amount so\'m',
        'line_delivered' => 'Delivered: :amount so\'m',
        'line_paid' => 'Paid: :amount so\'m',
        'line_discount' => 'Discount: :amount so\'m',
        'line_balance' => 'Balance: :amount so\'m',
        'line_returned' => 'Returned to balance: :amount so\'m',
        'line_new_balance' => 'New balance: :amount so\'m',
    ],

    'product' => [
        'new_title' => '🆕 <b>New product</b>',
        'price_changed_title' => '💰 <b>Price changed</b>',
        'price_line' => 'Price (:unit): <b>:price so\'m</b>',
        'price_change_line' => 'Price (:unit): <s>:old</s> → <b>:new so\'m</b>',
        'pack_price_line' => 'Pack price: <b>:price so\'m</b>',
        'pack_price_change_line' => 'Pack price: <s>:old</s> → <b>:new so\'m</b>',
        'order_button' => '🛒 Place an order',
    ],

    'debt' => [
        'reminder' => "💳 *Debt reminder*\n\n🏪 *:shop*\n\nStatement from dealer :dealer.\n\nCurrent debt: *:amount so'm*\n\nPlease make the payment soon.",
    ],

    'profile_default' => [
        'short' => ':name — order bot',
        'description' => 'Press the "Place an order" button or send /start to order.',
    ],

];
