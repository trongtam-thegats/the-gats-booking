<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hai bang chua du lieu ban hang nhap tu POS (Sapo).
 *
 * invoices     - tung hoa don, de doi chieu voi lich dat ban theo so dien thoai.
 * pos_customers - the khach hang cua POS: hang the, diem, tong chi tieu.
 *
 * Hai bang nay chi doc, khong ai sua tay trong khu quan ly; moi lan xuat tep
 * moi tu POS thi nhap de len (khop theo ma hoa don / so dien thoai).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->string('code', 40)->unique();          // Ma hoa don ben POS
            $table->string('status', 30)->nullable();      // Da thanh toan | Da huy
            $table->string('source', 40)->nullable();      // Tai nha hang, mang ve...

            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Tien Viet khong co phan le, nhung tep xuat co the co so thap phan
            // sau khi chia thue nen van de hai chu so cho chac.
            $table->decimal('subtotal', 14, 2)->default(0);      // Tong tien hang
            $table->decimal('vat', 14, 2)->default(0);
            $table->decimal('service_fee', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('delivery_fee', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);         // Tong khach tra
            $table->decimal('tip', 14, 2)->default(0);
            $table->decimal('refund', 14, 2)->default(0);

            $table->string('payment_method', 80)->nullable();

            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('membership_card', 60)->nullable();

            $table->unsignedSmallInteger('party_size')->nullable();
            $table->string('service_type', 60)->nullable();      // An tai ban, mang ve
            $table->string('area', 80)->nullable();              // Quay bar, Sofa...
            $table->string('table_code', 80)->nullable();
            $table->string('cashier')->nullable();

            $table->text('customer_note')->nullable();
            $table->text('order_note')->nullable();

            $table->timestamps();

            $table->index('customer_phone');
            $table->index(['branch_id', 'paid_at']);
        });

        Schema::create('pos_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();

            $table->string('phone', 30)->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->date('birthday')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('address')->nullable();
            $table->text('note')->nullable();

            $table->timestamp('joined_at')->nullable();

            // Con so cua POS tinh den luc xuat tep. Giu nguyen de doi chieu voi
            // con so he thong tu tinh tu bang invoices.
            $table->unsignedInteger('invoice_count')->default(0);
            $table->decimal('total_spent', 16, 2)->default(0);

            $table->string('member_code', 60)->nullable();
            $table->string('tier', 60)->nullable();
            $table->unsignedInteger('points')->default(0);

            $table->timestamp('exported_at')->nullable();  // Thoi diem xuat tep nguon
            $table->timestamps();

            $table->index('tier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_customers');
        Schema::dropIfExists('invoices');
    }
};
