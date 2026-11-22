<?php

return [

    'label' => 'ناوبری صفحه‌بندی',

    // این دقیقاً همان متنی است که در جدول‌ها نمایش داده می‌شود
    'overview' => '{1} نمایش 1 نتیجه|[2,*] در حال نمایش :first تا :last - همه :total',

    'fields' => [
        'records_per_page' => [
            'label' => 'در هر صفحه',
        ],
    ],

    'actions' => [
        'go_to_page' => [
            'label' => 'رفتن به صفحه :page',
        ],
        'next' => [
            'label' => 'بعدی',
        ],
        'previous' => [
            'label' => 'قبلی',
        ],
    ],

];
