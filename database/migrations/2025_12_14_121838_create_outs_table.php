<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_in_id')->constrained('product_ins')->cascadeOnDelete();
            $table->integer('quantity');
            $table->dateTime('date');
            $table->string('destination')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('created_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outs');
    }
};
