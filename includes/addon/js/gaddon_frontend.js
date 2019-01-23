
var GFFrontendFeeds = function( args ) {

	var self = this,
		$    = jQuery;

	/**
	 * Initialize Feed Ordering
	 */
	self.init = function() {

		// Assign options to instance.
		self.options = args;

		self.triggerInputIds = self.getTriggerInputIds( self.options.feeds );

		self.activeFeeds = [];
		
		self.evaluateFeeds();

		self.bindEvents();

	};

	self.bindEvents = function() {

		gform.addAction( 'gform_input_change', function( elem, formId, inputId ) {

			var fieldId = parseInt( inputId ) + '';
			var isTriggeredInput = $.inArray( inputId, self.triggerInputIds ) !== -1 || $.inArray( fieldId , self.triggerInputIds ) !== -1 ;

			if( self.options.formId == formId && isTriggeredInput ) {
				self.evaluateFeeds();
			}
		} );

	};

	self.evaluateFeeds = function() {

		var feed, isMatch, isActivated;

		for( i = 0; i < self.options.feeds.length; i++ ) {

			feed        = self.options.feeds[ i ];
			isMatch     = self.evaluateFeed( feed, self.options.formId );
			isActivated = self.isFeedActivated( feed );

			if( ! isMatch && isActivated !== null ) {
				self.deactivateFeed( feed );
			} else if( isMatch && ! isActivated && ( ! feed.isSingleFeed || ( feed.isSingleFeed && self.hasPriority( feed.feedId, feed.addonSlug ) ) ) ) {
				self.activateFeed( feed );
			}

		}

		/**
		 * Fires after the conditional logic on the form has been evaluated.
		 *
		 * @since 2.4
		 *
		 * @param array $feeds     A collection of feed objects.
		 * @param int   $formId    The form id.
		 */
		gform.doAction( 'gform_frontend_feeds_evaluated', self.options.feeds, self.options.formId, self );
		gform.doAction( 'gform_frontend_feeds_evaluated_{0}'.format( self.options.formId ), self.options.feeds, self.options.formId, self );
		gform.doAction( 'gform_{0}_frontend_feeds_evaluated'.format( feed.addonSlug ), self.options.feeds, self.options.formId, self );
		gform.doAction( 'gform_{0}_frontend_feeds_evaluated_{0}'.format( feed.addonSlug, self.options.formId ), self.options.feeds, self.options.formId, self );

	};

	self.evaluateFeed = function( feed, formId ) {

		// Feeds with no configured conditional logic always a match.
		if( ! feed.conditionalLogic ) {
			return true;
		}

		return gf_get_field_action( formId, feed.conditionalLogic ) == 'show';
	};

	self.getTriggerInputIds = function() {
		var inputIds = [];
		for( var i = 0; i < self.options.feeds.length; i++ ) {

			var feed = self.options.feeds[ i ];

			if( ! feed.conditionalLogic ) {
				continue;
			}

			for( var j = 0; j < feed.conditionalLogic.rules.length; j++ ) {
				var rule = self.options.feeds[i].conditionalLogic.rules[j];
				if( $.inArray( rule.fieldId, inputIds ) == -1 ) {
					inputIds.push( rule.fieldId );
				}
			}

		}
		return inputIds;
	};

	self.isFeedActivated = function( feed ) {

		if( typeof feed != 'object' ) {
			feed = self.getFeed( feed );
			if( ! feed ) {
				return false;
			}
		}

		return typeof feed.isActivated != 'undefined' ? feed.isActivated : null;
	};

	self.getFeed = function( feedId ) {
		for( var i = 0; i < self.options.feeds.length; i++ ) {
			var feed = self.options.feeds[ i ];
			if( feed.feedId == feedId ) {
				return feed;
			}
		}
		return false;
	};

	self.getFeedsByAddon = function( addonSlug, currentFeed, onlyActive ) {
		var feeds = [];
		for( var i = 0; i < self.options.feeds.length; i++ ) {
			var feed = self.options.feeds[ i ];
			if( feed.addonSlug == addonSlug
				&& ! ( currentFeed && feed.feedId == currentFeed.feedId )
			) {
				if( onlyActive ) {
					if( self.isFeedActivated( feed ) ) {
						feeds.push( feed );
					}
				} else {
					feeds.push( feed );
				}

			}
		}
		return feeds;
	};

	self.activateFeed = function( feeds ) {

		if( feeds.feedId ) {
			feeds = [ feeds ];
		}

		for( var i = 0; i < feeds.length; i++ ) {

			var feed = feeds[ i ];

			feed.isActivated = true;

			/**
			 * Fires after the conditional logic on the form has been evaluated and the feed has been found to be active.
			 *
			 * @since 2.4
			 *
			 * @param array $feeds     A collection of feed objects.
			 * @param int   $formId    The form id.
			 */

			gform.doAction( 'gform_frontend_feed_activated', feed, self.options.formId );
			gform.doAction( 'gform_frontend_feed_activated_{0}'.format( self.options.formId ), feed, self.options.formId );
			gform.doAction( 'gform_{0}_frontend_feed_activated'.format( feed.addonSlug ), feed, self.options.formId );
			gform.doAction( 'gform_{0}_frontend_feed_activated_{0}'.format( feed.addonSlug, self.options.formId ), feed, self.options.formId );

			if( feed.isSingleFeed ) {
				self.deactivateFeed( self.getFeedsByAddon( feed.addonSlug, feed ) );
			}

		}

	};

	self.deactivateFeed = function( feeds ) {

		if( feeds.feedId ) {
			feeds = [ feeds ];
		}

		for( var i = 0; i < feeds.length; i++ ) {

			var feed        = feeds[ i ],
				isActivated = self.isFeedActivated( feed );

			if( isActivated === null || isActivated === false ) {
				continue;
			}

			feed.isActivated = false;

			/**
			 * Fires after the conditional logic on the form has been evaluated and the feed has become inactive.
			 *
			 * @since 2.4
			 *
			 * @param array $feeds     A collection of feed objects.
			 * @param int   $formId    The form id.
			 */
			gform.doAction( 'gform_frontend_feed_deactivated', feed, self.options.formId );
			gform.doAction( 'gform_frontend_feed_deactivated_{0}'.format( self.options.formId ), feed, self.options.formId );
			gform.doAction( 'gform_{0}_frontend_feed_deactivated'.format( feed.addonSlug ), feed, self.options.formId );
			gform.doAction( 'gform_{0}_frontend_feed_deactivated_{0}'.format( feed.addonSlug, self.options.formId ), feed, self.options.formId );

		}

	};

	self.hasPriority = function( feedId, addonSlug ) {

		var addonFeeds = self.getFeedsByAddon( addonSlug );

		for( var i = 0; i <= addonFeeds.length; i++ ) {

			var feed = addonFeeds[i];

			if( feed.feedId != feedId && feed.isActivated ) {
				return false;
			} else if ( feed.feedId == feedId ) {
				return true;
			}

		}

		return false;
	};

	this.init();

};
