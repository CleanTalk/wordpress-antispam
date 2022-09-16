'use strict';
 
var gulp       = require('gulp'),
    sourcemaps = require('gulp-sourcemaps'),
    uglify     = require('gulp-uglify'),
    rename     = require('gulp-rename'),
    cssmin     = require('gulp-cssmin'),
    concat     = require('gulp-concat');

// CSS COMPRESS
gulp.task('compress-css', function () {
    return gulp.src('css/src/*.css')
        .pipe(cssmin())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest('css'));
});

// JS COMPRESS
async function compress_all_js() {
    await gulp.src(['js/src/*.js', '!js/src/apbct-public--*.js', 'js/src/apbct-public--3--cleantalk-modal.js'])
        .pipe(sourcemaps.init())
        .pipe(uglify())
        .pipe(rename({suffix: '.min'}))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest('js'));
}

// Bundle Create
async function bundle_js() {
    await gulp.src('js/src/apbct-public--*.js')

        // Unminified bundle
        .pipe(concat('apbct-public-bundle.js'))
        .pipe(gulp.dest('js/src/'))

        // Minifying
        .pipe(concat('apbct-public-bundle.js'))
        .pipe(uglify())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest('js'));
}

gulp.task('compress-js', gulp.series(bundle_js, compress_all_js));

gulp.task('compress-css:watch', function () {
    gulp.watch('./css/src/*.css', gulp.parallel('compress-css'));
});

gulp.task('compress-js:watch', function () {
    gulp.watch('./js/src/*.js', gulp.parallel('compress-js'));
});