document.addEventListener('DOMContentLoaded', function () {
    const data = window.TRIP_CONFLICT_DATA;
    if (!data || !data.hasConflict) return;

    const tripDetailsModal = document.getElementById('trip-details-modal');
    const tripDetailsContent = document.getElementById('trip-details-content');
    const template = document.getElementById('trip-details-template-' + data.conflictId);
    const modalHeader = tripDetailsModal.querySelector('.modal-header');

    /**
     * Clears the conflict UI and restores the standard modal header
     */
    function renewModalState() {
        if (modalHeader) modalHeader.style.display = 'flex';
        tripDetailsContent.innerHTML = '';
    }

    if (template && tripDetailsContent) {
        // 1. HIDE THE STANDARD MODAL HEADER
        if (modalHeader) modalHeader.style.display = 'none';

        // Extract summary data
        const summaryDiv = template.querySelector('.trip-details-summary');
        const summaryHtml = summaryDiv ? summaryDiv.innerHTML : '';

        // Build the focused UI
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

        // Show the modal (using the function defined in Dashboard.php)
        showTripDetailsModal();

        // 2. HANDLE RENEWAL ON "ADJUST TRIP DATES"
        document.getElementById('back-to-edit-trip').addEventListener('click', function () {
            renewModalState();
            hideTripDetailsModal();
            showTripModal();
        });

        // 3. HANDLE RENEWAL ON STANDARD CLOSE BUTTON
        const closeBtn = document.getElementById('close-trip-details-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', renewModalState);
        }

        // 4. HANDLE RENEWAL ON BACKDROP CLICK
        tripDetailsModal.addEventListener('click', function (event) {
            if (event.target === tripDetailsModal) {
                renewModalState();
            }
        });
    }
});
