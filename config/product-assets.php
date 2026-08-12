<?php

return [
    'suppliers' => [
        'prodigi' => [
            '650ML-WATER-BOTTLE' => [
                'source_directory' => 'product-assets/prodigi/650ML-WATER-BOTTLE',
                'assets' => [
                    'product-01' => [
                        'filename' => 'Prodigi-copper-water-bottle01.jpg',
                        'role' => 'primary',
                        'variant_options' => ['colour' => 'white'],
                        'public' => ['disk' => 'public', 'storage_key' => 'products/cattie-water-bottle/catalogue/bottle01.jpg'],
                        'alt_text' => 'White 650 ml Cattie water bottle with colourful wraparound artwork and lid beside it',
                        'sort_order' => 0,
                    ],
                    'product-02' => [
                        'filename' => 'Prodigi-copper-water-bottle02.jpg',
                        'role' => 'gallery',
                        'variant_options' => ['colour' => 'navy'],
                        'public' => ['disk' => 'public', 'storage_key' => 'products/cattie-water-bottle/catalogue/bottle02.jpg'],
                        'alt_text' => 'Navy 650 ml Cattie water bottle with copper-coloured character artwork',
                        'sort_order' => 1,
                    ],
                    'close-up' => [
                        'filename' => 'Prodigi-copper-water-bottle-close-up.jpg',
                        'role' => 'detail',
                        'variant_options' => ['colour' => 'white'],
                        'public' => ['disk' => 'public', 'storage_key' => 'products/cattie-water-bottle/catalogue/close-up.jpg'],
                        'alt_text' => 'Close-up of the printed artwork finish on the white Cattie water bottle',
                        'sort_order' => 2,
                    ],
                    'lid' => [
                        'filename' => 'Prodigi-copper-water-bottle-lid.jpg',
                        'role' => 'detail',
                        'variant_options' => ['colour' => 'white'],
                        'public' => ['disk' => 'public', 'storage_key' => 'products/cattie-water-bottle/catalogue/lid.jpg'],
                        'alt_text' => 'Close-up of the stainless-steel carry lid on the white Cattie water bottle',
                        'sort_order' => 3,
                    ],
                    'blank' => [
                        'filename' => 'Prodigi-copper-water-bottle-blank.jpg',
                        'role' => 'mockup_source',
                        'variant_options' => ['colour' => 'white'],
                        'public' => ['disk' => 'public', 'storage_key' => 'products/cattie-water-bottle/mockup/blank.jpg'],
                    ],
                ],
            ],
        ],
        'treatpod' => [
            'WB-750ML-FLIP' => [
                'source_directory' => 'product-assets/treatpod/WB-750ML-FLIP',
                'assets' => [
                    'white-primary' => [
                        'filename' => 'wb-750mlwht-flipred.jpg',
                        'role' => 'primary',
                        'variant_options' => ['colour' => 'white'],
                        'public' => ['disk' => 'public', 'storage_key' => 'products/water-bottle-with-red-flip-lid/catalogue/wb-750mlwht-flipred.jpg'],
                        'alt_text' => 'White 750 ml aluminium water bottle with a red flip lid',
                        'sort_order' => 0,
                    ],
                    'white-gallery' => [
                        'filename' => 'wb-750mlwht-flipredm.jpg',
                        'role' => 'gallery',
                        'variant_options' => ['colour' => 'white'],
                        'public' => ['disk' => 'public', 'storage_key' => 'products/water-bottle-with-red-flip-lid/catalogue/wb-750mlwht-flipredm.jpg'],
                        'alt_text' => 'White bottle displaying an example wraparound printed design',
                        'sort_order' => 1,
                    ],
                    'silver-primary' => [
                        'filename' => 'wb-750mlslv-flipred.jpg',
                        'role' => 'primary',
                        'variant_options' => ['colour' => 'silver'],
                        'public' => ['disk' => 'public', 'storage_key' => 'products/water-bottle-with-red-flip-lid/catalogue/wb-750mlslv-flipred.jpg'],
                        'alt_text' => 'Silver 750 ml aluminium water bottle with a red flip lid',
                        'sort_order' => 10,
                    ],
                    'silver-gallery' => [
                        'filename' => 'wb-750mlslv-flipredm.jpg',
                        'role' => 'gallery',
                        'variant_options' => ['colour' => 'silver'],
                        'public' => ['disk' => 'public', 'storage_key' => 'products/water-bottle-with-red-flip-lid/catalogue/wb-750mlslv-flipredm.jpg'],
                        'alt_text' => 'Silver bottle displaying an example printed design',
                        'sort_order' => 11,
                    ],
                    'silver-detail' => [
                        'filename' => 'wb-750mlslv-flipred-1a0f7e95-6a87-46c6-9c05-02ef967ee1ac.jpg',
                        'role' => 'detail',
                        'variant_options' => ['colour' => 'silver'],
                        'public' => ['disk' => 'public', 'storage_key' => 'products/water-bottle-with-red-flip-lid/catalogue/wb-750mlslv-flipred-1a0f7e95-6a87-46c6-9c05-02ef967ee1ac.jpg'],
                        'alt_text' => 'Example showing how bold artwork appears on the silver bottle finish',
                        'sort_order' => 12,
                    ],
                ],
            ],
        ],
    ],
];
