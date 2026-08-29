<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();          // TG8KD3F2
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            $table->string('customer_name');
            $table->string('customer_phone', 30);
            $table->string('customer_email')->nullable();

            $table->unsignedSmallInteger('party_size');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete(); // khu vuc khach mong muon

            // pending | confirmed | seated | completed | cancelled | no_show
            $table->string('status', 20)->default('pending');
            $table->string('source', 20)->default('online'); // online | phone | walk_in

            $table->text('note')->nullable();            // ghi chu cua khach
            $table->text('internal_note')->nullable();   // ghi chu noi bo

            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->string('cancelled_by_type', 20)->nullable(); // customer | staff
            $table->timestamp('seated_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'booking_date', 'status']);
            $table->index('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
