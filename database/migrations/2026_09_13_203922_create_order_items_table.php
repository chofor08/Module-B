<?php

use App\Models\Items;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Items::class, 'item_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->string('unit_price');
            $table->string('sub_total');
            $table->timestamps();

            $table->index(['order_id', 'sub_total']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
