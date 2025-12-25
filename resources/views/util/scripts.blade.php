{{-- plugins --}}
<script src="{{ asset('assets/js/plugins/popper.min.js') }}"></script>
<script src="{{ asset('assets/bootstrap/js/bootstrap.min.js') }}"></script>
{{-- Use a browser-ready UMD build of Chart.js so window.Chart is available for dashboards --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/fontawesome/js/all.min.js') }}"></script>
<script src="{{ asset('assets/js/plugins/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('assets/select2/dist/js/select2.full.min.js') }}"></script>

{{-- components --}}
<script src="{{ asset('assets/components/js/input-field.js') }}"></script>
<script src="{{ asset('assets/components/js/manage-item.js') }}"></script>
<script src="{{ asset('assets/components/js/modal-console.js') }}"></script>
<script src="{{ asset('assets/components/js/modal-step.js') }}"></script>
<script src="{{ asset('assets/components/js/select-option.js') }}"></script>

{{-- page specified scripts --}}
<script src="{{ asset('assets/js/payroll-console.js') }}"></script>
<script src="{{ asset('assets/js/payroll-modal-step.js') }}"></script>
<script src="{{ asset('assets/js/payroll-table.js') }}"></script>
<script src="{{ asset('assets/js/attendance-table.js') }}"></script>
<script src="{{ asset('assets/js/attendance-bulk.js') }}"></script>
<script src="{{ asset('assets/js/users-console.js') }}"></script>
<script src="{{ asset('assets/js/users-modal-step.js') }}"></script>
<script src="{{ asset('assets/js/users-delete.js') }}"></script>
<script src="{{ asset('assets/js/cash-advances-table.js') }}"></script>

{{-- custom scripts --}}
<script src="{{ asset('assets/js/init-tables.js') }}"></script>

<script>
    $(document).ready(function(){
        //$('#deletePayrollModal').modal('show');
    });
</script>

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
		function openStatCardDetails(card) {
			const modalEl = document.getElementById('statCardDetailsModal');
			if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
				return;
			}

			const title = card.getAttribute('data-stat-title') || 'Details';
			const value = card.getAttribute('data-stat-value') || '—';
			const details = card.getAttribute('data-stat-details') || '';
			const source = card.getAttribute('data-stat-source') || '';
			const link = card.getAttribute('data-stat-link') || '';

			const titleEl = modalEl.querySelector('[data-stat-modal-title]');
			const valueEl = modalEl.querySelector('[data-stat-modal-value]');
			const detailsEl = modalEl.querySelector('[data-stat-modal-details]');
			const sourceEl = modalEl.querySelector('[data-stat-modal-source]');
			const linkEl = modalEl.querySelector('[data-stat-modal-link]');

			if (titleEl) titleEl.textContent = title;
			if (valueEl) valueEl.textContent = value;
			if (detailsEl) {
				detailsEl.textContent = details;
				const detailsBlock = detailsEl.closest('.mb-3');
				if (detailsBlock) {
					detailsBlock.style.display = details ? '' : 'none';
				}
			}
			if (sourceEl) {
				sourceEl.textContent = source;
				const sourceBlock = sourceEl.closest('.mb-0');
				if (sourceBlock) {
					sourceBlock.style.display = source ? '' : 'none';
				}
			}
			if (linkEl) {
				if (link) {
					linkEl.setAttribute('href', link);
					linkEl.style.display = '';
				} else {
					linkEl.setAttribute('href', '#');
					linkEl.style.display = 'none';
				}
			}

			const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
			modal.show();
		}

		document.addEventListener('click', function (e) {
			const target = e.target;
			if (!(target instanceof Element)) {
				return;
			}

			const card = target.closest('[data-stat-card="1"]');
			if (!card) {
				return;
			}

			// Preserve normal browser behavior for modified clicks.
			if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) {
				return;
			}

			e.preventDefault();
			e.stopPropagation();
			openStatCardDetails(card);
		}, true);
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
					const labelEl = el.querySelector('.button-label, .crew-chip-label');
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
