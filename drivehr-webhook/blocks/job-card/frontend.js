/**
 * DriveHR Job Card Frontend JavaScript
 *
 * Handles interactive features for the job card block:
 * - Learn More accordion toggle
 * - Apply Now modal window
 *
 * @package DriveHR_Webhook
 * @since 1.3.0
 */

(function() {
	'use strict';

	/**
	 * Initialize accordion toggles for "Learn More" sections
	 * Uses dynamic max-height measurement for smooth animation without content truncation
	 */
	function initAccordions() {
		const toggleButtons = document.querySelectorAll('.drivehr-job-card__button--learn-more');

		toggleButtons.forEach(button => {
			const targetId = button.getAttribute('aria-controls');
			const detailsPanel = document.getElementById(targetId);

			if (!detailsPanel) return;

			// Initialize max-height on load for panels that start hidden
			if (detailsPanel.hidden) {
				detailsPanel.style.maxHeight = '0';
			}

			button.addEventListener('click', function() {
				const isExpanded = this.getAttribute('aria-expanded') === 'true';

				if (isExpanded) {
					// Closing: animate to 0
					detailsPanel.style.maxHeight = '0';
					this.setAttribute('aria-expanded', 'false');
					// Set hidden after animation completes
					setTimeout(() => {
						detailsPanel.hidden = true;
					}, 400);
				} else {
					// Opening: measure actual height and animate to it
					detailsPanel.hidden = false;
					// Force reflow to ensure hidden is removed before measuring
					detailsPanel.offsetHeight;
					const scrollHeight = detailsPanel.scrollHeight;
					detailsPanel.style.maxHeight = scrollHeight + 'px';
					this.setAttribute('aria-expanded', 'true');

					// After animation, remove max-height limit so content can grow if needed
					setTimeout(() => {
						detailsPanel.style.maxHeight = 'none';
					}, 400);
				}
			});
		});
	}

	/**
	 * Initialize modal for "Apply Now" buttons
	 */
	function initApplyModal() {
		const applyButtons = document.querySelectorAll('.drivehr-job-card__button--apply');

		applyButtons.forEach(button => {
			button.addEventListener('click', function() {
				const applyUrl = this.getAttribute('data-apply-url');
				const jobTitle = this.getAttribute('data-job-title');

				if (!applyUrl) return;

				// Create modal
				const modal = createModal(applyUrl, jobTitle);
				document.body.appendChild(modal);

				// Prevent body scroll
				document.body.style.overflow = 'hidden';

				// Focus trap
				const firstFocusable = modal.querySelector('button');
				if (firstFocusable) {
					firstFocusable.focus();
				}
			});
		});
	}

	/**
	 * Create modal HTML structure
	 *
	 * @param {string} applyUrl - DriveHR application URL
	 * @param {string} jobTitle - Job title for modal header
	 * @returns {HTMLElement} Modal element
	 */
	function createModal(applyUrl, jobTitle) {
		const modal = document.createElement('div');
		modal.className = 'drivehr-modal';
		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');
		modal.setAttribute('aria-labelledby', 'drivehr-modal-title');

		modal.innerHTML = `
			<div class="drivehr-modal__overlay" data-modal-close></div>
			<div class="drivehr-modal__container">
				<div class="drivehr-modal__header">
					<h2 id="drivehr-modal-title" class="drivehr-modal__title">Apply for ${escapeHtml(jobTitle)}</h2>
					<button class="drivehr-modal__close" data-modal-close aria-label="Close application form">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
							<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
						</svg>
					</button>
				</div>
				<div class="drivehr-modal__body">
					<iframe
						src="${escapeHtml(applyUrl)}"
						title="Job application form for ${escapeHtml(jobTitle)}"
						class="drivehr-modal__iframe"
						frameborder="0"
						allowfullscreen
					></iframe>
				</div>
			</div>
		`;

		// Add close event listeners
		const closeElements = modal.querySelectorAll('[data-modal-close]');
		closeElements.forEach(element => {
			element.addEventListener('click', () => closeModal(modal));
		});

		// Close on Escape key
		modal.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') {
				closeModal(modal);
			}
		});

		// Monitor iframe navigation - close modal if user clicks "Back to description"
		const iframe = modal.querySelector('.drivehr-modal__iframe');
		if (iframe) {
			let hasLoaded = false;
			iframe.addEventListener('load', () => {
				// First load is the initial form page, ignore it
				if (!hasLoaded) {
					hasLoaded = true;
					return;
				}
				// Any subsequent navigation (like clicking "Back to description") closes the modal
				closeModal(modal);
			});
		}

		return modal;
	}

	/**
	 * Close and remove modal
	 *
	 * @param {HTMLElement} modal - Modal element to close
	 */
	function closeModal(modal) {
		// Restore body scroll
		document.body.style.overflow = '';

		// Animate out
		modal.classList.add('drivehr-modal--closing');

		setTimeout(() => {
			modal.remove();
		}, 200);
	}

	/**
	 * Escape HTML to prevent XSS
	 *
	 * @param {string} text - Text to escape
	 * @returns {string} Escaped text
	 */
	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g, m => map[m]);
	}

	/**
	 * Initialize all interactive features
	 */
	function init() {
		// Wait for DOM to be ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', () => {
				initAccordions();
				initApplyModal();
			});
		} else {
			initAccordions();
			initApplyModal();
		}
	}

	// Initialize
	init();
})();
