import app from 'flarum/app';

import SingleSOSettingsModal from 'singleso/singleso-flarum/components/SingleSOSettingsModal';

app.initializers.add('singleso-singleso-flarum', () => {
	app.extensionSettings['singleso-singleso-flarum'] = () => app.modal.show(new SingleSOSettingsModal());
});
