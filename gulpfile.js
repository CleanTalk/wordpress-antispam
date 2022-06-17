'use strict';
 
var gulp       = require('gulp'),
    sourcemaps = require('gulp-sourcemaps'),
    uglify     = require('gulp-uglify'),
    rename     = require('gulp-rename'),
    cssmin     = require('gulp-cssmin'),
    concat     = require('gulp-concat'),
    wait       = require('gulp-wait');

// CSS COMPRESS
gulp.task('compress-css', function () {
    return gulp.src('css/src/*.css')
        .pipe(cssmin())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest('css'));
});

// JS COMPRESS
gulp.task('compress-all-js', function (cb) {
    gulp.src('js/src/*.js')
        .pipe(wait(2000))
        .pipe(sourcemaps.init())
        .pipe(uglify())
        .pipe(rename({suffix: '.min'}))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest('js'));
    cb();
});

// Bundle Create
gulp.task('bundle-js', function (cb) {
    gulp.src(
        [
            'js/bundle/apbct-public--functions.js',
            'js/bundle/apbct-public.js',
            'js/bundle/cleantalk-modal.js',
        ]
    )
        .pipe(concat('apbct-public--functions.js'))
        .pipe(gulp.dest('js/src'))
        .pipe(uglify())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest('js'));
    cb();
});

gulp.task('compress-js', gulp.series('bundle-js', 'compress-all-js'));

gulp.task('compress-css:watch', function () {
    gulp.watch('./css/src/*.css', gulp.parallel('compress-css'));
});

gulp.task('compress-js:watch', function () {
    gulp.watch('./js/src/*.js', gulp.parallel('compress-js'));
});