var flarum = require('flarum-gulp');

flarum({
	modules: {
		'singleso/singleso-flarum': [
			'src/**/*.js'
		]
	}
});
