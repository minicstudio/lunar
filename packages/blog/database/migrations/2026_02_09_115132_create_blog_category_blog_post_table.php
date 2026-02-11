<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create($this->prefix."blog_category_blog_post", function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained($this->prefix."blog_posts")->cascadeOnDelete();
            $table->foreignId('blog_category_id')->constrained($this->prefix."blog_categories")->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix."blog_category_blog_post");
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix."blog_category_blog_post");
    }
};
