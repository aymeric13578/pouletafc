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
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('parameter8');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->unsignedBigInteger('shop_id')->nullable()->after('parameter8');
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
            $table->unsignedBigInteger('seller_id')->nullable()->after('parameter8');
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
            $table->string('shop_logo')->nullable()->after('parameter8');
            $table->integer('quantity')->nullable()->after('parameter8');
            $table->string('slug')->nullable()->after('parameter8');

            $table->string('unity')->nullable()->change();
            $table->string('unit_price')->nullable()->change();
            $table->string('category')->nullable()->change();
            $table->string('stock')->nullable()->change();
            $table->string('status')->nullable()->change();
            $table->string('locality')->nullable()->change();
            $table->string('id_saler')->nullable()->change();
            $table->string('bar_code')->nullable()->change();
            $table->string('commission')->nullable()->change();
            $table->string('product_image1')->nullable()->change();
            $table->string('product_image2')->nullable()->change();
            $table->string('product_image3')->nullable()->change();
            $table->string('product_image4')->nullable()->change();
            $table->string('product_image5')->nullable()->change();
            $table->string('product_image6')->nullable()->change();
            $table->string('product_image7')->nullable()->change();
            $table->string('product_image8')->nullable()->change();
            $table->string('product_image9')->nullable()->change();
            $table->string('product_image10')->nullable()->change();
            $table->string('product_video1')->nullable()->change();
            $table->string('product_video2')->nullable()->change();
            $table->string('product_video3')->nullable()->change();
            $table->string('product_length')->nullable()->change();
            $table->string('product_width')->nullable()->change();
            $table->string('product_epaisseur')->nullable()->change();
            $table->string('product_volume')->nullable()->change();
            $table->string('product_color')->nullable()->change();
            $table->string('product_weigth')->nullable()->change();
            $table->string('parameter1')->nullable()->change();
            $table->string('parameter2')->nullable()->change();
            $table->string('parameter3')->nullable()->change();
            $table->string('parameter4')->nullable()->change();
            $table->string('parameter5')->nullable()->change();
            $table->string('parameter6')->nullable()->change();
            $table->string('parameter7')->nullable()->change();
            $table->string('parameter8')->nullable()->change();
            $table->string('sale_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            Schema::disableForeignKeyConstraints();

            $table->dropForeign('products_category_id_foreign');
            $table->dropColumn('category_id');
            $table->dropForeign('products_shop_id_foreign');
            $table->dropColumn('shop_id');
            $table->dropColumn('shop_logo');
        });
    }
};
