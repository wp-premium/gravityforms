Gravity Forms
==============================

[![Build Status](https://travis-ci.com/gravityforms/gravityforms.svg?token=dWdigWFPjUjwVzDjbyxv&branch=master)](https://travis-ci.com/gravityforms/gravityforms)

This repository contains the development version of Gravity Forms intended to facilitate communication with developers. It is not stable and not intended for installation on production sites.

## Installation Instructions
The only thing you need to do to get this development version working is clone this repository into your plugins directory and activate script debug mode. If you try to use this version without script mode on the scripts and styles will not load and it will not work properly.

To enable script debug mode just add the following line to your wp-config.php file:

define( 'SCRIPT_DEBUG', true );


## Unit Tests

The unit tests can be installed from the terminal using:

    bash tests/bin/install.sh [DB_NAME] [DB_USER] [DB_PASSWORD] [DB_HOST]


If you're using VVV you can use this command:

	bash tests/bin/install.sh wordpress_unit_tests root root localhost

