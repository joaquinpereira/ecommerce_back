<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::factory()->create([
            'name' => 'Admin General',
            'email' => 'admin@ecommerce.com',
            'role' => UserRole::Admin->value,
        ]);

        // Proveedor
        $supplier = User::factory()->create([
            'name' => 'Proveedor Principal',
            'email' => 'supplier@ecommerce.com',
            'role' => UserRole::Proveedor->value,
        ]);

        // Cliente
        $cliente = User::factory()->create([
            'name' => 'Cliente Demo',
            'email' => 'cliente@ecommerce.com',
            'role' => UserRole::Cliente->value,
        ]);

        // Categorías
        $catElectronics = Category::factory()->create(['name' => 'Electrónica', 'slug' => 'electronica']);
        $catFashion = Category::factory()->create(['name' => 'Ropa y Calzado', 'slug' => 'ropa-y-calzado']);

        // Productos
        Product::factory()->count(15)->create([
            'supplier_id' => $supplier->id,
            'category_id' => $catElectronics->id,
        ]);

        Product::factory()->count(10)->create([
            'supplier_id' => $supplier->id,
            'category_id' => $catFashion->id,
        ]);
    }
}
