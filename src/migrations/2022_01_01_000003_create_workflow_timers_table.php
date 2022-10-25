<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowTimersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workflow_timers', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('stored_workflow_id')->index();
            $table->integer('index')->nullable();
            $table->timestamp('stop_at');
            $table->timestamps();

            $table->index(['stored_workflow_id', 'created_at']);

            $table->foreign('stored_workflow_id')->references('id')->on('workflows')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workflow_timers');
    }
}
