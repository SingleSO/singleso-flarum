(function(loc, base, path, redirect) {
	// Create the full redirect.
	redirect = base + path;

	// If location starts with base, add relative redirect.
	if (!loc.indexOf(base)) {
		redirect +=
			(/\?/.test(redirect) ? '&' : '?') +
			'redirect=' + encodeURIComponent(loc.substr(base.length));
	}

	// Redirect to it without adding a history entry.
	location.replace(redirect);
})('' + location, ___BASE___, ___PATH___);
