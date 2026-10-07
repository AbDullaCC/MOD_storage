<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_in_id')->constrained('product_ins')->onDelete('cascade');

            $table->integer('quantity');
            $table->dateTime('date');
            $table->string('source')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additions');
    }
};
