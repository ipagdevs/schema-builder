<?php

namespace IpagDevs\Tests;

use DateTimeInterface;
use IpagDevs\Model\Model;
use IpagDevs\Model\Schema\Schema;
use IpagDevs\Model\Schema\Mutator;
use IpagDevs\Model\Schema\SchemaBuilder;
use IpagDevs\Model\Schema\Exception\MutatorAttributeException;
use IpagDevs\Model\Schema\Exception\SchemaAttributeParseException;

class Review extends Model
{
    protected function schema(SchemaBuilder $schema): Schema
    {
        $schema->int('rating')->required();
        $schema->string('comment')->nullable()->hiddenIfNull();
        $schema->string('author')->default('Anonymous');

        return $schema->build();
    }

    public function getRating(): int
    {
        return $this->get('rating');
    }
}

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
        $schema->int('id')->required();
        $schema->string('name')->between(5, 50)->required();
        $schema->string('description')->limit(200)->nullable();
        $schema->float('price')->min(0.01)->required();
        $schema->bool('is_active')->default(true);
        $schema->date('available_since', 'Y-m-d')->required();
        $schema->enum('condition', ['new', 'used', 'refurbished']);
        $schema->string('internal_code')->hidden();
        $schema->string('short_description')->truncate(10);
        $schema->string('sku');
        $schema->string('slug');
        $schema->string('tags')->list()->nullable();
        $schema->int('alternate_ids')->list()->nullable();
        $schema->has('category', Category::class)->required();
        $schema->hasMany('reviews', Review::class)->nullable();

        $schema->bool('is_shippable')->positives(['Y', 'yes'])->negatives(['N', 'no'])->default(true);
        $schema->string('non_truncated_limit')->limit(5);
        $schema->string('matrix')->list()->list()->nullable();
        $schema->string('promo_code')->nullable()->hiddenIf(
            fn($value, Product $model) => $model->get('price') > 100.0
        );


        return $schema->build();
    }

    public function sku(): Mutator
    {
        return new Mutator(
            getter: fn($value) => "SKU-{$value}",
            setter: function ($value, $context) {
                $value = mb_strtoupper(str_replace(' ', '-', $value));
                $context->assert(mb_ereg_match('^[A-Z0-9\-]+$', $value), "Invalid SKU format.");
                return $value;
            }
        );
    }

    public function slug(): Mutator
    {
        return new Mutator(
            setter: fn($value, $context) => mb_strtolower(str_replace(' ', '-', $context->target->get('name')))
        );
    }
}

class ComprehensiveModelTest extends BaseTestCase
{
    /**
     * @var array<mixed>
     */
    private array $fullProductData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fullProductData = [
            'id' => 101,
            'name' => 'Awesome Wireless Keyboard',
            'description' => 'A very long description that is well within the 200 character limit.',
            'short_description' => 'This description is definitely going to be truncated.',
            'price' => 129.99,
            'is_active' => true,
            'available_since' => '2025-10-20',
            'condition' => 'new',
            'internal_code' => 'XYZ-INTERNAL-SECRET',
            'sku' => 'awk-101-blue',
            'slug' => 'will-be-overwritten',
            'tags' => ['wireless', 'mechanical', 'rgb'],
            'alternate_ids' => [202, 303],
            'category' => ['id' => 15, 'name' => 'Peripherals'],
            'reviews' => [
                ['rating' => 5, 'comment' => 'Best keyboard ever!'],
                ['rating' => 4, 'author' => 'lucasmilhoranca-ipag'],
                ['rating' => 1, 'comment' => 'Broke after one day.', 'author' => 'UnhappyCustomer'],
            ],
            'promo_code' => 'SAVE10',
        ];
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionProperty(Model::class, 'globalSchema');
        $reflection->setAccessible(true);
        $reflection->setValue(null, []);
        parent::tearDown();
    }

    public function testModelHiddenIf(): void
    {
        $review = Review::parse([
            'rating' => 4,
            'comment' => null,
        ]);

        $serialized = $review->jsonSerialize();
        $this->assertArrayNotHasKey(
            'comment',
            $serialized,
            "The 'comment' attribute should be hidden from JSON when it is null."
        );
    }


    public function testFullDataParsingAndGetters(): void
    {
        $product = Product::parse($this->fullProductData);

        $this->assertSame(101, $product->get('id'));
        $this->assertSame('Awesome Wireless Keyboard', $product->get('name'));

        $this->assertSame('This descr', $product->get('short_description'));

        $this->assertSame(129.99, $product->get('price'));
        $this->assertTrue($product->get('is_active'));
        $this->assertInstanceOf(DateTimeInterface::class, $product->get('available_since'));
        $this->assertSame('SKU-AWK-101-BLUE', $product->get('sku'));
        $this->assertSame('awesome-wireless-keyboard', $product->get('slug'));
        $this->assertInstanceOf(Category::class, $product->get('category'));
        $this->assertIsArray($product->get('reviews'));
        $this->assertInstanceOf(Review::class, $product->get('reviews')[0]);
    }

    public function testSerializationAndArrayConversion(): void
    {
        $product = Product::parse($this->fullProductData);

        $json = $product->jsonSerialize();
        $this->assertArrayNotHasKey('internal_code', $json);
        $this->assertIsArray($json['category']);

        $array = $product->toArray();
        $this->assertArrayHasKey('internal_code', $array);

        $this->assertIsArray($array['category'], "The 'has' relation should be a plain array in toArray().");
        $this->assertSame('Peripherals', $array['category']['name']);

        $this->assertIsArray($array['reviews'][0], "The 'hasMany' items should also be plain arrays in toArray().");
        $this->assertSame(5, $array['reviews'][0]['rating']);
    }

    public function testAttributeValidationFailures(): void
    {
        $data = $this->fullProductData;
        unset($data['name']);
        $this->expectException(SchemaAttributeParseException::class);
        $this->expectExceptionMessage("'Product.name' Missing required attribute");
        Product::parse($data);
    }

    public function testStringTypeValidationFailure(): void
    {
        $data = $this->fullProductData;
        $data['name'] = 'shrt';
        $this->expectException(SchemaAttributeParseException::class);
        $this->expectExceptionMessage("is shorter than the minimum of 5 characters");
        Product::parse($data);
    }

    public function testFloatTypeValidationFailure(): void
    {
        $data = $this->fullProductData;
        $data['price'] = 0;
        $this->expectException(SchemaAttributeParseException::class);
        $this->expectExceptionMessage("is less than the minimum value of 0.01");
        Product::parse($data);
    }

    public function testMutatorValidationFailure(): void
    {
        $this->expectException(MutatorAttributeException::class);
        $this->expectExceptionMessage("Invalid SKU format.");

        $data = $this->fullProductData;
        $data['sku'] = 'invalid sku with spaces and @';
        Product::parse($data);
    }

    public function testPartialDataWithDefaultsAndNullables(): void
    {
        $minimalData = [
            'id' => 202,
            'name' => 'Minimal Viable Product',
            'price' => 9.99,
            'available_since' => '2025-01-01',
            'category' => ['id' => 1, 'name' => 'General'],
        ];

        $product = Product::parse($minimalData);
        $this->assertTrue($product->get('is_active'));
        $this->assertNull($product->get('description'));
        $this->assertNull($product->get('reviews'));
    }

    public function testBooleanAttributeWithCustomMatchers(): void
    {
        $product = Product::make();

        $product->set('is_shippable', 'Y');
        $this->assertTrue($product->get('is_shippable'), "Failed to parse 'Y' as true.");

        $product->set('is_shippable', 'yes');
        $this->assertTrue($product->get('is_shippable'), "Failed to parse 'yes' as true.");

        $product->set('is_shippable', 'N');
        $this->assertFalse($product->get('is_shippable'), "Failed to parse 'N' as false.");

        $product->set('is_shippable', 'no');
        $this->assertFalse($product->get('is_shippable'), "Failed to parse 'no' as false.");

        $this->expectException(SchemaAttributeParseException::class);
        $product->set('is_shippable', 'invalid');
    }

    public function testStringLimitThrowsExceptionWithoutTruncate(): void
    {
        $this->expectException(SchemaAttributeParseException::class);
        $this->expectExceptionMessage("exceeding the limit of 5 character(s)");

        Product::parse(['non_truncated_limit' => 'too long']);
    }

    public function testDateAttributeWithInvalidFormat(): void
    {
        $this->expectException(SchemaAttributeParseException::class);
        $this->expectExceptionMessage("Provided value is not a valid date");

        $data = $this->fullProductData;
        $data['available_since'] = 'invalid-date-format';
        Product::parse($data);
    }

    public function testRelationHasManyFailsWithAssociativeArray(): void
    {
        $this->expectException(SchemaAttributeParseException::class);
        $this->expectExceptionMessage("Provided value is not a list.");

        $data = $this->fullProductData;
        $data['reviews'] = ['first_review' => ['rating' => 5]];
        Product::parse($data);
    }

    public function testHiddenIfWithComplexCondition(): void
    {
        $highPriceData = $this->fullProductData;
        $product1 = Product::parse($highPriceData);
        $this->assertArrayNotHasKey('promo_code', $product1->jsonSerialize(), "Promo code should be hidden for high-priced items.");

        $lowPriceData = $this->fullProductData;
        $lowPriceData['price'] = 50.00;
        $product2 = Product::parse($lowPriceData);
        $this->assertArrayHasKey('promo_code', $product2->jsonSerialize(), "Promo code should be visible for low-priced items.");
        $this->assertSame('SAVE10', $product2->jsonSerialize()['promo_code']);
    }

    public function testParsingEmptyRelationArray(): void
    {
        $data = $this->fullProductData;
        $data['reviews'] = [];

        $product = Product::parse($data);
        $this->assertIsArray($product->get('reviews'));
        $this->assertEmpty($product->get('reviews'));
    }

    public function testArrayOfArraysParsing(): void
    {
        $data = $this->fullProductData;
        $data['matrix'] = [
            ['a', 'b', 'c'],
            ['d', 'e', 'f'],
        ];

        $product = Product::parse($data);
        $matrix = $product->get('matrix');

        $this->assertIsArray($matrix);
        $this->assertCount(2, $matrix);
        $this->assertIsArray($matrix[0]);
        $this->assertSame('e', $matrix[1][1]);
    }
}
