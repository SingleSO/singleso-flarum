'use strict';

System.register('singleso/singleso-flarum/components/SingleSOSettingsModal', ['flarum/components/SettingsModal'], function (_export, _context) {
	"use strict";

	var SettingsModal, SingleSOSettingsModal;
	return {
		setters: [function (_flarumComponentsSettingsModal) {
			SettingsModal = _flarumComponentsSettingsModal.default;
		}],
		execute: function () {
			SingleSOSettingsModal = function (_SettingsModal) {
				babelHelpers.inherits(SingleSOSettingsModal, _SettingsModal);

				function SingleSOSettingsModal() {
					babelHelpers.classCallCheck(this, SingleSOSettingsModal);
					return babelHelpers.possibleConstructorReturn(this, Object.getPrototypeOf(SingleSOSettingsModal).apply(this, arguments));
				}

				babelHelpers.createClass(SingleSOSettingsModal, [{
					key: 'className',
					value: function className() {
						return 'SingleSOSettingsModal Modal--large';
					}
				}, {
					key: 'title',
					value: function title() {
						return 'SingleSO Settings';
					}
				}, {
					key: 'form',
					value: function form() {
						var authBase = app.forum.attribute('baseUrl') + app['singleso-singleso-flarum'].controller;
						var authAction = authBase;
						var logoutAction = authBase + '/logout';
						var redirectUriNoprotocolOptions = {
							'': 'Disabled',
							'1': 'Enabled'
						};
						return [m(
							'div',
							{ className: 'Form-group' },
							m(
								'label',
								null,
								'Client ID'
							),
							m('input', { className: 'FormControl', bidi: this.setting('singleso-singleso-flarum.client_id') })
						), m(
							'div',
							{ className: 'Form-group' },
							m(
								'label',
								null,
								'Client Secret'
							),
							m('input', { className: 'FormControl', bidi: this.setting('singleso-singleso-flarum.client_secret') })
						), m(
							'div',
							{ className: 'Form-group' },
							m(
								'label',
								null,
								'Endpoint URL'
							),
							m('input', { className: 'FormControl', bidi: this.setting('singleso-singleso-flarum.endpoint_url') })
						), m(
							'div',
							{ className: 'Form-group' },
							m(
								'label',
								null,
								'Login URL'
							),
							m('input', { className: 'FormControl', bidi: this.setting('singleso-singleso-flarum.login_url') })
						), m(
							'div',
							{ className: 'Form-group' },
							m(
								'label',
								null,
								'Register URL'
							),
							m('input', { className: 'FormControl', bidi: this.setting('singleso-singleso-flarum.register_url') })
						), m(
							'div',
							{ className: 'Form-group' },
							m(
								'label',
								null,
								'Logout URL ',
								m(
									'small',
									null,
									'(optional, enables global logout)'
								)
							),
							m('input', { className: 'FormControl', bidi: this.setting('singleso-singleso-flarum.logout_url') })
						), m(
							'div',
							{ className: 'Form-group' },
							m(
								'label',
								null,
								'Global Cookie ',
								m(
									'small',
									null,
									'(optional, enables global auto-login)'
								)
							),
							m('input', { className: 'FormControl', bidi: this.setting('singleso-singleso-flarum.global_cookie') })
						), m(
							'div',
							{ className: 'Form-group' },
							m(
								'label',
								null,
								'No protocol in redirect ',
								m(
									'small',
									null,
									'(enable to remove http/https protocol from redirect_uri, useful for issues with mod_security)'
								)
							),
							m(
								'select',
								{ className: 'Select-input FormControl', bidi: this.setting('singleso-singleso-flarum.redirect_uri_noprotocol') },
								Object.keys(redirectUriNoprotocolOptions).map(function (key) {
									return m(
										'option',
										{ value: key },
										redirectUriNoprotocolOptions[key]
									);
								})
							)
						), m(
							'div',
							{ className: 'Form-group' },
							m(
								'label',
								null,
								'Foced Endpoint IP Address ',
								m(
									'small',
									null,
									'(forces connecting to the endpoint at a specific IP address, only use if needed and understood)'
								)
							),
							m('input', { className: 'FormControl', bidi: this.setting('singleso-singleso-flarum.endpoint_ip_forced') })
						), m(
							'div',
							{ className: 'Form-group' },
							m(
								'label',
								null,
								'Redirect URI ',
								m(
									'small',
									null,
									'(value for SSO client)'
								)
							),
							m('input', { className: 'FormControl', readonly: 'readonly', value: authAction })
						), m(
							'div',
							{ className: 'Form-group' },
							m(
								'label',
								null,
								'Logout URI ',
								m(
									'small',
									null,
									'(value for SSO client)'
								)
							),
							m('input', { className: 'FormControl', readonly: 'readonly', value: logoutAction })
						), m(
							'div',
							{ className: 'Form-group' },
							m(
								'p',
								null,
								'SingleSO login and account settings can be bypassed for local-only accounts by appending either of the following strings to the URL.'
							),
							m(
								'blockquote',
								null,
								m(
									'pre',
									null,
									m(
										'code',
										null,
										'#singleso=0'
									)
								),
								m(
									'pre',
									null,
									m(
										'code',
										null,
										'?singleso=0'
									)
								)
							)
						)];
					}
				}]);
				return SingleSOSettingsModal;
			}(SettingsModal);

			_export('default', SingleSOSettingsModal);
		}
	};
});;
'use strict';

System.register('singleso/singleso-flarum/main', ['flarum/app', 'singleso/singleso-flarum/components/SingleSOSettingsModal'], function (_export, _context) {
	"use strict";

	var app, SingleSOSettingsModal;
	return {
		setters: [function (_flarumApp) {
			app = _flarumApp.default;
		}, function (_singlesoSinglesoFlarumComponentsSingleSOSettingsModal) {
			SingleSOSettingsModal = _singlesoSinglesoFlarumComponentsSingleSOSettingsModal.default;
		}],
		execute: function () {

			app.initializers.add('singleso-singleso-flarum', function () {
				app.extensionSettings['singleso-singleso-flarum'] = function () {
					return app.modal.show(new SingleSOSettingsModal());
				};
			});
		}
	};
});