<?php

use App\Support\SavedQueries\SavedQueryFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved queries (#923, ADR-0038 §7): a named filter over Member data, stored as structure, never
 * as SQL. `clauses` is a list of {source, field, operator, value}; `output_columns` a list of
 * allowlisted column keys. {@see SavedQueryFields}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_queries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('clauses');
            $table->string('conjunction', 3)->default('and');
            $table->string('sort', 64);
            $table->json('output_columns');
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_queries');
    }
};
