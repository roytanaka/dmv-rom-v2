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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // The Member's DMV-wide standing (App\Enums\Category). A self-registered
            // person starts pre-active until an officer activates them.
            $table->string('category')->default('pre_active')->index();
            // The single explicit org-wide "all-DMV access" grant (seeded later for
            // President / VP1 / VP2). Authority is otherwise per-Group; this is the
            // one flag that reaches across the whole DMV.
            $table->boolean('super_tier')->default(false)->index();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            // Laravel's database session handler writes the authenticated id to a
            // column hard-named `user_id`; this is the framework's session-store
            // column, not a FK to members, so it keeps the framework name.
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
