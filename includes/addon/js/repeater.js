/**
 * jQuery Repeater
 *
 * Easily create a section of repeatable items.
 *
 * 1. Include repeater.js
 * 2. Define a template to be used by the repeater.
 *      a. Input elements should have a class "property_{i}" (do not replace {i} with an index, the script will handle this.
 *      b. The template should include a container for the "row" of elements.
 *      c. Use the {buttons} merge tag to indicate the location of the repeater buttons.
 *
 *      Example:
 *      <div class="repeater">
 *          <!-- Template Start -->
 *          <div class="row">
 *              <input class="name_{i}" />
 *              <input class="age_{i}" />
 *              {buttons}
 *          </div>
 *          <!-- / Template Ends -->
 *      </div>
 *
 * 3. Define a "save" callback to handle how your data is saved. It will give you an array of objects representing your data.
 *
 */

jQuery.fn.repeater = function( options ) {

    var self     = this,
        defaults = {
        template:       '',
        limit:          5,
        items:          [{}],
        saveEvents:     'blur change',
        saveElements:   'input, select',
        addButtonMarkup:      '+',
        removeButtonMarkup:   '-',
        callbacks: {
            save:            function() { },
            beforeAdd:       function() { },
            add:             function() { },
            beforeAddNew:    function() { },
            addNew:          function() { },
            beforeRemove:    function() { },
            remove:          function() { },
            repeaterButtons: function() { return false; }
        }
    }

    self.options   = jQuery.extend( true, {}, defaults, options );
    self.elem      = jQuery( this );
    self.items     = self.options.items;
    self.callbacks = self.options.callbacks;
    self._template = self.options.template;

    self.init = function() {

        self.stashTemplate();

        self.elem.addClass( 'repeater' );
        self.refresh();

        self.bindEvents();

        return self;
    }

    self.bindEvents = function() {

        self.options.saveEvents = self.getNamespacedEvents( self.options.saveEvents );

        self.elem.off( 'click.repeater', 'a.add-item' );
        self.elem.on( 'click.repeater', 'a.add-item:not(.inactive)', function() {
            self.addNewItem( this );
        });

        self.elem.off( 'click.repeater', 'a.remove-item' );
        self.elem.on( 'click.repeater', 'a.remove-item', function( event ){
            self.removeItem( this );
        });

        self.elem.off( self.options.saveEvents, self.options.saveElements );
        self.elem.on( self.options.saveEvents, self.options.saveElements, function() {
            self.save();
        });

    }

    self.stashTemplate = function() {

        // if no template provided or in "storage", use current HTML
        if( ! self._template )
            self._template = self.elem.html();

        // move template html into "stash"
        //jQuery( 'body' ).append( '<div id="' + self.elem.attr( 'id' ) + '-template" style="display:none">' + self._template + '</div>' );

    }

    self.getStashedTemplate = function() {
        return jQuery( self.elem.attr( 'id' ) + '-repeater-template').html();
    }

    self.addItem = function( item, index ) {

        var itemMarkup = self.getItemMarkup( item, index),
            itemElem = jQuery( itemMarkup ).addClass( 'item-' + index );

        self.callbacks.beforeAdd( self, itemElem, item );

        self.append( itemElem );
        self.populateSelects( item, index );

        self.callbacks.add( self, itemElem, item );

    }

    self.getItemMarkup = function( item, index ) {

        var itemMarkup = self._template;

        for( var property in item ) {

            if( ! item.hasOwnProperty( property ) )
                continue;

            itemMarkup = itemMarkup.replace( /{i}/g, index );
            itemMarkup = itemMarkup.replace( '{buttons}', self.getRepeaterButtonsMarkup( index ) );
            itemMarkup = itemMarkup.replace( new RegExp( '{' + property + '}', 'g' ), item[property] );

        }

        return itemMarkup;
    }

    self.getRepeaterButtonsMarkup = function( index ) {

        var buttonsMarkup = self.callbacks.repeaterButtons( self, index );

        if( ! buttonsMarkup )
            buttonsMarkup = self.getDefaultButtonsMarkup( index );

        return buttonsMarkup;
    }

    self.getDefaultButtonsMarkup = function( index ) {

        var cssClass = self.items.length >= self.options.limit && self.options.limit !== 0 ? 'inactive' : '',
            buttons = '<a class="add-item ' + cssClass + '" data-index="' + index + '">' + self.options.addButtonMarkup + '</a>';

        if( self.items.length > 1 )
            buttons += '<a class="remove-item" data-index="' + index + '">' + self.options.removeButtonMarkup + '</a>';

        return '<div class="repeater-buttons">' +  buttons + '</div>';
    }

    self.populateSelects = function( item, index ) {

        // after appending the row, check each property to see if it is a select and then populate
        for( var property in item ) {

            if( ! item.hasOwnProperty( property ) )
                continue;

            var input = self.elem.find( '.' + property + '_' + index );
            if( input.is( 'select' ) ){
                if(jQuery.isArray(item[property])){
                    input.val(item[property]);
                } else {
                    input.find( 'option[value="' + item[property] + '"]' ).prop( 'selected', true );
                }
            }
        }

    }

    self.addNewItem = function( elem ) {

        var index = jQuery( elem ).attr( 'data-index' );
        index = parseInt( index );
        self.callbacks.beforeAddNew( this, index );
        self.items.splice( index + 1, 0, self.getBaseObject() );
        self.callbacks.addNew( this, index );

        self.refresh();

        return self;
    }

    self.removeItem = function( elem ) {

        var index = jQuery( elem ).attr( 'data-index' );

        self.callbacks.beforeRemove( self, index );

        // using delete (over splice) to maintain the correct indexes for
        // the items array when saving the data from the UI
        delete self.items[index];

        self.callbacks.remove( self, index );

        self.save().refresh();

    }

    self.refresh = function() {

        self.elem.empty();

        for( var i = 0; i < self.items.length; i++ ) {
            self.addItem( self.items[i], i );
        }

        return self;
    }

    self.save = function() {

        var keys = self.getBaseObjectKeys(),
            data = [];

        for( var i = 0; i < self.items.length; i++ ) {

            if( typeof self.items[i] == 'undefined' )
                continue;

            var item = {};

            for( var j = 0; j < keys.length; j++ ) {

                var key = keys[j],
                    id = '.' + key + '_' + i,
                    value = self.elem.find( id ).val();

                item[key] = typeof value == 'undefined' ? false : value;

            }

            data.push( item );

        }

        // save data to items
        self.items = data;

        // save data externally via callback
        self.callbacks.save( self, data );

        return self;
    }

    /**
     * Loops through the current items array and retrieves the object properties of the
     * first valid item object. Originally this would simply pull the object keys from
     * the first index of the items array; however, when the first item has been
     * 'deleted' (see the save() method), it will be undefined.
     */
    self.getBaseObjectKeys = function() {

        var keys = [];

        for( var i = 0; i < self.items.length; i++ ) {

            if( typeof self.items[i] == 'undefined' )
                continue;

            for( var key in self.items[i] ) {
                if( ! self.items[i].hasOwnProperty( key ) )
                    continue;
                keys.push( key );
            }

            break;
        }

        return keys;
    }

    self.getBaseObject = function() {

        var item = {},
            keys = self.getBaseObjectKeys();

        for( var i = 0; i < keys.length; i++ ) {
            item[keys[i]] = '';
        }

        return item;
    }

    self.getNamespacedEvents = function( events ) {

        var events = events.split( ' ' ),
            namespacedEvents = [];

        for( var i = 0; i < events.length; i++ ) {
            namespacedEvents.push( events[i] + '.repeater' );
        }

        return namespacedEvents.join( ' ' );
    }

    return self.init();
};