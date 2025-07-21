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
        Schema::create('categories', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('category_name');
            $table->string('category_code');
            $table->string('category_image');
            $table->string('slug')->nullable();
            $table->integer('subcategory_count')->default(0);
            $table->integer('product_count')->default(0);
            $table->string('designatotal_producttion_tech')->nullable();
            $table->string('total_earning')->nullable();
            $table->string('description')->nullable();
            $table->string('status')->nullable();

            $table->softDeletes();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
