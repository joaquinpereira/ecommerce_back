<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DatabaseSchemaIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_tables_exist(): void
    {
        $tables = [
            'users',
            'categories',
            'products',
            'carts',
            'cart_items',
            'orders',
            'order_items',
            'personal_access_tokens',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} should exist in schema.");
        }
    }

    public function test_users_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['id', 'uuid', 'name', 'email', 'role', 'password']));
    }

    public function test_products_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('products', [
            'id', 'uuid', 'supplier_id', 'category_id', 'name', 'slug', 'description', 'price', 'stock', 'status', 'deleted_at',
        ]));
    }

    public function test_orders_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('orders', [
            'id', 'uuid', 'user_id', 'status', 'total_amount', 'stripe_payment_intent_id', 'shipping_address',
        ]));
    }
}
