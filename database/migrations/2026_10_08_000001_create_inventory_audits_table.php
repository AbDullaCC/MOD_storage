<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_in_id')->constrained('product_ins')->restrictOnDelete();
            $table->string('record_type', 20);
            $table->unsignedBigInteger('record_id');
            $table->string('action', 20);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->text('reason')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->timestamp('created_at');
            $table->index(['product_in_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_audits');
    }
};
