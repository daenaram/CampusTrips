document.addEventListener('DOMContentLoaded', function () {
    const data = window.TRIP_CONFLICT_DATA;
    if (!data || !data.hasConflict) return;

    const tripDetailsModal = document.getElementById('trip-details-modal');
    const tripDetailsContent = document.getElementById('trip-details-content');
    const template = document.getElementById('trip-details-template-' + data.conflictId);
    const modalHeader = tripDetailsModal.querySelector('.modal-header');
    const tripForm = document.querySelector('#trip-modal .modal-form');

    /**
     * Clears the conflict UI and restores the standard modal header
     */
    function renewModalState() {
        if (modalHeader) modalHeader.style.display = 'flex';
        tripDetailsContent.innerHTML = '';
    }

    /**
     * Wipes out the create-trip form fields so "Create new Trip"
     * opens a fresh, blank form next time (instead of the
     * conflicting title/destination/dates left over from the failed submit)
     */
    function clearTripFormData() {
        if (!tripForm) return;

        tripForm.querySelectorAll('input[type="text"], input[type="date"], textarea')
            .forEach(function (field) {
                field.value = '';
            });

        // Also hide any leftover PHP-rendered error messages
        const errorsBox = tripForm.parentElement.querySelector('.modal-errors');
        if (errorsBox) errorsBox.remove();
    }

    if (template && tripDetailsContent) {
        // 1. HIDE THE STANDARD MODAL HEADER
        if (modalHeader) modalHeader.style.display = 'none';

        const summaryDiv = template.querySelector('.trip-details-summary');
        const summaryHtml = summaryDiv ? summaryDiv.innerHTML : '';

        tripDetailsContent.innerHTML = `
            <div class="conflict-banner-clean">
                <div class="conflict-header">
                    <strong>Date Overlap Detected</strong>
                </div>

                <p class="conflict-subtext">You already have a trip planned during these dates:</p>

                <div class="conflicting-trip-brief">
                    ${summaryHtml}
                </div>

                <div class="conflict-actions">
                    <button type="button" id="back-to-edit-trip" class="modal-btn modal-save">
                        Adjust Trip Dates
                    </button>
                </div>
            </div>
        `;

        showTripDetailsModal();

        // 2. "ADJUST TRIP DATES" -> keep the entered data so the user can fix it
        document.getElementById('back-to-edit-trip').addEventListener('click', function () {
            renewModalState();
            hideTripDetailsModal();
            showTripModal();
        });

        // 3. STANDARD CLOSE (×) = CANCEL -> wipe the form
        const closeBtn = document.getElementById('close-trip-details-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                renewModalState();
                clearTripFormData();
            });
        }

        // 4. BACKDROP CLICK = CANCEL -> wipe the form
        tripDetailsModal.addEventListener('click', function (event) {
            if (event.target === tripDetailsModal) {
                renewModalState();
                clearTripFormData();
            }
        });
    }
});
