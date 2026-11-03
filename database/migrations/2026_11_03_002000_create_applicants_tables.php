<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Applicant record (#928, ADR-0038 §8): a person who applied to volunteer, kept apart from
     * Members until Accept links the Member they became. Identity and contact columns carry the
     * Member record's names, so Accept copies them one for one. `applicant_languages` has the shape
     * of `member_languages`.
     */
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('alternate_phone')->nullable();
            $table->string('business_phone')->nullable();
            $table->string('address_street')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_province')->nullable();
            $table->string('address_postal_code')->nullable();
            $table->string('address_country')->nullable();
            $table->string('membership_number')->nullable();
            $table->date('membership_expiry')->nullable();
            $table->boolean('membership_lifetime')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_contact_number')->nullable();
            $table->string('status')->index();
            $table->date('status_date')->nullable();
            $table->string('departure_reason')->nullable();
            $table->foreignId('placement_group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('mentor_id')->nullable()->constrained('members')->nullOnDelete();
            $table->boolean('entered_by_applicant')->default(false);
            $table->date('applied_on');
            $table->unsignedSmallInteger('entry_year')->nullable();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('volunteer_experience')->nullable();
            $table->text('past_experience')->nullable();
            $table->text('education')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();

            $table->index('last_name');
            $table->index('first_name');
            $table->index('status_date');
        });

        Schema::create('applicant_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('language_code', 64);
            $table->boolean('speaks')->default(false);
            $table->boolean('reads')->default(false);
            $table->boolean('writes')->default(false);
            $table->timestamps();

            $table->unique(['applicant_id', 'language_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_languages');
        Schema::dropIfExists('applicants');
    }
};
