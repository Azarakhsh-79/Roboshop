<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'پنل مدیریت ربات‌ها' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">

    <x-admin.sidebar />

    <div class="flex-1 flex flex-col w-full">

        <x-admin.header :title="$title ?? 'داشبورد'" />

        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>

    </div>

</body>
</html>
