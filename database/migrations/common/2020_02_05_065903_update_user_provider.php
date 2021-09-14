<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateUserProvider extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('api_response')->after('company_id')->nullable();
            $table->text('account_id')->after('api_response')->nullable();
            $table->text('client_id')->after('account_id')->nullable();
            $table->text('resource_id')->after('client_id')->nullable();
            $table->text('savingsaccount_id')->after('resource_id')->nullable();
        });
        Schema::table('providers', function (Blueprint $table) {
            $table->text('account_id')->after('api_response')->nullable();
            $table->text('client_id')->after('account_id')->nullable();
            $table->text('resource_id')->after('client_id')->nullable();
            $table->text('savingsaccount_id')->after('resource_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('api_response');
            $table->dropColumn('account_id');
            $table->dropColumn('client_id');
            $table->dropColumn('resource_id');
        });
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('account_id');
            $table->dropColumn('client_id');
            $table->dropColumn('resource_id');
        });
    }
}
