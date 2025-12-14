<?php

declare(strict_types=1);

namespace AIArmada\Products\Database\Factories;

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        $data = [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'short_description' => $this->faker->sentence(),
            'description' => $this->faker->paragraphs(3, true),
            'type' => $this->faker->randomElement(ProductType::cases()),
            'status' => ProductStatus::Active,
            'visibility' => ProductVisibility::CatalogSearch,
            'price' => $this->faker->numberBetween(1000, 100000), // RM 10 - RM 1000
            'compare_price' => $this->faker->optional(0.3)->numberBetween(1000, 150000),
            'cost' => $this->faker->numberBetween(500, 50000),
            'sku' => mb_strtoupper(Str::random(8)),
            'barcode' => $this->faker->optional()->ean13(),
            'is_featured' => $this->faker->boolean(20),
            'weight' => $this->faker->optional()->randomFloat(2, 0.1, 50),
            'meta_title' => null,
            'meta_description' => null,
        ];

        // Only include dimension columns if they exist in the table
        $tableColumns = Schema::getColumnListing('products');
        if (in_array('length', $tableColumns)) {
            $data['length'] = $this->faker->optional()->randomFloat(2, 1, 100);
        }
        if (in_array('width', $tableColumns)) {
            $data['width'] = $this->faker->optional()->randomFloat(2, 1, 100);
        }
        if (in_array('height', $tableColumns)) {
            $data['height'] = $this->faker->optional()->randomFloat(2, 1, 100);
        }

        return $data;
    }

    /**
     * Product in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Draft,
        ]);
    }

    /**
     * Product in active status.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Active,
        ]);
    }

    /**
     * Featured product.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Digital product.
     */
    public function digital(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Digital,
            'requires_shipping' => false,
            'weight' => null,
            'length' => null,
            'width' => null,
            'height' => null,
        ]);
    }

    /**
     * Product on sale.
     */
    public function onSale(int $discountPercent = 20): static
    {
        return $this->state(function (array $attributes) use ($discountPercent) {
            $originalPrice = $attributes['price'] ?? 10000;
            $salePrice = (int) ($originalPrice * (1 - $discountPercent / 100));

            return [
                'price' => $salePrice,
                'compare_price' => $originalPrice,
            ];
        });
    }
}
