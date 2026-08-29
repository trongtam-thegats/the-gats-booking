<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 20);   // email | sms | zalo
            $table->string('event', 30);     // created | confirmed | cancelled | reminder
            $table->string('recipient')->nullable();
            $table->string('status', 20);    // sent | skipped | failed
            $table->text('message')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
