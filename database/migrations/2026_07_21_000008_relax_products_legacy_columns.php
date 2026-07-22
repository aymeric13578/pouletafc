<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * reference/designation_tech restent NOT NULL sans défaut depuis la
 * migration d'origine ; le nouveau flux boutique utilise name/ref à la
 * place. On les rend nullable pour ne pas bloquer les insertions.
 */
return new class extends Migration
{
    protected array $columns = ['reference', 'designation_tech'];

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                if (Schema::hasColumn('products', $column)) {
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
