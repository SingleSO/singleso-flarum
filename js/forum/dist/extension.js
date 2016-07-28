'use strict';

System.register('singleso/singleso-flarum/main', [], function (_export, _context) {
	"use strict";

	return {
		setters: [],
		execute: function () {
			app.initializers.add('singleso-singleso-flarum', function () {
				// Append ?singleso=0 or #singleso=0 to the URL to disable these overrides.
				var disableOverride = 'singleso=0';

				// Get an extension setting.
				function setting(key) {
					return app['singleso-singleso-flarum'][key];
				}

				// Get the path to the controller, optionally adding redirect path.
				function controller(action, redirect) {
					// Must lazy access forum as it is not available on initial load.
					var baseURL = app.forum.attribute('baseUrl').replace(/\/*$/, '');
					var url = baseURL + setting('controller') + (action ? '/' + action : '');
					// Add redirect argument relative to the base if requested.
					var loc = '' + window.location;
					if (redirect && !loc.indexOf(baseURL)) {
						url = addQueryArg(url, 'redirect', loc.substr(baseURL.length));
					}
					return url;
				}

				// Get the action for an element.
				function elemAction(elem) {
					switch (true) {
						// Desktop login and mobile button which prompts login if guest.
						case matches(elem, '.item-logIn *'):
						case setting('guest') && matches(elem, '.App-primaryControl *'):
							{
								return controller('login', 1);
							}

						// Desktop register.
						case matches(elem, '.item-signUp *'):
							{
								return controller('register', 1);
							}

						// Logout desktop and mobile, exclude managed and unconfigred.
						case setting('managed') && setting('logout') && matches(elem, '.item-logOut *'):
							{
								return controller('logout', 1);
							}

						// Links for account settings, only hook if managed.
						case setting('managed') && matches(elem, '.Settings-account *'):
							{
								return controller('account', 0);
							}
					}
					return null;
				}

				// Check if an element matches a CSS selector.
				function matches(elem, selector) {
					return (elem.matches || elem.matchesSelector || elem.webkitMatchesSelector || elem.mozMatchesSelector || elem.msMatchesSelector || elem.oMatchesSelector || function (selector) {
						return Array.prototype.indexOf.call(this.ownerDocument.querySelectorAll(selector), this) > -1;
					}).call(elem, selector);
				}

				// Check if the URL contains the override disable string.
				function disabled() {
					// Split off the querystring and/or fragment.
					var queryHash = ('' + window.location).replace(/[^?#]*[?#]?/, '');
					// Check if it contains the override disable string.
					return queryHash.indexOf(disableOverride) > -1;
				}

				// And query argument to a URL.
				function addQueryArg(url, arg, val) {
					return url + (/\?/.test(url) ? '&' : '?') + arg + '=' + encodeURIComponent(val);
				}

				// Capture click events before they reach the overriden elements.
				window.addEventListener('click', function (e) {
					// Filter out only the targeted elements with actions, unless disabled.
					var action = elemAction(e.target);
					if (!action || disabled()) {
						return;
					}
					// Prevent the event from reaching the element itself.
					e.preventDefault();
					e.stopPropagation();
					// Redirect to the action.
					window.location = action;
				}, true);
			});
		}
	};
});