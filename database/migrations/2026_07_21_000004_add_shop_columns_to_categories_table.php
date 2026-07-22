<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App\Models\Category référence name/image/ref, absents de la migration
 * d'origine (category_name/category_image). Colonnes ajoutées en plus,
 * sans supprimer les anciennes (utilisées ailleurs dans l'admin/API).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (! Schema::hasColumn('categories', 'image')) {
                $table->string('image')->nullable();
            }
            if (! Schema::hasColumn('categories', 'ref')) {
                $table->string('ref')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            foreach (['name', 'image', 'ref'] as $column) {
                if (Schema::hasColumn('categories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
