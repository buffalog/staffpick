<?php

use App\Constants\PaymentProviderPlanPriceType;
use App\Constants\PlanPriceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_meters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->foreignId('meter_id')->nullable()->constrained('plan_meters');
        });

        Schema::table('plan_prices', function (Blueprint $table) {
            $table->unsignedInteger('price_per_unit')->nullable();
            $table->string('type')->default(PlanPriceType::FLAT_RATE->value);
            $table->json('tiers')->nullable();
        });

        Schema::table('plan_price_payment_provider_data', function (Blueprint $table) {
            $table->string('type')->default(PaymentProviderPlanPriceType::MAIN_PRICE->value);

            if (in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
                $table->unique(['plan_price_id', 'payment_provider_id', 'type'], 'plan_price_payment_provider_type_data_unq');
                $table->dropIndex('plan_price_payment_provider_data_unq');
            } else {
                $table->dropUnique('plan_price_payment_provider_data_unq');
                $table->unique(['plan_price_id', 'payment_provider_id', 'type'], 'plan_price_payment_provider_type_data_unq');
            }
        });

        Schema::create('plan_meter_payment_provider_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_meter_id')->constrained('plan_meters');
            $table->foreignId('payment_provider_id')->constrained();
            $table->string('payment_provider_plan_meter_id')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('price_type')->default(PlanPriceType::FLAT_RATE->value);
            $table->json('price_tiers')->nullable();
            $table->string('price_per_unit')->nullable();
            $table->json('extra_payment_provider_data')->nullable();
        });

        Schema::create('subscription_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->integer('unit_count');
            $table->timestamps();
        });

        // SQL Server cascade cycle: user_stripe_data already has user_id FK.
        // Store tenant_id as plain integer to avoid multiple cascade paths.
        Schema::table('user_stripe_data', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_stripe_data', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });

        Schema::dropIfExists('subscription_usages');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('price_type');
            $table->dropColumn('price_tiers');
            $table->dropColumn('price_per_unit');
            $table->dropColumn('extra_payment_provider_data');
        });

        Schema::dropIfExists('plan_meter_payment_provider_data');

        Schema::table('plan_price_payment_provider_data', function (Blueprint $table) {
            $table->unique(['plan_price_id', 'payment_provider_id'], 'plan_price_payment_provider_data_unq');

            if (config('database.default') === 'mysql') {
                $table->dropColumn('type');
                $table->dropUnique('plan_price_payment_provider_type_data_unq');
            } else {
                $table->dropUnique('plan_price_payment_provider_type_data_unq');
                $table->dropColumn('type');
            }
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropForeign(['meter_id']);
            $table->dropColumn('meter_id');
        });

        Schema::table('plan_prices', function (Blueprint $table) {
            $table->dropColumn('price_per_unit');
            $table->dropColumn('type');
            $table->dropColumn('tiers');
        });

        Schema::dropIfExists('plan_meters');
    }
};
