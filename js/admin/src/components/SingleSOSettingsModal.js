import SettingsModal from 'flarum/components/SettingsModal';

export default class SingleSOSettingsModal extends SettingsModal {

	className() {
		return 'SingleSOSettingsModal Modal--large';
	}

	title() {
		return 'SingleSO Settings';
	}

	form() {
		const authBase = app.forum.attribute('baseUrl') +
			app['singleso-singleso-flarum'].controller;
		const authAction = authBase;
		const logoutAction = authBase + '/logout';
		const redirectUriNoprotocolOptions = {
			'': 'Disabled',
			'1': 'Enabled'
		};
		return [
			<div className="Form-group">
				<label>Client ID</label>
				<input className="FormControl" bidi={this.setting('singleso-singleso-flarum.client_id')}/>
			</div>,

			<div className="Form-group">
				<label>Client Secret</label>
				<input className="FormControl" bidi={this.setting('singleso-singleso-flarum.client_secret')}/>
			</div>,

			<div className="Form-group">
				<label>Endpoint URL</label>
				<input className="FormControl" bidi={this.setting('singleso-singleso-flarum.endpoint_url')}/>
			</div>,

			<div className="Form-group">
				<label>Login URL</label>
				<input className="FormControl" bidi={this.setting('singleso-singleso-flarum.login_url')}/>
			</div>,

			<div className="Form-group">
				<label>Register URL</label>
				<input className="FormControl" bidi={this.setting('singleso-singleso-flarum.register_url')}/>
			</div>,

			<div className="Form-group">
				<label>Logout URL <small>(optional, enables global logout)</small></label>
				<input className="FormControl" bidi={this.setting('singleso-singleso-flarum.logout_url')}/>
			</div>,

			<div className="Form-group">
				<label>Global Cookie <small>(optional, enables global auto-login)</small></label>
				<input className="FormControl" bidi={this.setting('singleso-singleso-flarum.global_cookie')}/>
			</div>,

			<div className="Form-group">
				<label>No protocol in redirect <small>(enable to remove http/https protocol from redirect_uri, useful for issues with mod_security)</small></label>
				<select className="Select-input FormControl" bidi={this.setting('singleso-singleso-flarum.redirect_uri_noprotocol')}>
					{Object.keys(redirectUriNoprotocolOptions).map(key => <option value={key}>{redirectUriNoprotocolOptions[key]}</option>)}
				</select>
			</div>,

			<div className="Form-group">
				<label>Redirect URI <small>(value for SSO client)</small></label>
				<input className="FormControl" readonly="readonly" value={authAction}/>
			</div>,

			<div className="Form-group">
				<label>Logout URI <small>(value for SSO client)</small></label>
				<input className="FormControl" readonly="readonly" value={logoutAction}/>
			</div>,

			<div className="Form-group">
				<p>SingleSO login and account settings can be bypassed for local-only accounts by appending either of the following strings to the URL.</p>
				<blockquote>
					<pre><code>#singleso=0</code></pre>
					<pre><code>?singleso=0</code></pre>
				</blockquote>
			</div>
		];
	}
}
