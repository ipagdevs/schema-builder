# PHP Schema Builder

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ipagdevs/schema-builder.svg?style=flat-square)](https://packagist.org/packages/ipagdevs/schema-builder)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/com/ipagdevs/schema-builder.svg?style=flat-square)](https://travis-ci.com/ipagdevs/schema-builder)
[![Total Downloads](https://img.shields.io/packagist/dt/ipagdevs/schema-builder.svg?style=flat-square)](https://packagist.org/packages/ipagdevs/schema-builder)

A powerful and intuitive library for building schema-driven data models in PHP. Validate, parse, and serialize complex data structures with ease, ensuring your data models are robust and reliable.

## Features

-   **Fluent Schema Definition:** Define your model's structure using a clean and fluent API.
-   **Rich Type System:** Supports `int`, `string`, `float`, `bool`, `date`, `enum`, and complex types like arrays and nested relationships.
-   **Powerful Validation:** Built-in validation rules like `required`, `nullable`, `min`, `max`, `limit`, `between`, and more.
-   **Relationships:** Easily define `has` (one) and `hasMany` (many) relationships between models.
-   **Mutators:** Transform attribute values with custom logic on get or set.
-   **Smart Serialization:** Control how your models are converted to JSON, including hiding sensitive attributes.
-   **Defaults and Nullables:** Effortlessly handle default values and nullable attributes.
-   **Strongly Typed:** Designed to work well with modern, strongly-typed PHP (8.1+).

## Installation

Install the library via Composer:

```bash
composer require ipagdevs/schema-builder
```

## Core Concepts

### 1. Model

The `Model` is the heart of the library. You extend the base `IpagDevs\Model\Model` class to create your own data models. Each model is responsible for defining its structure through a schema.

### 2. Schema

The `Schema` defines the "shape" of your data: its attributes, types, and validation rules. You define the schema within your model by implementing the `schema()` method.

### 3. Mutators

`Mutators` allow you to apply custom transformations to data when an attribute is set or retrieved. This is perfect for formatting, sanitizing, or deriving values.

## Getting Started: A Simple Example

Let's create a simple `Review` model.

```php
<?php

use IpagDevs\Model\Model;
use IpagDevs\Model\Schema\Schema;
use IpagDevs\Model\Schema\SchemaBuilder;

class Review extends Model
{
    protected function schema(SchemaBuilder $schema): Schema
    {
        $schema->int('rating')->required();
        $schema->string('comment')->nullable();
        $schema->string('author')->default('Anonymous');

        return $schema->build();
    }
}
```

Now, let's use it to parse and handle data:

```php
// Parse data from an array
$review = Review::parse([
    'rating' => 5,
    'comment' => 'Excellent product!'
]);

// Get attributes
echo $review->get('rating'); // 5
echo $review->get('author'); // 'Anonymous' (from default)

// Parsing with missing required data will throw an exception
try {
    Review::parse(['comment' => 'This will fail.']);
} catch (\IpagDevs\Model\Schema\Exception\SchemaAttributeParseException $e) {
    // "Missing required attribute"
    echo $e->getMessage();
}

// Convert to JSON
echo json_encode($review, JSON_PRETTY_PRINT);
// or
echo json_encode($review->jsonSerialize(), JSON_PRETTY_PRINT);
```

**JSON Output:**

```json
{
    "rating": 5,
    "comment": "Excellent product!",
    "author": "Anonymous"
}
```

## Advanced Usage & Features

Here's a more comprehensive `Product` model that showcases many of the library's features.

```php
<?php

use IpagDevs\Model\Model;
use IpagDevs\Model\Schema\Schema;
use IpagDevs\Model\Schema\Mutator;
use IpagDevs\Model\Schema\SchemaBuilder;
use IpagDevs\Model\Schema\MutatorContext;

class Category extends Model
{
    protected function schema(SchemaBuilder $schema): Schema
    {
        $schema->int('id')->required();
        $schema->string('name')->required();
        return $schema->build();
    }
}

class Product extends Model
{
    protected function schema(SchemaBuilder $schema): Schema
    {
        // Basic Types
        $schema->int('id')->required();
        $schema->float('price')->min(0.01)->required();
        $schema->bool('is_active')->default(true);
        $schema->date('available_since', 'Y-m-d')->required();
        $schema->enum('condition', ['new', 'used', 'refurbished']);

        // String Validation
        $schema->string('name')->between(5, 50); // min 5, max 50 chars
        $schema->string('description')->limit(200)->nullable();
        $schema->string('short_description')->truncate(10); // Truncates if > 10 chars

        // Arrays and Lists
        $schema->string('tags')->list()->nullable(); // An array of strings
        $schema->int('alternate_ids')->list()->nullable(); // An array of integers
        $schema->string('matrix')->list()->list()->nullable(); // An array of arrays of strings

        // Hidden Attributes
        $schema->string('internal_code')->hidden(); // Always hidden from jsonSerialize()
        $schema->string('promo_code')->nullable()->hiddenIf(
            fn($value, Product $model) => $model->get('price') > 100.0
        );

        // Relationships
        $schema->has('category', Category::class)->required();
        $schema->hasMany('reviews', Review::class)->nullable();

        // Mutated Attributes
        $schema->string('sku');
        $schema->string('slug');

        return $schema->build();
    }

    // Mutator for the 'sku' attribute
    public function sku(): Mutator
    {
        return new Mutator(
            getter: fn($value) => "SKU-{$value}",
            setter: function ($value, MutatorContext $context) {
                $value = mb_strtoupper(str_replace(' ', '-', $value));
                $context->assert(mb_ereg_match('^[A-Z0-9\-]+$', $value), "Invalid SKU format.");
                return $value;
            }
        );
    }

    // Mutator for the 'slug' attribute (derived from 'name')
    public function slug(): Mutator
    {
        return new Mutator(
            setter: fn($value, MutatorContext $context) => mb_strtolower(str_replace(' ', '-', $context->target->get('name')))
        );
    }
}
```

### Parsing Complex Data

You can now parse a complete data structure that matches the schema.

```php
$productData = [
    'id' => 101,
    'name' => 'Awesome Wireless Keyboard',
    'price' => 129.99,
    'available_since' => '2025-10-20',
    'condition' => 'new',
    'internal_code' => 'XYZ-SECRET',
    'short_description' => 'This will be truncated for sure.',
    'tags' => ['wireless', 'mechanical', 'rgb'],
    'category' => ['id' => 15, 'name' => 'Peripherals'],
    'reviews' => [
        ['rating' => 5, 'comment' => 'Best keyboard ever!'],
        ['rating' => 4, 'author' => 'A happy user'],
    ],
    'sku' => 'awk-101-blue',
    'promo_code' => 'SAVE10'
];

$product = Product::parse($productData);

// Accessing data
echo $product->get('name'); // 'Awesome Wireless Keyboard'
echo $product->get('short_description'); // 'This will '

// Mutators are applied automatically
echo $product->get('sku'); // 'SKU-AWK-101-BLUE'
echo $product->get('slug'); // 'awesome-wireless-keyboard'

// Relationships are parsed into Model instances
echo get_class($product->get('category')); // 'Category'
echo get_class($product->get('reviews')[0]); // 'Review'
```

### Attribute Types and Validation

| Method                            | Description                                                                  |
| --------------------------------- | ---------------------------------------------------------------------------- |
| `->required()`                    | The attribute must be present in the input data.                             |
| `->nullable()`                    | The attribute can be `null`.                                                 |
| `->default($value)`               | Sets a default value if the attribute is not provided.                       |
| `->min($value)`                   | For `float` or `int`, sets a minimum value.                                  |
| `->max($value)`                   | For `float` or `int`, sets a maximum value.                                  |
| `->limit($chars)`                 | For `string`, enforces a maximum character length.                           |
| `->truncate($chars)`              | For `string`, truncates the string if it exceeds the character limit.        |
| `->between($min, $max)`           | For `string`, enforces a min and max character length.                       |
| `->positives([...])`              | For `bool`, defines values that should be parsed as `true`.                  |
| `->negatives([...])`              | For `bool`, defines values that should be parsed as `false`.                 |
| `->list()` or `->array()`         | Converts an attribute into an array of its original type. Chainable.         |
| `->hidden()`                      | Hides the attribute from `jsonSerialize()` output.                           |
| `->hiddenIf(callable $check)`     | Hides the attribute from `jsonSerialize()` if the callback returns `true`.   |
| `->hiddenIfNull()`                | A shortcut for hiding an attribute if its value is `null`.                   |

### Serialization

The library provides two ways to convert a model to an array.

#### `jsonSerialize()`

This method is automatically called by `json_encode()`. It respects the `hidden()`, `hiddenIf()`, and `hiddenIfNull()` rules, making it safe for public API responses. It also serializes nested models and collections.

```php
$product = Product::parse($productData);
$json = $product->jsonSerialize();

// $json will NOT contain 'internal_code'.
// 'promo_code' will be hidden because price > 100.
// 'category' and 'reviews' will be arrays of serialized data.
```

#### `toArray()`

This method converts the model and all its relations into a "raw" array, including all attributes (even hidden ones). It's useful for debugging or internal data transfer.

```php
$array = $product->toArray();

// $array WILL contain 'internal_code'.
```

## Contributing

Contributions are welcome! Please feel free to submit a pull request or open an issue for any bugs or feature requests.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.