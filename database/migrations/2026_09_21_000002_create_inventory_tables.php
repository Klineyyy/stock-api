<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 64)->unique();
            $table->string('item_name');
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('stock_uom', 16)->default('Nos');
            $table->timestamps();
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 14, 3)->default(0);
            $table->decimal('reorder_level', 14, 3)->nullable();
            $table->decimal('reorder_qty', 14, 3)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
        });

        // The database itself refuses negative stock, even if a bug in the code ever tried.
        DB::statement('ALTER TABLE stock_levels ADD CONSTRAINT stock_levels_qty_check CHECK (qty >= 0)');

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('qty_change', 14, 3);
            $table->decimal('qty_after', 14, 3);
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('products');
        Schema::dropIfExists('warehouses');
    }
};
