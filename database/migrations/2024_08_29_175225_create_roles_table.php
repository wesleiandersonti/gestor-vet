<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Adicione esta linha para usar o DB facade

class CreateRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Inserir os registros 'admin', 'master' e 'cliente' na tabela 'roles'
        DB::table('roles')->insert([
            [
                'name' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'master',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'cliente',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role_id')) {
            $foreignExists = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', 'users')
                ->where('COLUMN_NAME', 'role_id')
                ->where('REFERENCED_TABLE_NAME', 'roles')
                ->exists();

            if (!$foreignExists) {
                Schema::table('users', function (Blueprint $table) {
                    $table->foreign('role_id')->references('id')->on('roles');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('users')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropForeign(['role_id']);
                });
            } catch (\Throwable $e) {
                // no-op
            }
        }

        Schema::dropIfExists('roles');
    }
}
