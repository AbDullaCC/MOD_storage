<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductIn;
use App\Models\Out;
use Faker\Factory as Faker;

class StorageSeeder extends Seeder
{
    public function run(): void
    {
        // Use Arabic Faker for realistic data
        $faker = Faker::create('ar_SA');

        // Categories relevant to IT/Storage
        $categories = ['لابتوب', 'طابعة', 'شاشة', 'كيبلات', 'أحبار', 'سيرفر', 'ماوس', 'كيبورد', 'راوتر', 'سويتش'];
        
        // Manufacturers
        $brands = ['Dell', 'HP', 'Lenovo', 'Cisco', 'Canon', 'Logitech', 'Samsung', 'Apple'];

        for ($i = 0; $i < 100; $i++) {
            
            // 1. Create the Product (Input)
            $initialQty = $faker->numberBetween(5, 50);
            $dateAdded = $faker->dateTimeBetween('-1 year', '-1 month'); // Added sometime last year

            $product = ProductIn::create([
                'name' => $faker->randomElement(['جهاز ', 'وحدة ', 'كرتون ', 'طقم ']) . $faker->word,
                'category' => $faker->randomElement($categories),
                'manufacturer' => $faker->randomElement($brands),
                'model_type' => $faker->bothify('Model-###??'),
                'quantity' => $initialQty,
                'serial_number' => $faker->boolean(70) ? $faker->bothify('SN-########') : null, // 70% chance of having SN
                'reciever' => $faker->name,
                'description' => $faker->sentence,
                'added_at' => $dateAdded,
            ]);

            // 2. Randomly create Removals (Outs) for this item
            // 60% chance that this item has some removals
            if ($faker->boolean(60)) {
                
                // Determine how much is left to remove
                $currentStock = $initialQty;
                
                // Create 1 to 4 removal transactions
                $numberOfRemovals = $faker->numberBetween(1, 4);

                for ($j = 0; $j < $numberOfRemovals; $j++) {
                    if ($currentStock <= 0) break;

                    // Remove a random amount (but not more than what's left)
                    $qtyToRemove = $faker->numberBetween(1, ceil($currentStock / 2));
                    
                    // Date must be AFTER addition but BEFORE now
                    $dateRemoved = $faker->dateTimeBetween($dateAdded, 'now');

                    Out::create([
                        'product_in_id' => $product->id,
                        'quantity' => $qtyToRemove,
                        'destination' => $faker->randomElement(['الفرع الرئيسي', 'المستودع الفرعي', 'المكتب الفني', 'الإدارة المالية', 'قسم IT']),
                        'date' => $dateRemoved,
                        'note' => $faker->realText(30),
                    ]);

                    $currentStock -= $qtyToRemove;
                }
            }
        }
    }
}