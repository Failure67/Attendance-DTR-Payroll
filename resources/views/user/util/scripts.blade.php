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

<script>
	(function () {
		let confirmModal = null;
		let confirmMessageEl = null;
		let confirmOkBtn = null;
		let pendingResolve = null;
		let pendingReject = null;

		function ensureModal() {
			if (!confirmModal) {
				const modalEl = document.getElementById('globalConfirmModal');
				if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
					return null;
				}
				confirmModal = new bootstrap.Modal(modalEl);
				confirmMessageEl = modalEl.querySelector('[data-confirm-message]');
				confirmOkBtn = modalEl.querySelector('[data-confirm-ok]');

				if (confirmOkBtn) {
					confirmOkBtn.addEventListener('click', function () {
						if (pendingResolve) {
							pendingResolve(true);
						}
						pendingResolve = null;
						pendingReject = null;
						confirmModal.hide();
					});
				}

				modalEl.addEventListener('hidden.bs.modal', function () {
					if (pendingReject) {
						pendingReject(false);
					}
					pendingResolve = null;
					pendingReject = null;
				});
			}
			return confirmModal;
		}

		window.appConfirm = function (message) {
			return new Promise(function (resolve, reject) {
				const modal = ensureModal();
				if (!modal) {
					// Fallback to native confirm if modal/bootstrap is unavailable
					resolve(window.confirm(message || 'Are you sure?'));
					return;
				}

				if (confirmMessageEl) {
					confirmMessageEl.textContent = message || 'Are you sure you want to proceed?';
				}

				pendingResolve = resolve;
				pendingReject = reject;
				modal.show();
			});
		};

		document.addEventListener('submit', function (e) {
			const form = e.target;
			if (!(form instanceof HTMLFormElement)) {
				return;
			}

			const message = form.getAttribute('data-confirm');
			if (!message) {
				return;
			}

			e.preventDefault();

			window.appConfirm(message).then(function (ok) {
				if (!ok) {
					return;
				}

				form.removeAttribute('data-confirm');
				form.submit();
			});
		});
	})();
</script>

<script>
	(function () {
		function initButtonTooltips(root) {
			const container = root || document;
			const elements = container.querySelectorAll('button, a.btn, a.dropdown-item');

			elements.forEach(function (el) {
				if (el.hasAttribute('title')) {
					return;
				}

				let tooltip = el.getAttribute('data-tooltip');

				if (!tooltip) {
					tooltip = el.getAttribute('aria-label');
				}

				if (!tooltip) {
					const labelEl = el.querySelector('.button-label');
					if (labelEl && labelEl.textContent) {
						tooltip = labelEl.textContent.trim();
					} else {
						const text = el.textContent;
						if (text) {
							tooltip = text.trim();
						}
					}
				}

				if (tooltip) {
					el.setAttribute('title', tooltip);
				}
			});
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function () {
				initButtonTooltips();
			});
		} else {
			initButtonTooltips();
		}

		if ('MutationObserver' in window) {
			const observer = new MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) {
					mutation.addedNodes.forEach(function (node) {
						if (node.nodeType === 1) {
							initButtonTooltips(node);
						}
					});
				});
			});

			observer.observe(document.body, { childList: true, subtree: true });
		}
	})();
</script>