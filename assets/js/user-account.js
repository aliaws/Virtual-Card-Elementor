/**
 * User Account Dropdown Toggle
 */
(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {
		const dropdowns = document.querySelectorAll('.vce-user-account-dropdown');

		dropdowns.forEach(function(dropdown) {
			const trigger = dropdown.querySelector('.vce-user-account-trigger');
			if (!trigger) return;

			trigger.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				dropdown.classList.toggle('active');
				const isOpen = dropdown.classList.contains('active');
				trigger.setAttribute('aria-expanded', isOpen);
			});

			document.addEventListener('click', function(e) {
				if (!dropdown.contains(e.target)) {
					dropdown.classList.remove('active');
					trigger.setAttribute('aria-expanded', 'false');
				}
			});

			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape' && dropdown.classList.contains('active')) {
					dropdown.classList.remove('active');
					trigger.setAttribute('aria-expanded', 'false');
				}
			});
		});
	});
})();
