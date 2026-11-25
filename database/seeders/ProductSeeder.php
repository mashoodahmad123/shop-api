<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Wireless Mouse', 'sku' => 'ACC-MOUSE-01', 'price' => 799.00, 'stock_quantity' => 50, 'status' => 'active'],
            ['name' => 'Mechanical Keyboard', 'sku' => 'ACC-KEYB-01', 'price' => 3499.00, 'stock_quantity' => 20, 'status' => 'active'],
            ['name' => 'USB-C Charger 65W', 'sku' => 'PWR-USBC-65', 'price' => 1899.00, 'stock_quantity' => 35, 'status' => 'active'],
            ['name' => 'Laptop Stand', 'sku' => 'ACC-STAND-01', 'price' => 1299.00, 'stock_quantity' => 15, 'status' => 'active'],
            ['name' => 'HD Webcam', 'sku' => 'CAM-HD-720', 'price' => 2299.00, 'stock_quantity' => 0, 'status' => 'active'],
            ['name' => 'Noise Cancelling Headphones', 'sku' => 'AUD-ANC-01', 'price' => 5999.00, 'stock_quantity' => 10, 'status' => 'active'],
            ['name' => 'Old Model Speaker', 'sku' => 'AUD-SPK-OLD', 'price' => 999.00, 'stock_quantity' => 8, 'status' => 'inactive'],
            ['name' => 'HDMI Cable 2m', 'sku' => 'CBL-HDMI-2M', 'price' => 349.00, 'stock_quantity' => 120, 'status' => 'active'],
        ];

        foreach ($products as $data) {
            Product::updateOrCreate(['sku' => $data['sku']], $data);
        }
    }
}
