<?php

return [
    // General Messages
    'welcome_message' => "👋 Hello :name\n\n🛍 Welcome to the ShopBot Platform.\n\nYou can build and manage your Telegram store in seconds using this bot.",
    'unknown_command' => '🤔 I didn\'t understand that! Please use the menu buttons.',
    'maintenance' => '🚧 This section is under maintenance...',
    'language_changed' => '✅ Language changed successfully.',
    'select_language' => "🌐 Please select your language:\n\n👇 Please select your language:",
    'cancel_action' => '❌ Cancel',

    // Main Menu
    'menu' => [
        'add_bot' => '➕ Create New Store',
        'my_bots' => '🤖 My Stores',
        'support' => '📞 Support',
        'guide' => '📚 Guide & Tutorials',
        'settings' => '⚙️ Settings',
        'change_lang' => '🌐 Language',
        'back' => '🔙 Back to Main Menu',
        'back_button' => '« Back',
    ],

    // Bot Creation Process (Telegram Managed Bots flow)
    'creation' => [
        'bot_exists' => "⚠️ Bot Already Exists.\n\nThis bot has already been registered in our system.",
        'invalid_phone_format' => "⚠️ Invalid Phone Number.\n\nPlease enter your number in international format.",
        'webhook_failed' => "⚠️ Bot created but Webhook connection failed.\nError: :error\nPlease contact support.",
        'database_error' => "❌ A database error occurred. Please try again.",

        'create_button' => '🤖 Create New Bot',

        'managed_start_prompt' => "To create a new shop, tap the button below.\n\nA Telegram screen will open where you can choose or edit the bot's name and username, and confirm it right there.",

        'still_waiting' => "⏳ Still waiting for the bot to be created.\n\nIf you opened the bot-creation screen in Telegram and confirmed it, please wait a moment — you'll get the next message shortly.",

        'suggested_bot_name' => 'My Shop',

        'phone_session_expired' => "❌ Couldn't find this step's info. If the bot was already created, you can set the phone number later from \"My Shops\".",

        'phone_saved' => '✅ Phone number :phone saved successfully.',

        'phone_skipped' => "Okay, we'll continue without a phone number for now. You can add it anytime later from the shop settings.",

        'success_managed_message' => "🎉 The shop \":name\" (@:username) was created and activated successfully!\n\n",

        'ask_phone_post' => '📱 Please send the phone number (e.g. 09xxxxxxxxx), or skip if you don\'t want to add it now.',

        'skip_phone' => 'Skip ⏭',
    ],
];
