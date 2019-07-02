/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = "./js/src/index.js");
/******/ })
/************************************************************************/
/******/ ({

/***/ "./js/src/blocks/form/block.scss":
/*!***************************************!*\
  !*** ./js/src/blocks/form/block.scss ***!
  \***************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin

/***/ }),

/***/ "./js/src/blocks/form/edit.js":
/*!************************************!*\
  !*** ./js/src/blocks/form/edit.js ***!
  \************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _block_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./block.scss */ "./js/src/blocks/form/block.scss");
/* harmony import */ var _block_scss__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_block_scss__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _icon__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./icon */ "./js/src/blocks/form/icon.js");
/**
 * WordPress dependencies
 */
const { PanelBody, Placeholder, SelectControl, ServerSideRender, TextControl, ToggleControl } = wp.components;
const { InspectorControls } = wp.editor;
const { Component, Fragment } = wp.element;
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */



class Edit extends Component {

	constructor() {

		super(...arguments);

		// Set initial state.
		this.state = { formWasDeleted: false };

		// Bind events.
		this.setFormId = this.setFormId.bind(this);

		// Get defined form ID.
		const { formId } = this.props.attributes;

		// If form has been selected, disable preview / reset.
		if (formId) {

			// Get form object.
			const form = Edit.getForm(formId);

			// If form was not found, reset block.
			if (!form) {

				// Reset form ID.
				this.props.setAttributes({ formId: '' });

				// Set failed state.
				this.state = { formWasDeleted: true };

				// If form was found and has conditional logic, disable preview.
			} else if (form && form.hasConditionalLogic) {
				this.props.setAttributes({ formPreview: false });
			}
		}
	}

	componentWillUnmount() {

		this.unmounting = true;
	}

	setFormId(formId) {

		let form = Edit.getForm(formId);

		this.props.setAttributes({ formId });
		this.setState({ formWasDeleted: false });

		if (form && form.hasConditionalLogic) {
			this.props.setAttributes({ formPreview: false });
		}
	}

	static getForm(formId) {

		return gform_block_form.forms.find(form => form.id == formId);
	}

	static getFormOptions() {

		let options = [{
			label: __('Select a Form', 'gravityforms'),
			value: ''
		}];

		for (let i = 0; i < gform_block_form.forms.length; i++) {

			let form = gform_block_form.forms[i];

			options.push({
				label: form.title,
				value: form.id
			});
		}

		return options;
	}

	render() {

		let { formId, title, description, ajax, tabindex, formPreview } = this.props.attributes;

		const { setAttributes, isSelected } = this.props;

		const toggleTitle = () => setAttributes({ title: !title });
		const toggleDescription = () => setAttributes({ description: !description });
		const toggleAjax = () => setAttributes({ ajax: !ajax });
		const toggleFormPreview = () => setAttributes({ formPreview: !formPreview });

		const updateTabindex = tabindex => setAttributes({ tabindex });

		const setFormIdFromPlaceholder = e => this.setFormId(e.target.value);

		const controls = [isSelected && React.createElement(
			InspectorControls,
			{ key: 'inspector' },
			React.createElement(
				PanelBody,
				{
					title: __('Form Settings', 'gravityforms')
				},
				React.createElement(SelectControl, {
					label: __('Form', 'gravityforms'),
					value: formId,
					options: Edit.getFormOptions(),
					onChange: this.setFormId
				}),
				formId && React.createElement(ToggleControl, {
					label: __('Form Title', 'gravityforms'),
					checked: title,
					onChange: toggleTitle
				}),
				formId && React.createElement(ToggleControl, {
					label: __('Form Description', 'gravityforms'),
					checked: description,
					onChange: toggleDescription
				})
			),
			formId && React.createElement(
				PanelBody,
				{
					title: __('Advanced', 'gravityforms'),
					initialOpen: false,
					className: 'gform-block__panel'
				},
				formId && !Edit.getForm(formId).hasConditionalLogic && React.createElement(ToggleControl, {
					label: __('Preview', 'gravityforms'),
					checked: formPreview,
					onChange: toggleFormPreview
				}),
				React.createElement(ToggleControl, {
					label: __('AJAX', 'gravityforms'),
					checked: ajax,
					onChange: toggleAjax
				}),
				React.createElement(TextControl, {
					className: 'gform-block__tabindex',
					label: __('Tabindex', 'gravityforms'),
					type: 'number',
					value: tabindex,
					onChange: updateTabindex,
					placeholder: '-1'
				}),
				React.createElement(
					Fragment,
					null,
					'Form ID: ',
					formId
				)
			)
		)];

		if (!formId || !formPreview) {

			const { formWasDeleted } = this.state;

			return [controls, formWasDeleted && React.createElement(
				'div',
				{ className: 'gform-block__alert gform-block__alert-error' },
				React.createElement(
					'p',
					null,
					__('The selected form has been deleted or trashed. Please select a new form.', 'gravityforms')
				)
			), React.createElement(
				Placeholder,
				{ key: 'placeholder', className: 'wp-block-embed gform-block__placeholder' },
				React.createElement(
					'div',
					{ className: 'gform-block__placeholder-brand' },
					React.createElement(
						'div',
						{ className: 'gform-icon' },
						_icon__WEBPACK_IMPORTED_MODULE_1__["default"]
					),
					React.createElement(
						'p',
						null,
						React.createElement(
							'strong',
							null,
							'Gravity Forms'
						)
					)
				),
				React.createElement(
					'form',
					null,
					React.createElement(
						'select',
						{ value: formId, onChange: setFormIdFromPlaceholder },
						Edit.getFormOptions().map(form => React.createElement(
							'option',
							{ key: form.value, value: form.value },
							form.label
						))
					)
				)
			)];
		}

		return [controls, React.createElement(ServerSideRender, {
			key: 'form_preview',
			block: 'gravityforms/form',
			attributes: this.props.attributes
		})];
	}

}

/* harmony default export */ __webpack_exports__["default"] = (Edit);

/***/ }),

/***/ "./js/src/blocks/form/icon.js":
/*!************************************!*\
  !*** ./js/src/blocks/form/icon.js ***!
  \************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
const icon = React.createElement(
	'svg',
	{ xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 508.3 559.5', width: '100%', height: '100%',
		focusable: 'false', 'aria-hidden': 'true',
		className: 'dashicon dashicon-gravityforms' },
	React.createElement(
		'g',
		null,
		React.createElement('path', { className: 'st0',
			d: 'M468,109.8L294.4,9.6c-22.1-12.8-58.4-12.8-80.5,0L40.3,109.8C18.2,122.6,0,154,0,179.5V380\tc0,25.6,18.1,56.9,40.3,69.7l173.6,100.2c22.1,12.8,58.4,12.8,80.5,0L468,449.8c22.2-12.8,40.3-44.2,40.3-69.7V179.6\tC508.3,154,490.2,122.6,468,109.8z M399.3,244.4l-195.1,0c-11,0-19.2,3.2-25.6,10c-14.2,15.1-18.2,44.4-19.3,60.7H348v-26.4h49.9\tv76.3H111.3l-1.8-23c-0.3-3.3-5.9-80.7,32.8-121.9c16.1-17.1,37.1-25.8,62.4-25.8h194.7V244.4z'
		})
	)
);

/* harmony default export */ __webpack_exports__["default"] = (icon);

/***/ }),

/***/ "./js/src/blocks/form/index.js":
/*!*************************************!*\
  !*** ./js/src/blocks/form/index.js ***!
  \*************************************/
/*! no exports provided */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./edit */ "./js/src/blocks/form/edit.js");
/* harmony import */ var _icon__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./icon */ "./js/src/blocks/form/icon.js");
/**
 * WordPress dependencies
 */
const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;

/**
 * Internal dependencies
 */



registerBlockType('gravityforms/form', {

	title: __('Form', 'gravityforms'),
	description: __('Select a form below to add it to your page.', 'gravityforms'),
	category: 'embed',
	supports: {
		customClassName: false,
		className: false,
		html: false
	},
	keywords: ['gravity forms', 'newsletter', 'contact'],
	attributes: {
		formId: {
			type: 'string'
		},
		title: {
			type: 'bool',
			default: true
		},
		description: {
			type: 'bool',
			default: true
		},
		ajax: {
			type: 'bool',
			default: false
		},
		tabindex: {
			type: 'string'
		},
		formPreview: {
			type: 'bool',
			default: true
		}
	},
	icon: _icon__WEBPACK_IMPORTED_MODULE_1__["default"],

	transforms: {
		from: [{
			type: 'shortcode',
			tag: ['gravityform', 'gravityforms'],
			attributes: {
				formId: {
					type: 'string',
					shortcode: ({ named: { id } }) => {
						return parseInt(id).toString();
					}
				},
				title: {
					type: 'bool',
					shortcode: ({ named: { title } }) => {
						return 'true' === title;
					}
				},
				description: {
					type: 'bool',
					shortcode: ({ named: { description } }) => {
						return 'true' === description;
					}
				},
				ajax: {
					type: 'bool',
					shortcode: ({ named: { ajax } }) => {
						return 'true' === ajax;
					}
				},
				tabindex: {
					type: 'string',
					shortcode: ({ named: { tabindex } }) => {
						return isNaN(tabindex) ? null : parseInt(tabindex).toString();
					}
				}
			}
		}]
	},

	edit: _edit__WEBPACK_IMPORTED_MODULE_0__["default"],

	save() {
		return null;
	}

});

/***/ }),

/***/ "./js/src/index.js":
/*!*************************!*\
  !*** ./js/src/index.js ***!
  \*************************/
/*! no exports provided */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _blocks_form_index_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./blocks/form/index.js */ "./js/src/blocks/form/index.js");


/***/ })

/******/ });
//# sourceMappingURL=blocks.js.map