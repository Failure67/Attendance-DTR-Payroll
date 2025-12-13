{{-- plugins --}}
<script src="{{ asset('assets/js/plugins/popper.min.js') }}"></script>
<script src="{{ asset('assets/bootstrap/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('assets/fontawesome/js/all.min.js') }}"></script>
<script src="{{ asset('assets/js/plugins/jquery-3.7.1.min.js') }}"></script>

{{-- custom scripts --}}
<script src="{{ asset('assets/js/user-dashboard.js') }}"></script>
<script src="{{ asset('assets/js/worker-announcements.js') }}"></script>

<script>
	(function () {
		const storageKey = 'romarTheme';
		const root = document.documentElement;

		function initThemeToggle() {
			const toggleBtn = document.getElementById('themeToggle');

			function applyTheme(theme) {
				const next = theme === 'classic' ? 'classic' : 'modern';
				root.setAttribute('data-theme', next);
				localStorage.setItem(storageKey, next);
			}

			// Initialize from storage on load
			const saved = localStorage.getItem(storageKey);
			if (saved === 'classic' || saved === 'modern') {
				applyTheme(saved);
			}

			if (toggleBtn) {
				toggleBtn.addEventListener('click', function () {
					const current = root.getAttribute('data-theme') || 'modern';
					const next = current === 'modern' ? 'classic' : 'modern';
					applyTheme(next);
				});
			}
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initThemeToggle);
		} else {
			initThemeToggle();
		}
	})();
</script>