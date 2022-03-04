<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workflow_logs', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('stored_workflow_id')->index();
            $table->integer('index')->nullable();
            $table->text('result')->nullable();
            $table->timestamps();

            $table->index(['stored_workflow_id', 'index']);

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
        Schema::dropIfExists('workflow_logs');
    }
}
