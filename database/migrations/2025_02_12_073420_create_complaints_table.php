<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id(); // BIGINT by default in MySQL
            $table->string('invoice_no', 255); // Changed from invoice_id
            $table->unsignedBigInteger('product_id');
            $table->string('product_name', 255);
            $table->integer('quantity');
            $table->string('issue_type', 255);
            $table->string('customer_phone', 20);
            $table->text('remark')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('complain_date');
            $table->unsignedBigInteger('owner_id');
            $table->text('admin_response')->nullable();
            $table->string('warehouse_branch', 50)->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('owner_id')->references('partner_shops_id')->on('partner_shops')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('complaints');
    }
};