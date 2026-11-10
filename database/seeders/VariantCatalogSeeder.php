<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\VariantType;
use App\Models\VariantValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VariantCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // 1. MASTER COLORS (Shared across categories)
        $masterColors = [
            ['value' => 'Black', 'label' => 'Black', 'meta' => ['hex' => '#000000']],
            ['value' => 'White', 'label' => 'White', 'meta' => ['hex' => '#FFFFFF']],
            ['value' => 'Gray', 'label' => 'Gray', 'meta' => ['hex' => '#808080']],
            ['value' => 'Charcoal', 'label' => 'Charcoal', 'meta' => ['hex' => '#36454F']],
            ['value' => 'Silver', 'label' => 'Silver', 'meta' => ['hex' => '#C0C0C0']],
            ['value' => 'Red', 'label' => 'Red', 'meta' => ['hex' => '#E53935']],
            ['value' => 'Maroon', 'label' => 'Maroon', 'meta' => ['hex' => '#800000']],
            ['value' => 'Burgundy', 'label' => 'Burgundy', 'meta' => ['hex' => '#800020']],
            ['value' => 'Blue', 'label' => 'Blue', 'meta' => ['hex' => '#1E88E5']],
            ['value' => 'Navy', 'label' => 'Navy', 'meta' => ['hex' => '#000080']],
            ['value' => 'Sky Blue', 'label' => 'Sky Blue', 'meta' => ['hex' => '#87CEEB']],
            ['value' => 'Green', 'label' => 'Green', 'meta' => ['hex' => '#43A047']],
            ['value' => 'Olive', 'label' => 'Olive', 'meta' => ['hex' => '#808000']],
            ['value' => 'Mint', 'label' => 'Mint', 'meta' => ['hex' => '#98FF98']],
            ['value' => 'Yellow', 'label' => 'Yellow', 'meta' => ['hex' => '#FDD835']],
            ['value' => 'Mustard', 'label' => 'Mustard', 'meta' => ['hex' => '#FFDB58']],
            ['value' => 'Orange', 'label' => 'Orange', 'meta' => ['hex' => '#FB8C00']],
            ['value' => 'Pink', 'label' => 'Pink', 'meta' => ['hex' => '#EC407A']],
            ['value' => 'Rose', 'label' => 'Rose', 'meta' => ['hex' => '#FF007F']],
            ['value' => 'Purple', 'label' => 'Purple', 'meta' => ['hex' => '#8E24AA']],
            ['value' => 'Lavender', 'label' => 'Lavender', 'meta' => ['hex' => '#E6E6FA']],
            ['value' => 'Brown', 'label' => 'Brown', 'meta' => ['hex' => '#795548']],
            ['value' => 'Beige', 'label' => 'Beige', 'meta' => ['hex' => '#F5F5DC']],
            ['value' => 'Cream', 'label' => 'Cream', 'meta' => ['hex' => '#FFFDD0']],
            ['value' => 'Khaki', 'label' => 'Khaki', 'meta' => ['hex' => '#C3B091']],
            ['value' => 'Gold', 'label' => 'Gold', 'meta' => ['hex' => '#FFD700']],
            ['value' => 'Multicolor', 'label' => 'Multicolor', 'meta' => ['hex' => 'linear-gradient(135deg, red, yellow, green, blue)']],
        ];

        // 2. DEFINE ALL VARIANT TYPES & THEIR PREDEFINED VALUES
        $catalog = [
            [
                'name' => 'Color',
                'slug' => 'color',
                'display_type' => 'color_swatch',
                'description' => 'Master color palette with visual swatches',
                'sort_order' => 1,
                'values' => $masterColors,
            ],
            [
                'name' => 'Size (International)',
                'slug' => 'size-international',
                'display_type' => 'pills',
                'description' => 'Standard international apparel sizing (XXS to 6XL)',
                'sort_order' => 2,
                'values' => [
                    ['value' => 'XXS', 'label' => 'XXS'],
                    ['value' => 'XS', 'label' => 'XS'],
                    ['value' => 'S', 'label' => 'S (Small)'],
                    ['value' => 'M', 'label' => 'M (Medium)'],
                    ['value' => 'L', 'label' => 'L (Large)'],
                    ['value' => 'XL', 'label' => 'XL (Extra Large)'],
                    ['value' => 'XXL', 'label' => 'XXL (2XL)'],
                    ['value' => '3XL', 'label' => '3XL'],
                    ['value' => '4XL', 'label' => '4XL'],
                    ['value' => '5XL', 'label' => '5XL'],
                    ['value' => '6XL', 'label' => '6XL'],
                ],
            ],
            [
                'name' => 'Size (Numeric)',
                'slug' => 'size-numeric',
                'display_type' => 'pills',
                'description' => 'Numeric dress and pants sizes (0 to 30)',
                'sort_order' => 3,
                'values' => array_map(fn ($n) => ['value' => (string) $n, 'label' => "Size {$n}"], range(0, 30, 2)),
            ],
            [
                'name' => 'Waist Size',
                'slug' => 'size-waist',
                'display_type' => 'pills',
                'description' => 'Waist measurement in inches (24" to 50")',
                'sort_order' => 4,
                'values' => array_map(fn ($n) => ['value' => "{$n}\"", 'label' => "{$n} inches"], [24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 38, 40, 42, 44, 46, 48, 50]),
            ],
            [
                'name' => 'Children\'s Size',
                'slug' => 'size-children',
                'display_type' => 'pills',
                'description' => 'Toddler and youth clothing sizes',
                'sort_order' => 5,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['2T', '3T', '4T', '5T', '6', '7', '8', '10', '12', '14', '16']),
            ],
            [
                'name' => 'Material',
                'slug' => 'material-fashion',
                'display_type' => 'pills',
                'description' => 'Apparel and accessories fabric/material',
                'sort_order' => 6,
                'values' => array_map(fn ($m) => ['value' => $m, 'label' => $m], ['Cotton', 'Polyester', 'Denim', 'Linen', 'Silk', 'Wool', 'Leather', 'Faux Leather', 'Nylon', 'Spandex', 'Rayon', 'Fleece', 'Velvet', 'Canvas']),
            ],
            [
                'name' => 'Fit',
                'slug' => 'fit',
                'display_type' => 'pills',
                'description' => 'Apparel cut and silhouette',
                'sort_order' => 7,
                'values' => array_map(fn ($f) => ['value' => $f, 'label' => $f], ['Slim', 'Regular', 'Relaxed', 'Oversized', 'Loose', 'Skinny', 'Straight', 'Wide Leg']),
            ],
            [
                'name' => 'Sleeve Length',
                'slug' => 'sleeve-length',
                'display_type' => 'pills',
                'description' => 'Shirt or dress sleeve length',
                'sort_order' => 8,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['Sleeveless', 'Short Sleeve', 'Three-Quarter Sleeve', 'Long Sleeve']),
            ],
            [
                'name' => 'Pattern',
                'slug' => 'pattern',
                'display_type' => 'pills',
                'description' => 'Design pattern or print',
                'sort_order' => 9,
                'values' => array_map(fn ($p) => ['value' => $p, 'label' => $p], ['Plain', 'Striped', 'Checkered', 'Floral', 'Printed', 'Graphic', 'Polka Dot', 'Camouflage']),
            ],
            [
                'name' => 'Shoe Size (EU)',
                'slug' => 'shoe-size-eu',
                'display_type' => 'pills',
                'description' => 'European footwear sizing (EU 30 to 50)',
                'sort_order' => 10,
                'values' => array_map(fn ($n) => ['value' => "EU {$n}", 'label' => "EU {$n}"], range(30, 50)),
            ],
            [
                'name' => 'Shoe Size (US Men)',
                'slug' => 'shoe-size-us-men',
                'display_type' => 'pills',
                'description' => 'US Men footwear sizing (US 4 to 16)',
                'sort_order' => 11,
                'values' => array_map(fn ($n) => ['value' => "US {$n}M", 'label' => "US {$n} Men"], [4, 4.5, 5, 5.5, 6, 6.5, 7, 7.5, 8, 8.5, 9, 9.5, 10, 10.5, 11, 11.5, 12, 13, 14, 15, 16]),
            ],
            [
                'name' => 'Shoe Size (US Women)',
                'slug' => 'shoe-size-us-women',
                'display_type' => 'pills',
                'description' => 'US Women footwear sizing (US 4 to 14)',
                'sort_order' => 12,
                'values' => array_map(fn ($n) => ['value' => "US {$n}W", 'label' => "US {$n} Women"], [4, 4.5, 5, 5.5, 6, 6.5, 7, 7.5, 8, 8.5, 9, 9.5, 10, 10.5, 11, 11.5, 12, 13, 14]),
            ],
            [
                'name' => 'Shoe Size (CM)',
                'slug' => 'shoe-size-cm',
                'display_type' => 'pills',
                'description' => 'Centimeter foot length (20cm to 32cm)',
                'sort_order' => 13,
                'values' => array_map(fn ($n) => ['value' => "{$n} cm", 'label' => "{$n} cm"], range(20, 32)),
            ],
            [
                'name' => 'Shoe Width',
                'slug' => 'shoe-width',
                'display_type' => 'pills',
                'description' => 'Shoe width fit',
                'sort_order' => 14,
                'values' => array_map(fn ($w) => ['value' => $w, 'label' => $w], ['Narrow', 'Standard', 'Wide', 'Extra Wide']),
            ],
            [
                'name' => 'Footwear Style',
                'slug' => 'shoe-style',
                'display_type' => 'pills',
                'description' => 'Shoe silhouette and style',
                'sort_order' => 15,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['Sneakers', 'Running', 'Casual', 'Formal', 'Sandals', 'Slippers', 'Boots']),
            ],
            [
                'name' => 'Shoe Material',
                'slug' => 'material-shoes',
                'display_type' => 'pills',
                'description' => 'Shoe upper and sole material',
                'sort_order' => 16,
                'values' => array_map(fn ($m) => ['value' => $m, 'label' => $m], ['Leather', 'Synthetic Leather', 'Canvas', 'Mesh', 'Suede', 'Rubber', 'Textile']),
            ],
            [
                'name' => 'RAM (Mobile & Gadgets)',
                'slug' => 'ram-mobile',
                'display_type' => 'pills',
                'description' => 'Smartphone and tablet memory',
                'sort_order' => 17,
                'values' => array_map(fn ($r) => ['value' => $r, 'label' => $r], ['2GB', '3GB', '4GB', '6GB', '8GB', '12GB', '16GB', '24GB']),
            ],
            [
                'name' => 'Storage (Mobile & Gadgets)',
                'slug' => 'storage-mobile',
                'display_type' => 'pills',
                'description' => 'Smartphone and tablet internal capacity',
                'sort_order' => 18,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['16GB', '32GB', '64GB', '128GB', '256GB', '512GB', '1TB', '2TB']),
            ],
            [
                'name' => 'Connectivity',
                'slug' => 'connectivity-mobile',
                'display_type' => 'pills',
                'description' => 'Cellular and wireless generation',
                'sort_order' => 19,
                'values' => array_map(fn ($c) => ['value' => $c, 'label' => $c], ['Wi-Fi', 'Wi-Fi + Cellular', '4G LTE', '5G']),
            ],
            [
                'name' => 'Item Condition',
                'slug' => 'item-condition',
                'display_type' => 'pills',
                'description' => 'Device or product grade/condition',
                'sort_order' => 20,
                'values' => array_map(fn ($c) => ['value' => $c, 'label' => $c], ['New', 'Refurbished', 'Used - Like New', 'Used - Good', 'Used - Fair']),
            ],
            [
                'name' => 'RAM (Computer & Laptop)',
                'slug' => 'ram-computer',
                'display_type' => 'pills',
                'description' => 'Laptop and desktop system RAM',
                'sort_order' => 21,
                'values' => array_map(fn ($r) => ['value' => $r, 'label' => $r], ['4GB', '8GB', '12GB', '16GB', '24GB', '32GB', '48GB', '64GB', '96GB', '128GB']),
            ],
            [
                'name' => 'Storage (Computer & Laptop)',
                'slug' => 'storage-computer',
                'display_type' => 'pills',
                'description' => 'Primary computer storage capacity',
                'sort_order' => 22,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['128GB', '256GB', '512GB', '1TB', '2TB', '4TB', '8TB']),
            ],
            [
                'name' => 'Storage Type',
                'slug' => 'storage-type',
                'display_type' => 'pills',
                'description' => 'Drive technology',
                'sort_order' => 23,
                'values' => array_map(fn ($t) => ['value' => $t, 'label' => $t], ['HDD', 'SATA SSD', 'NVMe SSD']),
            ],
            [
                'name' => 'Processor Family',
                'slug' => 'processor-family',
                'display_type' => 'pills',
                'description' => 'CPU Architecture',
                'sort_order' => 24,
                'values' => array_map(fn ($p) => ['value' => $p, 'label' => $p], ['Intel Core i3', 'Intel Core i5', 'Intel Core i7', 'Intel Core i9', 'Intel Core Ultra 3', 'Intel Core Ultra 5', 'Intel Core Ultra 7', 'Intel Core Ultra 9', 'AMD Ryzen 3', 'AMD Ryzen 5', 'AMD Ryzen 7', 'AMD Ryzen 9', 'AMD Ryzen AI']),
            ],
            [
                'name' => 'Display Size',
                'slug' => 'display-size',
                'display_type' => 'pills',
                'description' => 'Screen diagonal measurement',
                'sort_order' => 25,
                'values' => array_map(fn ($d) => ['value' => $d, 'label' => $d], ['11.6"', '13.3"', '14"', '15.6"', '16"', '17.3"', '18"']),
            ],
            [
                'name' => 'Operating System',
                'slug' => 'operating-system',
                'display_type' => 'pills',
                'description' => 'Pre-installed platform OS',
                'sort_order' => 26,
                'values' => array_map(fn ($o) => ['value' => $o, 'label' => $o], ['Windows', 'Linux', 'macOS', 'ChromeOS', 'No OS']),
            ],
            [
                'name' => 'RAM Capacity (Component)',
                'slug' => 'ram-component-capacity',
                'display_type' => 'pills',
                'description' => 'Desktop RAM stick capacity',
                'sort_order' => 27,
                'values' => array_map(fn ($r) => ['value' => $r, 'label' => $r], ['4GB', '8GB', '16GB', '32GB', '64GB']),
            ],
            [
                'name' => 'RAM Generation',
                'slug' => 'ram-type',
                'display_type' => 'pills',
                'description' => 'Memory technology generation',
                'sort_order' => 28,
                'values' => array_map(fn ($t) => ['value' => $t, 'label' => $t], ['DDR3', 'DDR4', 'DDR5']),
            ],
            [
                'name' => 'RAM Frequency',
                'slug' => 'ram-speed',
                'display_type' => 'pills',
                'description' => 'Clock speed',
                'sort_order' => 29,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['1600MHz', '1866MHz', '2133MHz', '2400MHz', '2666MHz', '2933MHz', '3000MHz', '3200MHz', '3600MHz', '4800MHz', '5200MHz', '5600MHz', '6000MHz', '6400MHz']),
            ],
            [
                'name' => 'Component Storage Capacity',
                'slug' => 'storage-component-capacity',
                'display_type' => 'pills',
                'description' => 'Internal drive capacity',
                'sort_order' => 30,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['120GB', '128GB', '240GB', '250GB', '256GB', '480GB', '500GB', '512GB', '1TB', '2TB', '4TB', '8TB']),
            ],
            [
                'name' => 'Storage Interface',
                'slug' => 'storage-interface',
                'display_type' => 'pills',
                'description' => 'Drive bus interface',
                'sort_order' => 31,
                'values' => array_map(fn ($i) => ['value' => $i, 'label' => $i], ['SATA', 'M.2 SATA', 'M.2 NVMe', 'PCIe']),
            ],
            [
                'name' => 'Power Supply Wattage',
                'slug' => 'psu-wattage',
                'display_type' => 'pills',
                'description' => 'PSU wattage output',
                'sort_order' => 32,
                'values' => array_map(fn ($w) => ['value' => $w, 'label' => $w], ['400W', '450W', '500W', '550W', '600W', '650W', '750W', '850W', '1000W', '1200W']),
            ],
            [
                'name' => 'Motherboard Form Factor',
                'slug' => 'form-factor',
                'display_type' => 'pills',
                'description' => 'Chassis / Board standard size',
                'sort_order' => 33,
                'values' => array_map(fn ($f) => ['value' => $f, 'label' => $f], ['ATX', 'Micro-ATX', 'Mini-ITX']),
            ],
            [
                'name' => 'Audio Connectivity',
                'slug' => 'audio-connectivity',
                'display_type' => 'pills',
                'description' => 'Audio jack / wireless interface',
                'sort_order' => 34,
                'values' => array_map(fn ($c) => ['value' => $c, 'label' => $c], ['Wired', 'Bluetooth', '2.4GHz Wireless', 'USB', 'USB-C', '3.5mm']),
            ],
            [
                'name' => 'Headphone Style',
                'slug' => 'audio-style',
                'display_type' => 'pills',
                'description' => 'Acoustic fit form',
                'sort_order' => 35,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['In-Ear', 'On-Ear', 'Over-Ear', 'Open-Ear']),
            ],
            [
                'name' => 'Microphone',
                'slug' => 'microphone',
                'display_type' => 'pills',
                'description' => 'Voice input presence',
                'sort_order' => 36,
                'values' => array_map(fn ($m) => ['value' => $m, 'label' => $m], ['With Microphone', 'Without Microphone']),
            ],
            [
                'name' => 'Furniture Size',
                'slug' => 'furniture-size',
                'display_type' => 'pills',
                'description' => 'Furniture relative dimension',
                'sort_order' => 37,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['Extra Small', 'Small', 'Medium', 'Large', 'Extra Large']),
            ],
            [
                'name' => 'Furniture Material',
                'slug' => 'furniture-material',
                'display_type' => 'pills',
                'description' => 'Construction material',
                'sort_order' => 38,
                'values' => array_map(fn ($m) => ['value' => $m, 'label' => $m], ['Solid Wood', 'MDF', 'Plywood', 'Metal', 'Glass', 'Plastic', 'Fabric', 'Leather', 'Rattan', 'Bamboo']),
            ],
            [
                'name' => 'Finish',
                'slug' => 'furniture-finish',
                'display_type' => 'pills',
                'description' => 'Surface coating finish',
                'sort_order' => 39,
                'values' => array_map(fn ($f) => ['value' => $f, 'label' => $f], ['Matte', 'Glossy', 'Natural', 'Painted', 'Laminated']),
            ],
            [
                'name' => 'Flavor',
                'slug' => 'flavor',
                'display_type' => 'pills',
                'description' => 'Snack, food and drink taste profile',
                'sort_order' => 40,
                'values' => array_map(fn ($f) => ['value' => $f, 'label' => $f], ['Original', 'Chocolate', 'Vanilla', 'Strawberry', 'Mango', 'Ube', 'Cheese', 'Cookies and Cream', 'Matcha', 'Caramel', 'Coffee', 'Spicy', 'Barbecue', 'Sour Cream', 'Salted', 'Sweet and Sour']),
            ],
            [
                'name' => 'Net Weight',
                'slug' => 'food-weight',
                'display_type' => 'pills',
                'description' => 'Packaged net weight',
                'sort_order' => 41,
                'values' => array_map(fn ($w) => ['value' => $w, 'label' => $w], ['25g', '50g', '100g', '150g', '200g', '250g', '500g', '750g', '1kg', '2kg', '5kg', '10kg']),
            ],
            [
                'name' => 'Volume (Liquid)',
                'slug' => 'liquid-volume',
                'display_type' => 'pills',
                'description' => 'Liquid bottle or container volume',
                'sort_order' => 42,
                'values' => array_map(fn ($v) => ['value' => $v, 'label' => $v], ['100ml', '150ml', '250ml', '330ml', '500ml', '750ml', '1L', '1.5L', '2L', '5L']),
            ],
            [
                'name' => 'Pack Quantity',
                'slug' => 'pack-size',
                'display_type' => 'pills',
                'description' => 'Bundle item count',
                'sort_order' => 43,
                'values' => array_map(fn ($p) => ['value' => $p, 'label' => $p], ['Single', 'Pack of 2', 'Pack of 3', 'Pack of 4', 'Pack of 6', 'Pack of 8', 'Pack of 10', 'Pack of 12', 'Pack of 24', 'Pack of 48']),
            ],
            [
                'name' => 'Spice Level',
                'slug' => 'spice-level',
                'display_type' => 'pills',
                'description' => 'Chili heat level',
                'sort_order' => 44,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['Mild', 'Medium', 'Hot', 'Extra Hot']),
            ],
            [
                'name' => 'Complexion Shade',
                'slug' => 'beauty-shade',
                'display_type' => 'pills',
                'description' => 'Cosmetic skin tone shade',
                'sort_order' => 45,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['Porcelain', 'Ivory', 'Fair', 'Light', 'Beige', 'Natural', 'Medium', 'Tan', 'Deep', 'Espresso']),
            ],
            [
                'name' => 'Skin Undertone',
                'slug' => 'beauty-undertone',
                'display_type' => 'pills',
                'description' => 'Cool, neutral, or warm undertone',
                'sort_order' => 46,
                'values' => array_map(fn ($u) => ['value' => $u, 'label' => $u], ['Cool', 'Neutral', 'Warm']),
            ],
            [
                'name' => 'Cosmetics Volume',
                'slug' => 'beauty-volume',
                'display_type' => 'pills',
                'description' => 'Skincare and beauty container size',
                'sort_order' => 47,
                'values' => array_map(fn ($v) => ['value' => $v, 'label' => $v], ['5ml', '10ml', '15ml', '30ml', '50ml', '100ml', '150ml', '200ml', '250ml', '500ml', '1L']),
            ],
            [
                'name' => 'Skin Type Suitability',
                'slug' => 'skin-type',
                'display_type' => 'pills',
                'description' => 'Target skin suitability',
                'sort_order' => 48,
                'values' => array_map(fn ($t) => ['value' => $t, 'label' => $t], ['Dry', 'Oily', 'Combination', 'Normal', 'Sensitive']),
            ],
            [
                'name' => 'Beauty Finish',
                'slug' => 'beauty-finish',
                'display_type' => 'pills',
                'description' => 'Cosmetic texture finish',
                'sort_order' => 49,
                'values' => array_map(fn ($f) => ['value' => $f, 'label' => $f], ['Matte', 'Satin', 'Dewy', 'Natural', 'Glossy']),
            ],
            [
                'name' => 'Appliance Capacity',
                'slug' => 'appliance-capacity',
                'display_type' => 'pills',
                'description' => 'Chamber or vessel capacity',
                'sort_order' => 50,
                'values' => array_map(fn ($c) => ['value' => $c, 'label' => $c], ['0.5L', '1L', '1.5L', '2L', '3L', '5L', '7L', '10L', '15L', '20L']),
            ],
            [
                'name' => 'Operating Voltage',
                'slug' => 'voltage',
                'display_type' => 'pills',
                'description' => 'AC Voltage standard',
                'sort_order' => 51,
                'values' => array_map(fn ($v) => ['value' => $v, 'label' => $v], ['110V', '220V', '230V', '240V', 'Dual Voltage']),
            ],
            [
                'name' => 'Power Consumption',
                'slug' => 'appliance-power',
                'display_type' => 'pills',
                'description' => 'Rated wattage',
                'sort_order' => 52,
                'values' => array_map(fn ($p) => ['value' => $p, 'label' => $p], ['300W', '500W', '700W', '1000W', '1200W', '1500W', '2000W']),
            ],
            [
                'name' => 'Bag Size',
                'slug' => 'bag-size',
                'display_type' => 'pills',
                'description' => 'Bag / Accessory size',
                'sort_order' => 53,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['Mini', 'Small', 'Medium', 'Large', 'Extra Large']),
            ],
            [
                'name' => 'Bag Style',
                'slug' => 'bag-style',
                'display_type' => 'pills',
                'description' => 'Bag carrying form',
                'sort_order' => 54,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['Backpack', 'Tote', 'Crossbody', 'Shoulder', 'Sling', 'Handbag', 'Travel']),
            ],
            [
                'name' => 'Ring Size (US)',
                'slug' => 'ring-size',
                'display_type' => 'pills',
                'description' => 'US Ring sizing (US 4 to 15)',
                'sort_order' => 55,
                'values' => array_map(fn ($n) => ['value' => "US {$n}", 'label' => "US {$n}"], range(4, 15)),
            ],
            [
                'name' => 'Chain Length',
                'slug' => 'chain-length',
                'display_type' => 'pills',
                'description' => 'Necklace / chain length in inches',
                'sort_order' => 56,
                'values' => array_map(fn ($n) => ['value' => "{$n}\"", 'label' => "{$n} inches"], [14, 16, 18, 20, 22, 24, 26, 28, 30]),
            ],
            [
                'name' => 'Jewelry Metal / Material',
                'slug' => 'jewelry-material',
                'display_type' => 'pills',
                'description' => 'Precious and durable metals',
                'sort_order' => 57,
                'values' => array_map(fn ($m) => ['value' => $m, 'label' => $m], ['Stainless Steel', 'Silver', 'Gold', 'Gold-Plated', 'Titanium', 'Platinum', 'Alloy']),
            ],
            [
                'name' => 'Jewelry Finish',
                'slug' => 'jewelry-finish',
                'display_type' => 'pills',
                'description' => 'Plating color',
                'sort_order' => 58,
                'values' => array_map(fn ($f) => ['value' => $f, 'label' => $f], ['Silver', 'Gold', 'Rose Gold', 'Black']),
            ],
            [
                'name' => 'Watch Strap',
                'slug' => 'watch-strap',
                'display_type' => 'pills',
                'description' => 'Watch band material',
                'sort_order' => 59,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['Leather', 'Silicone', 'Stainless Steel', 'Nylon', 'Fabric']),
            ],
            [
                'name' => 'Age Range',
                'slug' => 'age-range',
                'display_type' => 'pills',
                'description' => 'Target child development stage',
                'sort_order' => 60,
                'values' => array_map(fn ($a) => ['value' => $a, 'label' => $a], ['0–6 Months', '6–12 Months', '1–2 Years', '3–5 Years', '6–8 Years', '9–12 Years', '13+ Years']),
            ],
            [
                'name' => 'Book Format',
                'slug' => 'book-format',
                'display_type' => 'pills',
                'description' => 'Print binding format',
                'sort_order' => 61,
                'values' => array_map(fn ($f) => ['value' => $f, 'label' => $f], ['Paperback', 'Hardcover', 'Spiral Bound', 'Box Set']),
            ],
            [
                'name' => 'Language',
                'slug' => 'book-language',
                'display_type' => 'pills',
                'description' => 'Text language',
                'sort_order' => 62,
                'values' => array_map(fn ($l) => ['value' => $l, 'label' => $l], ['English', 'Filipino', 'Bilingual', 'Other Admin-Approved Languages']),
            ],
            [
                'name' => 'Notebook Paper Size',
                'slug' => 'notebook-size',
                'display_type' => 'pills',
                'description' => 'Standard paper dimensions',
                'sort_order' => 63,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['A4', 'A5', 'B5', 'Letter', 'Legal']),
            ],
            [
                'name' => 'Vehicle Brand Compatibility',
                'slug' => 'vehicle-compatibility',
                'display_type' => 'pills',
                'description' => 'Pre-approved vehicle manufacturer',
                'sort_order' => 64,
                'values' => array_map(fn ($b) => ['value' => $b, 'label' => $b], ['Universal Fit', 'Toyota', 'Honda', 'Yamaha', 'Kawasaki', 'Suzuki', 'Mitsubishi', 'Ford', 'Nissan', 'Hyundai']),
            ],
            [
                'name' => 'Pet Food Flavor',
                'slug' => 'pet-flavor',
                'display_type' => 'pills',
                'description' => 'Pet recipe flavor',
                'sort_order' => 65,
                'values' => array_map(fn ($f) => ['value' => $f, 'label' => $f], ['Chicken', 'Beef', 'Fish', 'Lamb', 'Mixed']),
            ],
            [
                'name' => 'Scale / Model Ratio',
                'slug' => 'model-scale',
                'display_type' => 'pills',
                'description' => 'Scale ratio for models and collectibles',
                'sort_order' => 66,
                'values' => array_map(fn ($s) => ['value' => $s, 'label' => $s], ['1:64', '1:32', '1:24', '1:18', '1:12', '1:6', 'Non-scale']),
            ],
            [
                'name' => 'Resistance / Weight Level',
                'slug' => 'fitness-resistance',
                'display_type' => 'pills',
                'description' => 'Fitness resistance level and equipment weight',
                'sort_order' => 67,
                'values' => array_map(fn ($r) => ['value' => $r, 'label' => $r], ['Light', 'Medium', 'Heavy', 'Extra Heavy', '2kg', '5kg', '10kg', '15kg', '20kg', '25kg']),
            ],
        ];

        // Seed Types and Values
        $typeModelMap = [];
        foreach ($catalog as $typeData) {
            $type = VariantType::updateOrCreate(
                ['slug' => $typeData['slug']],
                [
                    'name' => $typeData['name'],
                    'display_type' => $typeData['display_type'],
                    'description' => $typeData['description'],
                    'sort_order' => $typeData['sort_order'],
                    'is_active' => true,
                ]
            );
            $typeModelMap[$type->slug] = $type;

            foreach ($typeData['values'] as $vIndex => $valData) {
                VariantValue::updateOrCreate(
                    [
                        'variant_type_id' => $type->id,
                        'value' => $valData['value'],
                    ],
                    [
                        'label' => $valData['label'],
                        'meta' => $valData['meta'] ?? null,
                        'sort_order' => $vIndex + 1,
                        'is_active' => true,
                    ]
                );
            }
        }

        // 3. ENSURE ALL CATEGORIES EXIST AND ARE ORGANIZED
        (new CategorySeeder())->run();

        // 4. MAP CATEGORIES TO APPROPRIATE VARIANT TYPES
        // Category slug => Array of VariantType slugs
        $categoryMappings = [
            // 1. Men's Fashion
            'mens-fashion' => ['size-international', 'color', 'material-fashion', 'fit', 'sleeve-length', 'pattern'],
            'mens-apparel' => ['size-international', 'color', 'size-waist', 'size-numeric', 'material-fashion', 'fit', 'sleeve-length', 'pattern'],
            'mens-shoes' => ['shoe-size-eu', 'shoe-size-us-men', 'shoe-size-cm', 'color', 'material-shoes', 'shoe-width', 'shoe-style'],
            'mens-bags-accessories' => ['bag-size', 'color', 'material-fashion', 'bag-style'],

            // 2. Women's Fashion
            'womens-fashion' => ['size-international', 'color', 'material-fashion', 'fit', 'sleeve-length', 'pattern'],
            'womens-apparel' => ['size-international', 'color', 'size-numeric', 'size-waist', 'material-fashion', 'fit', 'sleeve-length', 'pattern'],
            'womens-shoes' => ['shoe-size-eu', 'shoe-size-us-women', 'shoe-size-cm', 'color', 'material-shoes', 'shoe-width', 'shoe-style'],
            'womens-bags' => ['bag-size', 'color', 'material-fashion', 'bag-style'],
            'women-accessories' => ['color', 'material-fashion', 'jewelry-material', 'jewelry-finish'],

            // 3. Babies & Kids
            'babies-kids' => ['size-children', 'color', 'material-fashion', 'age-range'],
            'baby-clothing' => ['size-children', 'color', 'material-fashion', 'pattern', 'age-range'],
            'baby-gear-toys' => ['age-range', 'color', 'material-fashion'],

            // 4. Electronics & Mobiles
            'electronics' => ['color', 'connectivity-mobile', 'item-condition'],
            'mobiles-gadgets' => ['ram-mobile', 'storage-mobile', 'color', 'connectivity-mobile', 'item-condition'],
            'mobiles-accessories' => ['color', 'material-fashion', 'item-condition'],
            'smartwatches' => ['color', 'watch-strap', 'connectivity-mobile', 'item-condition'],
            'audio' => ['audio-connectivity', 'audio-style', 'color', 'microphone'],
            'cameras' => ['color', 'item-condition', 'connectivity-mobile'],
            'drones' => ['color', 'item-condition', 'connectivity-mobile'],
            'gaming' => ['color', 'audio-connectivity', 'storage-mobile', 'item-condition'],

            // 5. Laptops & Computers
            'laptops-computers' => ['ram-computer', 'storage-computer', 'storage-type', 'processor-family', 'display-size', 'color', 'operating-system'],
            'laptops' => ['ram-computer', 'storage-computer', 'storage-type', 'processor-family', 'display-size', 'color', 'operating-system'],
            'desktops-monitors' => ['ram-computer', 'storage-computer', 'storage-type', 'processor-family', 'display-size', 'operating-system', 'color'],
            'pc-components' => ['ram-component-capacity', 'ram-type', 'ram-speed', 'storage-component-capacity', 'storage-interface', 'psu-wattage', 'form-factor'],

            // 6. Home & Living
            'home-living' => ['furniture-material', 'color', 'furniture-size', 'furniture-finish'],
            'furniture' => ['furniture-size', 'furniture-material', 'furniture-finish', 'color'],
            'home-appliances' => ['appliance-capacity', 'voltage', 'appliance-power', 'color'],
            'kitchen-dining' => ['furniture-material', 'color', 'furniture-size', 'pack-size'],
            'bedding' => ['furniture-size', 'size-international', 'color', 'material-fashion', 'pattern'],
            'home-entertainment' => ['display-size', 'voltage', 'color'],

            // 7. Health & Beauty
            'beauty' => ['beauty-shade', 'beauty-volume', 'skin-type', 'beauty-finish'],
            'health-personal-care' => ['beauty-volume', 'skin-type', 'pack-size', 'size-international'],
            'makeup-fragrances' => ['beauty-shade', 'beauty-undertone', 'beauty-volume', 'beauty-finish', 'skin-type'],

            // 8. Food & Groceries
            'groceries' => ['flavor', 'food-weight', 'liquid-volume', 'pack-size', 'spice-level'],
            'snacks' => ['flavor', 'food-weight', 'pack-size', 'spice-level'],
            'beverages' => ['flavor', 'liquid-volume', 'pack-size'],
            'fresh-produce' => ['food-weight', 'pack-size'],
            'canned-goods' => ['flavor', 'food-weight', 'pack-size'],

            // 9. Jewelry & Watches
            'jewelry' => ['ring-size', 'chain-length', 'jewelry-material', 'jewelry-finish', 'watch-strap'],
            'fine-jewelry' => ['ring-size', 'chain-length', 'jewelry-material', 'jewelry-finish'],
            'watches' => ['watch-strap', 'color', 'jewelry-material', 'jewelry-finish'],

            // 10. Sports & Travel
            'sports-travel' => ['size-international', 'color', 'fitness-resistance', 'food-weight', 'bag-size'],
            'camping-hiking' => ['bag-size', 'size-international', 'color', 'furniture-material', 'food-weight'],
            'fitness-equipment' => ['fitness-resistance', 'food-weight', 'color', 'furniture-material'],
            'sports-apparel' => ['size-international', 'color', 'shoe-size-eu', 'shoe-width', 'material-fashion'],

            // 11. Toys, Games & Collectibles
            'toys-games-collectibles' => ['age-range', 'model-scale', 'color', 'material-fashion'],
            'action-figures-collectibles' => ['model-scale', 'age-range', 'color', 'material-fashion'],
            'board-games-puzzles' => ['age-range', 'pack-size'],

            // 12. Hobbies & Stationery
            'hobbies-stationery' => ['book-format', 'book-language', 'notebook-size', 'pack-size', 'color'],
            'books-magazines' => ['book-format', 'book-language', 'pack-size'],
            'office-stationery' => ['notebook-size', 'pack-size', 'color'],

            // 13. Motors & Automotive
            'motors' => ['vehicle-compatibility', 'size-international', 'color', 'material-fashion'],
            'auto-parts' => ['vehicle-compatibility', 'color', 'furniture-material'],
            'motorcycle-accessories' => ['vehicle-compatibility', 'size-international', 'color', 'material-fashion'],

            // 14. Pet Care
            'pet-care' => ['pet-flavor', 'food-weight', 'pack-size', 'size-international', 'color'],
            'pet-food-treats' => ['pet-flavor', 'food-weight', 'pack-size'],
            'pet-supplies-accessories' => ['size-international', 'color', 'material-fashion'],
        ];

        foreach ($categoryMappings as $categorySlug => $typeSlugs) {
            $cat = Category::where('slug', $categorySlug)->first();
            if (! $cat) {
                continue;
            }

            foreach ($typeSlugs as $order => $typeSlug) {
                if (isset($typeModelMap[$typeSlug])) {
                    DB::table('category_variant_types')->updateOrInsert(
                        [
                            'category_id' => $cat->id,
                            'variant_type_id' => $typeModelMap[$typeSlug]->id,
                        ],
                        [
                            'is_required' => false,
                            'sort_order' => $order + 1,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
