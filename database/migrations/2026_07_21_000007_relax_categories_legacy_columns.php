<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * category_name/category_code/category_image sont NOT NULL sans défaut dans
 * la migration d'origine, mais absents du $fillable du modèle Category
 * (qui utilise name/ref/image) : aucun code actuel ne peut donc les
 * renseigner. On les rend nullable pour permettre l'insertion.
 */
return new class extends Migration
{
    protected array $columns = ['category_name', 'category_code', 'category_image'];

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                if (Schema::hasColumn('categories', $column)) {
                    $table->string($column)->nullable()->change();
                }
            }
        });
    }

    public function down(): void
    {
        //
    }
};
