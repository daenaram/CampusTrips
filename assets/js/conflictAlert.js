document.addEventListener('DOMContentLoaded', function () {
    const data = window.TRIP_CONFLICT_DATA;
    if (!data || !data.hasConflict) return;

    const tripDetailsModal = document.getElementById('trip-details-modal');
    const tripDetailsContent = document.getElementById('trip-details-content');
    const template = document.getElementById('trip-details-template-' + data.conflictId);

    if (template && tripDetailsContent) {
    
        // This hides the <h3>Trip Details</h3> and the close button area
        const modalHeader = tripDetailsModal.querySelector('.modal-header');
        if (modalHeader) modalHeader.style.display = 'none';

        //Extract summary data
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

        showTripDetailsModal();

        document.getElementById('back-to-edit-trip').addEventListener('click', function () {
            // Restore the header for normal "View Details" usage before closing
            if (modalHeader) modalHeader.style.display = 'flex';
            hideTripDetailsModal();
            showTripModal();
        });
    }
});
