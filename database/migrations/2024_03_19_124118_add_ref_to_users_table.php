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
        Schema::table('users', function (Blueprint $table) {
            $table->string('ref')->nullable()->after('id');
            $table->unsignedBigInteger('id_father')->nullable()->after('email');
            $table->string('last_name')->nullable()->after('email');
            $table->string('country')->nullable()->after('email');
            $table->enum('role', ['user', 'agent', 'admin'])->default('user');
            $table->enum('status', ['pending', 'Success', 'failed', 'waiting'])->default('waiting');
            $table->tinyInteger('confirmed')->nullable()->after('email');
            $table->string('confirmation_code')->nullable()->after('email');
            $table->string('default_currency')->nullable()->after('email');
            $table->string('referral_code')->nullable()->after('email');
            $table->string('phone')->nullable()->after('email');
            $table->string('city')->nullable()->after('email');
            $table->string('last_connect')->nullable()->after('email');
            $table->string('cover_img')->nullable()->after('email');
            $table->timestamp('last_active')->nullable()->after('email');
            $table->string('photo')->nullable()->after('email');
            $table->string('description')->nullable()->after('email');
            $table->date('birth')->nullable()->after('email');
            $table->string('country_code')->nullable()->after('email');
            $table->string('recoveryPass_code')->nullable()->after('email');
            $table->enum('sexe', ['H', 'F'])->default('H');
            $table->foreignId('id_country')->nullable()->after('email')->constrained('countries')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            Schema::disableForeignKeyConstraints();

            $table->dropForeign('countries_id_country_foreign');
            $table->dropColumn('id_country');
            $table->dropColumn('ref');
            $table->dropColumn('id_father');
            $table->dropColumn('last_name');
            $table->dropColumn('status');
            $table->dropColumn('confirmed');
            $table->dropColumn('confirmation_code');
            $table->dropColumn('default_currency');
            $table->dropColumn('referral_code');
            $table->dropColumn('phone');
            $table->dropColumn('city');
            $table->dropColumn('last_connect');
            $table->dropColumn('cover_img');
            $table->dropColumn('last_active');
            $table->dropColumn('photo');
            $table->dropColumn('description');
            $table->dropColumn('birth');
            $table->dropColumn('country_code');
            $table->dropColumn('recoveryPass_code');
            $table->dropColumn('sexe');
            $table->dropColumn('country');
        });
    }
};
