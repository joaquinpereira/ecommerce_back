<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('supplier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->string('status')->default('active')->index();
            $table->softDeletes();
            $table->timestamps();

            // B-Tree Compound Indexes for optimized catalog searches & N+1 / join queries
            $table->index(['category_id', 'status']);
            $table->index(['supplier_id', 'status']);
            $table->index(['status', 'price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
