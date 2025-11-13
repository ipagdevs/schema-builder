<?php

namespace IpagDevs\Tests\Examples;

use DateTime;
use IpagDevs\Model\Model;
use IpagDevs\Tests\BaseTestCase;
use IpagDevs\Model\Schema\Schema;
use IpagDevs\Model\Schema\SchemaBuilder;

class User extends Model
{
    protected function schema(SchemaBuilder $schema): Schema
    {
        $schema->int('id')->default(1)->nullable();
        $schema->date('created_at')->format('Y-m-d')->nullable();
        $schema->enum('groups', ['admin', 'user'])->array()->array()->nullable();
        $schema->has('child', User::class)->many()->nullable();
        
        return $schema->build();
    }
}

class ModelTest extends BaseTestCase
{
    public function testCanParseModel(): void
    {
        $model = User::parse([
            'id' => 2,
            'created_at' => '2022-08-27',
            'groups' => [['admin'], ['user']],
            'child' => [
                [
                    'id' => 99,
                    'created_at' => null,
                    'child' => [
                        [
                            'id' => 3,
                            'created_at' => new DateTime()
                        ]
                    ]
                ],
                [
                    'id' => 100,
                ]
            ]
        ]);

        $this->assertEquals(2, $model->get('id'));
    }
}
