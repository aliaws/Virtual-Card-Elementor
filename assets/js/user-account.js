/**
 * User Account Dropdown Toggle Script
 */
(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {
		const dropdowns = document.querySelectorAll('.vce-user-account-dropdown');

		dropdowns.forEach(function(dropdown) {
			const trigger = dropdown.querySelector('.vce-user-account-trigger');
			const menu = dropdown.querySelector('.vce-user-account-menu');

			if (!trigger || !menu) return;

			// Toggle dropdown on click
			trigger.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();

				const isOpen = dropdown.classList.contains('active');

				// Close all other dropdowns
				document.querySelectorAll('.vce-user-account-dropdown.active').forEach(function(other) {
					if (other !== dropdown) {
						other.classList.remove('active');
						const otherTrigger = other.querySelector('.vce-user-account-trigger');
						if (otherTrigger) {
							otherTrigger.setAttribute('aria-expanded', 'false');
						}
					}
				});

				// Toggle current dropdown
				dropdown.classList.toggle('active');
				trigger.setAttribute('aria-expanded', !isOpen);
			});

			// Close dropdown when clicking outside
			document.addEventListener('click', function(e) {
				if (!dropdown.contains(e.target)) {
					dropdown.classList.remove('active');
					trigger.setAttribute('aria-expanded', 'false');
				}
			});

			// Close dropdown on Escape key
			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape' && dropdown.classList.contains('active')) {
					dropdown.classList.remove('active');
					trigger.setAttribute('aria-expanded', 'false');
					trigger.focus();
				}
			});
		});
	});
})();
