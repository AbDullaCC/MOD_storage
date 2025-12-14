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
            
            $table->string('reciever'); // Who recieved it
            $table->text('description')->nullable();
            $table->timestamp('added_at');
            $table->timestamps(); // Created_at, Updated_at
        });

        // 2. The Outputs Table
        Schema::create('outs', function (Blueprint $table) {
            $table->id();
            // Connects to the specific batch/item
            $table->foreignId('product_in_id')->constrained('product_ins')->onDelete('cascade');
            
            $table->integer('quantity'); // How many removed
            $table->dateTime('date');
            $table->string('destination')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_tables');
    }
};
