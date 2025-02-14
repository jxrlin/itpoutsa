<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id(); // BIGINT by default in MySQL
            $table->string('driver_name', 255);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->string('phone_number', 20);
            $table->string('license_number', 50);
            $table->string('vehicle_type', 50)->nullable();
            $table->string('vehicle_plate_number', 20)->nullable();
            $table->enum('status', ['Available', 'On Delivery', 'Offline'])->default('Available');
            $table->integer('assigned_orders_count')->default(0);
            $table->dateTime('last_delivery_time')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->dateTime('hire_date')->useCurrent();
            $table->string('profile_picture', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('deliveries');
    }
};