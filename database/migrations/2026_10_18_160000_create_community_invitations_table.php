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
        Schema::create('community_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique()->index();
            $table->string('recipient_name', 100)->nullable();
            $table->string('recipient_email', 150)->nullable();
            $table->string('recipient_phone', 50)->nullable(); // للتفعيل لاحقاً (SMS / WhatsApp)
            $table->text('custom_message')->nullable();
            $table->enum('status', ['sent', 'opened', 'accepted', 'rejected', 'expired', 'revoked'])
                ->default('sent')
                ->index();
            $table->dateTime('expires_at')->nullable()->index();
            $table->unsignedInteger('max_uses')->nullable()->default(1);
            $table->unsignedInteger('uses_count')->default(0);
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['community_id', 'status']);
            $table->index(['recipient_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_invitations');
    }
};
