'use strict';

var gulp       = require('gulp'),
    sourcemaps = require('gulp-sourcemaps'),
    uglify     = require('gulp-uglify'),
    rename     = require('gulp-rename'),
    cleanCSS   = require('gulp-clean-css'),
    concat     = require('gulp-concat');

/**
 * Minify css files
 */
gulp.task('compress-css', function () {
    return gulp.src('css/src/*.css')
        .pipe(cleanCSS())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest('css'));
});

/**
 * Watch css files
 */
gulp.task('compress-css:watch', function () {
    gulp.watch('./css/src/*.css', gulp.parallel('compress-css'));
});

/**
 * Minify all js files except bundled
 */
function minify_all_js_files_except_already_bundled() {
    return gulp.src([
            'js/src/*.js',
            '!js/src/public*.js',
            '!js/src/cleantalk-admin.js',
            '!js/src/common-decoder.js',
        ])
        .pipe(sourcemaps.init())
        .pipe(uglify())
        .pipe(rename({suffix: '.min'}))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest('js'));
}

/**
 * Bundle and minify admin and decoder js files
 */
function bundle_and_minify_admin_and_common_js() {
    return gulp.src([
        'js/src/cleantalk-admin.js',
        'js/src/common-decoder.js'
    ])
    .pipe(sourcemaps.init())
    .pipe(concat('cleantalk-admin.js'))
    .pipe(uglify())
    .pipe(rename({suffix: '.min'}))
    .pipe(sourcemaps.write('.'))
    .pipe(gulp.dest('js'));
}

gulp.task('compress-js', gulp.series(
    bundle_and_minify_admin_and_common_js,
    minify_all_js_files_except_already_bundled
));

gulp.task('compress-js:watch', function () {
    gulp.watch('./js/src/*.js', gulp.parallel('compress-js'));
});
