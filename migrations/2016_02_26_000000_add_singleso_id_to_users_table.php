<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
	'up' => function (Builder $schema) {
		$schema->table('users', function (Blueprint $table) {
			$table->integer('singleso_id')->unsigned()->nullable()->unique();
			$table->index('singleso_id', 'users_singleso_id_index');
		});
	},
	'down' => function (Builder $schema) {
		$schema->table('users', function (Blueprint $table) {
			$table->dropIndex('users_singleso_id_index');
			$table->dropColumn('singleso_id');
		});
	}
];
