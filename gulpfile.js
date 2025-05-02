'use strict';
 
var gulp       = require('gulp'),
    sourcemaps = require('gulp-sourcemaps'),
    uglify     = require('gulp-uglify'),
    rename     = require('gulp-rename'),
    cssmin     = require('gulp-cssmin'),
    concat     = require('gulp-concat'),
    babel      = require('gulp-babel');

// CSS COMPRESS
gulp.task('compress-css', function () {
    return gulp.src('css/src/*.css')
        .pipe(cssmin())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest('css'));
});

// JS COMPRESS
function compress_all_js() {
    return gulp.src([
            'js/src/*.js',
            '!js/src/apbct-public--*.js',
            '!js/src/apbct-public-bundle.js',
            '!js/src/cleantalk-admin.js',
            '!js/src/apbct-common-functions.js',
            'js/src/apbct-public--3--cleantalk-modal.js',
            'js/src/apbct-public--7--trp.js',
            'js/src/apbct-public--8--browser-check.js',
        ])
        .pipe(sourcemaps.init())
        .pipe(uglify())
        .pipe(rename({suffix: '.min'}))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest('js'));
}

function bundle_admin_and_common_js() {
    return gulp.src([
        'js/src/cleantalk-admin.js',
        'js/src/apbct-common-functions.js'
    ])
    .pipe(sourcemaps.init())
    .pipe(concat('cleantalk-admin.js'))
    .pipe(uglify())
    .pipe(rename({suffix: '.min'}))
    .pipe(sourcemaps.write('.'))
    .pipe(gulp.dest('js'));
}

/**
 * Bundle Create
 */
// Bundle without external and internal js
function bundle_src_js() {
    return gulp.src([
        'js/src/apbct-public--0*.js',
        'js/src/apbct-public--1*.js',
        'js/src/apbct-public--2*.js',
        'js/src/apbct-public--3*.js',
        'js/src/apbct-public--7*.js',
        'js/src/apbct-public--8*.js',
        'js/src/apbct-common-functions.js',
    ])
        // Unminified bundle
        .pipe(concat('apbct-public-bundle.js'))
        .pipe(gulp.dest('js/src/'));
}

// Bundle with external js and without internal js
function bundle_src_js_external_protection() {
    return gulp.src([
            'js/src/apbct-public--0*.js',
            'js/src/apbct-public--1*.js',
            'js/src/apbct-public--2*.js',
            'js/src/apbct-public--3*.js',
            'js/src/apbct-public--7*.js',
            'js/src/apbct-public--8*.js',
            'js/src/apbct-public--5--external-forms.js',
            'js/src/apbct-common-functions.js',
        ])
        // Unminified bundle
        .pipe(concat('apbct-public-bundle_ext-protection.js'))
        .pipe(gulp.dest('js/src/'));
}

// Bundle with internal js and without external js 
function bundle_src_js_internal_protection() {
    return gulp.src([
            'js/src/apbct-common-functions.js',
            'js/src/apbct-public--0*.js',
            'js/src/apbct-public--1*.js',
            'js/src/apbct-public--2*.js',
            'js/src/apbct-public--3*.js',
            'js/src/apbct-public--7*.js',
            'js/src/apbct-public--8*.js',
            'js/src/apbct-public--6--internal-forms.js',
            'js/src/apbct-common-functions.js',
        ])
        // Unminified bundle
        .pipe(concat('apbct-public-bundle_int-protection.js'))
        .pipe(gulp.dest('js/src/'));
}

// Bundle with external and internal js
function bundle_src_js_ext_int_protection() {
    return gulp.src([
            'js/src/apbct-common-functions.js',
            'js/src/apbct-public--0*.js',
            'js/src/apbct-public--1*.js',
            'js/src/apbct-public--2*.js',
            'js/src/apbct-public--3*.js',
            'js/src/apbct-public--7*.js',
            'js/src/apbct-public--5--external-forms.js',
            'js/src/apbct-public--6--internal-forms.js',
            'js/src/apbct-common-functions.js',
            'js/src/apbct-public--8*.js',
        ])
        // Unminified bundle
        .pipe(concat('apbct-public-bundle_full-protection.js'))
        .pipe(gulp.dest('js/src/'));
}

function bundle_js() {
    return gulp.src('js/src/apbct-public-bundle*.js')
        .pipe(babel({
            presets: [["@babel/preset-env", { targets: { ie: "11" } }]],
            plugins: ["@babel/plugin-transform-class-properties"]
        }))
        .pipe(uglify())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest('js'));
}

gulp.task('compress-js', gulp.series(
    bundle_src_js,
    bundle_src_js_external_protection,
    bundle_src_js_internal_protection,
    bundle_src_js_ext_int_protection,
    bundle_js,
    compress_all_js,
    bundle_admin_and_common_js
));

gulp.task('compress-css:watch', function () {
    gulp.watch('./css/src/*.css', gulp.parallel('compress-css'));
});

gulp.task('compress-js:watch', function () {
    gulp.watch('./js/src/*.js', gulp.parallel('compress-js'));
});