<?php

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
        Schema::create('product_ins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('manufacturer')->nullable();
            $table->string('model_type')->nullable();

            $table->integer('quantity'); // Initial quantity
            $table->string('serial_number')->nullable(); // Nullable for bulk items

            $table->string('reciever')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('added_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('created_by_name')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps(); // Created_at, Updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_ins');
    }
};
