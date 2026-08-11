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
    ],
];
