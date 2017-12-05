'use strict';
module.exports = function(grunt) {

	// load all tasks
	require('load-grunt-tasks')(grunt, {scope: 'devDependencies'});

	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),
		// https://www.npmjs.org/package/grunt-wp-i18n
		makepot: {
			target: {
				options: {
					domainPath: '/languages/',
					potFilename: 'woocommerce-new-customer-coupons.pot',
					potHeaders: {
					poedit: true, // Includes common Poedit headers.
					'x-poedit-keywordslist': true // Include a list of all possible gettext functions.
				},
				type: 'wp-plugin',
				updateTimestamp: false,
				processPot: function( pot, options ) {
					pot.headers['report-msgid-bugs-to'] = 'https://devpress.com/';
					pot.headers['language'] = 'en_US';
					return pot;
					}
				}
			}
		},
		replace: {
			version: {
				src: [
					'woocommerce-coupon-restrictions.php'
				],
				overwrite: true,
				replacements: [
					{
						from: /Version:.*$/m,
						to: 'Version: <%= pkg.version %>'
					},
				]
			},
			readme: {
				src: [
					'README.TXT'
				],
				overwrite: true,
				replacements: [{
					from: /Stable tag:.*$/m,
					to: 'Stable tag: <%= pkg.version %>'
				}]
			},
		}
	});

	grunt.registerTask( 'default', [
		'makepot',
		'replace',
	]);

};
