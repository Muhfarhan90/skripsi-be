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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('instructor_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->double('price')->nullable();
            $table->double('discount_price')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('status')->nullable()->default('draft');
            $table->integer('total_duration')->nullable()->default(0); // in seconds
            $table->text('requirements')->nullable();
            $table->text('outcomes')->nullable();
            $table->integer('total_students')->nullable()->default(0);
            $table->double('rating')->nullable()->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
