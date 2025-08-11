'use strict';
 
var gulp       = require('gulp'),
    sourcemaps = require('gulp-sourcemaps'),
    uglify     = require('gulp-uglify'),
    rename     = require('gulp-rename'),
    cssmin     = require('gulp-cssmin'),
    concat     = require('gulp-concat'),
    babel      = require('gulp-babel');

/**
 * Minify css files
 */
gulp.task('compress-css', function () {
    return gulp.src('css/src/*.css')
        .pipe(cssmin())
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

/**
 * Bundle without external and internal js files
 */
function bundle_public_default() {
    return gulp.src([
        'js/src/common-decoder.js',
        'js/src/common-cleantalk-modal.js',
        'js/src/public-0*.js',
        'js/src/public-1*.js',
        'js/src/public-3*.js',
    ])
    .pipe(concat('apbct-public-bundle.js'))
    .pipe(gulp.dest('js/prebuild/'));
}

/**
 * Bundle without external and internal js files and add gathering
 */
function bundle_public_default_with_gathering() {
    return gulp.src([
        'js/src/common-decoder.js',
        'js/src/common-cleantalk-modal.js',
        'js/src/public-0*.js',
        'js/src/public-1*.js',
        'js/src/public-2-gathering-data.js',
        'js/src/public-3*.js',
    ])
    .pipe(concat('apbct-public-bundle_gathering.js'))
    .pipe(gulp.dest('js/prebuild/'));
}

/**
 * Bundle with external js and without internal js files
 */
function bundle_public_external_protection() {
    return gulp.src([
        'js/src/common-decoder.js',
        'js/src/common-cleantalk-modal.js',
        'js/src/public-0*.js',
        'js/src/public-1*.js',
        'js/src/public-2-external-forms.js',
        '!js/src/public-2-gathering-data.js',
        'js/src/public-3*.js',
    ])
    .pipe(concat('apbct-public-bundle_ext-protection.js'))
    .pipe(gulp.dest('js/prebuild/'));
}

/**
 * Bundle with external js and without internal js files and add gathering
 */
function bundle_public_external_protection_with_gathering() {
    return gulp.src([
        'js/src/common-decoder.js',
        'js/src/common-cleantalk-modal.js',
        'js/src/public-0*.js',
        'js/src/public-1*.js',
        'js/src/public-2-external-forms.js',
        'js/src/public-2-gathering-data.js',
        'js/src/public-3*.js',
    ])
    .pipe(concat('apbct-public-bundle_ext-protection_gathering.js'))
    .pipe(gulp.dest('js/prebuild/'));
}

/**
 * Bundle without external js and with internal js files
 */
function bundle_public_internal_protection() {
    return gulp.src([
        'js/src/common-decoder.js',
        'js/src/common-cleantalk-modal.js',
        'js/src/public-0*.js',
        'js/src/public-1*.js',
        'js/src/public-2-internal-forms.js',
        'js/src/public-3*.js',
    ])
    .pipe(concat('apbct-public-bundle_int-protection.js'))
    .pipe(gulp.dest('js/prebuild/'));
}

/**
 * Bundle without external js and with internal js files and add gathering
 */
function bundle_public_internal_protection_with_gathering() {
    return gulp.src([
        'js/src/common-decoder.js',
        'js/src/common-cleantalk-modal.js',
        'js/src/public-0*.js',
        'js/src/public-1*.js',
        'js/src/public-2-internal-forms.js',
        'js/src/public-2-gathering-data.js',
        'js/src/public-3*.js',
    ])
    .pipe(concat('apbct-public-bundle_int-protection_gathering.js'))
    .pipe(gulp.dest('js/prebuild/'));
}

/**
 * Bundle with external and internal js files
 */
function bundle_public_full_protection() {
    return gulp.src([
        'js/src/common-decoder.js',
        'js/src/common-cleantalk-modal.js',
        'js/src/public-0*.js',
        'js/src/public-1*.js',
        'js/src/public-2*.js',
        '!js/src/public-2-gathering-data.js',
        'js/src/public-3*.js',
    ])
    .pipe(concat('apbct-public-bundle_full-protection.js'))
    .pipe(gulp.dest('js/prebuild/'));
}

/**
 * Bundle with external and internal js files and add gathering
 */
function bundle_public_full_protection_with_gathering() {
    return gulp.src([
        'js/src/common-decoder.js',
        'js/src/common-cleantalk-modal.js',
        'js/src/public-0*.js',
        'js/src/public-1*.js',
        'js/src/public-2*.js',
        'js/src/public-3*.js',
    ])
    .pipe(concat('apbct-public-bundle_full-protection_gathering.js'))
    .pipe(gulp.dest('js/prebuild/'));
}

/**
 * Minify all js bundles files
 */
function minify_public_js_files() {
    return gulp.src('js/prebuild/apbct-public-bundle*.js')
        .pipe(babel({
            presets: [["@babel/preset-env", { targets: { ie: "11" } }]],
            plugins: ["@babel/plugin-transform-class-properties"]
        }))
        .pipe(uglify())
        .pipe(rename({suffix: '.min'}))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest('js'));
}

gulp.task('compress-js', gulp.series(
    bundle_public_default,
    bundle_public_default_with_gathering,
    bundle_public_external_protection,
    bundle_public_external_protection_with_gathering,
    bundle_public_internal_protection,
    bundle_public_internal_protection_with_gathering,
    bundle_public_full_protection,
    bundle_public_full_protection_with_gathering,
    minify_public_js_files,
    bundle_and_minify_admin_and_common_js,
    minify_all_js_files_except_already_bundled
));

gulp.task('compress-js:watch', function () {
    gulp.watch('./js/src/*.js', gulp.parallel('compress-js'));
});