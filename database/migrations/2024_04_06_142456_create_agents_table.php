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
        Schema::create('agents', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('registration_number')->nullable();
            $table->string('agent_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('national_identity_card_number')->nullable();
            $table->string('location_plan_file')->nullable();
            $table->string('identity_card_file')->nullable();
            $table->string('photo')->nullable();

            $table->foreignId('caution_id')->nullable()->constrained('cautions')->onDelete('cascade');

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
        Schema::dropIfExists('agents');
    }
};
