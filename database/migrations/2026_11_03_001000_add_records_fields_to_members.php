<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fields the Records office keeps on a Member record (#917, ADR-0038 §3), and the two
     * reference tables behind them, seeded here with the Legacy app's rows so they exist
     * wherever the schema does. Email becomes nullable: the unique index stays, and it admits
     * many nulls.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('email')->nullable()->change();

            $table->string('membership_number')->nullable();
            $table->date('membership_expiry')->nullable();
            $table->boolean('membership_lifetime')->nullable();
            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->unsignedSmallInteger('paid_through_year')->nullable();
            $table->string('fee_category')->nullable();
            $table->unsignedSmallInteger('entry_year')->nullable();
            $table->json('service_awards')->nullable();
            $table->unsignedSmallInteger('last_award_year')->nullable();
            $table->unsignedSmallInteger('next_award_year')->nullable();
            $table->string('other_awards')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_contact_number')->nullable();
            $table->boolean('newsletter_opt_in')->nullable();
            $table->date('status_date')->nullable();
            $table->unsignedSmallInteger('code_of_conduct_year')->nullable();
            $table->unsignedSmallInteger('renewal_year')->nullable();
            $table->string('renewal_status')->nullable();
            $table->text('renewal_placements')->nullable();
            $table->text('renewal_other')->nullable();
            $table->text('record_form_placements')->nullable();
            $table->text('record_form_other')->nullable();
            $table->string('mentor_name')->nullable();
            $table->text('volunteer_experience')->nullable();
            $table->text('past_experience')->nullable();
            $table->text('education')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_sign_in_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('members')->nullOnDelete();
        });

        Schema::create('fee_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('extra')->nullable();
            $table->decimal('amount', 8, 2);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_awards', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('years');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $board = 'with DMV Board approval';

        DB::table('fee_categories')->insert([
            ['code' => 'Active', 'name' => 'Active', 'extra' => null, 'amount' => 25, 'active' => true],
            ['code' => 'Active-SR', 'name' => 'Active Senior 65+', 'extra' => null, 'amount' => 25, 'active' => true],
            ['code' => 'Active-ST', 'name' => 'Active Student', 'extra' => null, 'amount' => 20, 'active' => true],
            ['code' => 'Sust', 'name' => 'Sustaining', 'extra' => $board, 'amount' => 25, 'active' => true],
            ['code' => 'Sust-SR', 'name' => 'Sustaining Senior 65+', 'extra' => $board, 'amount' => 25, 'active' => true],
            ['code' => 'LOA', 'name' => 'Leave of Absence', 'extra' => $board, 'amount' => 0, 'active' => true],
            ['code' => 'Hon', 'name' => 'Honorary', 'extra' => $board, 'amount' => 0, 'active' => false],
        ]);

        DB::table('service_awards')->insert(array_map(
            fn (int $years) => ['code' => "{$years} yr", 'name' => "{$years} years", 'years' => $years, 'active' => true],
            range(5, 60, 5),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('service_awards');
        Schema::dropIfExists('fee_categories');

        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'membership_number', 'membership_expiry', 'membership_lifetime', 'amount_paid',
                'paid_through_year', 'fee_category', 'entry_year', 'service_awards', 'last_award_year',
                'next_award_year', 'other_awards', 'emergency_contact', 'emergency_contact_number',
                'newsletter_opt_in', 'status_date', 'code_of_conduct_year', 'renewal_year',
                'renewal_status', 'renewal_placements', 'renewal_other', 'record_form_placements',
                'record_form_other', 'mentor_name', 'volunteer_experience', 'past_experience',
                'education', 'notes', 'last_sign_in_at',
            ]);
            $table->string('email')->nullable(false)->change();
        });
    }
};
