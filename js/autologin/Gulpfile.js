var gulp = require('gulp');
var uglify = require('gulp-uglify')

gulp.task('build', function() {
	gulp.src('src/*.js')
		.pipe(uglify())
		.pipe(gulp.dest('dist'));
});

gulp.task('build-watch', function() {
	gulp.watch('src/*.js', ['build']);
});

gulp.task('default', ['build']);
gulp.task('watch', ['build', 'build-watch']);
