// script.js - Szívhangja Akadálymentességi és Interakciós Kezelő

document.addEventListener('DOMContentLoaded', () => {
    console.log('Szívhangja akadálymentesített felület betöltve.');

    // --- 1. MODÁLIS KEZELŐ (FOCUS TRAP) ---
    const accessibilityManager = {
        activeModal: null,
        lastFocusedElement: null,

        // Fókuszcsapda implementáció
        trapFocus: function(modalElement) {
            const focusableElements = modalElement.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            const firstFocusable = focusableElements[0];
            const lastFocusable = focusableElements[focusableElements.length - 1];

            modalElement.addEventListener('keydown', (e) => {
                if (e.key !== 'Tab') return;

                if (e.shiftKey) { // Shift + Tab
                    if (document.activeElement === firstFocusable) {
                        lastFocusable.focus();
                        e.preventDefault();
                    }
                } else { // Tab
                    if (document.activeElement === lastFocusable) {
                        firstFocusable.focus();
                        e.preventDefault();
                    }
                }
            });
        },

        // Modális megnyitása
        openModal: function(modalId, triggerElement = null) {
            const modal = document.getElementById(modalId);
            if (!modal) return;

            this.lastFocusedElement = triggerElement || document.activeElement;
            modal.style.display = 'flex';
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            
            // Fókusz az első elemre vagy a bezárás gombra
            const firstInput = modal.querySelector('button, input, select, textarea, a[href]');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }

            this.trapFocus(modal);
            this.activeModal = modal;

            // Esc gombbal való bezárás
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    this.closeModal(modalId);
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);
        },

        // Modális bezárása
        closeModal: function(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;

            modal.style.display = 'none';
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            
            if (this.lastFocusedElement) {
                this.lastFocusedElement.focus();
            }
            this.activeModal = null;
        },

        // Üzenet bejelentése képernyőolvasónak
        announce: function(message, priority = 'polite') {
            let liveRegion = document.getElementById('aria-live-announcer');
            if (!liveRegion) {
                liveRegion = document.createElement('div');
                liveRegion.id = 'aria-live-announcer';
                liveRegion.setAttribute('aria-live', priority);
                liveRegion.classList.add('sr-only');
                document.body.appendChild(liveRegion);
            }
            liveRegion.innerHTML = '';
            setTimeout(() => {
                liveRegion.innerHTML = message;
            }, 50);
        }
    };

    // Exportálás globális használatra (pl. inline onclick helyett, ha szükséges)
    window.Accessibility = accessibilityManager;

    // --- 2. AUTOMATIKUS ESEMÉNYKEZELŐK ---

    // Pánikgomb (messages.php)
    const panicBtn = document.getElementById('panic-btn');
    if (panicBtn) {
        panicBtn.addEventListener('click', () => {
            Accessibility.openModal('panic-modal', panicBtn);
        });

        // Jelentés beküldő gombok (messages.php belső scriptje hívva)
        const reportOnlyBtn = document.getElementById('report-only-btn');
        if (reportOnlyBtn) {
            reportOnlyBtn.addEventListener('click', () => {
                if (typeof submitPanic === 'function') submitPanic('report');
            });
        }

        const reportBlockBtn = document.getElementById('report-block-btn');
        if (reportBlockBtn) {
            reportBlockBtn.addEventListener('click', () => {
                if (typeof submitPanic === 'function') submitPanic('report_block');
            });
        }
    }

    // Új poszt gomb (forum.php / header.php)
    const newPostBtn = document.querySelector('[onclick*="newPostModal"]');
    if (newPostBtn) {
        // Leváltjuk az inline onclick-et, ha lehetséges, de a meglévő is hívhatja az Accessibility-t
        newPostBtn.removeAttribute('onclick');
        newPostBtn.addEventListener('click', (e) => {
            e.preventDefault();
            Accessibility.openModal('newPostModal', newPostBtn);
        });
    }

    // Modal bezáró gombok keresése
    document.querySelectorAll('.modal-close, .btn-cancel-modal').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const modal = btn.closest('[role="dialog"], .glass-modal-overlay, #newPostModal');
            if (modal) {
                Accessibility.closeModal(modal.id);
            }
        });
    });

    // --- 3. DINAMIKUS TARTALOM BEJELENTÉSE ---

    // Fájlfeltöltés visszajelzés (register.php)
    const fileInput = document.getElementById('profile_image');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : 'Nincs kiválasztva fájl';
            const statusSpan = document.querySelector('#file-label span');
            if (statusSpan) statusSpan.textContent = fileName;
            Accessibility.announce('Kiválasztott fájl: ' + fileName);
        });
    }
});
