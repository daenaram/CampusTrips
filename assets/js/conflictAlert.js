document.addEventListener('DOMContentLoaded', function () {
    const data = window.TRIP_CONFLICT_DATA;
    if (!data || !data.hasConflict) return;

    const tripDetailsModal = document.getElementById('trip-details-modal');
    const tripDetailsContent = document.getElementById('trip-details-content');
    const template = document.getElementById('trip-details-template-' + data.conflictId);
    const modalHeader = tripDetailsModal.querySelector('.modal-header');
    const tripForm = document.querySelector('#trip-modal .modal-form');

    
    function renewModalState() {
        if (modalHeader) modalHeader.style.display = 'flex';
        tripDetailsContent.innerHTML = '';
    }

   
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

    
        document.getElementById('back-to-edit-trip').addEventListener('click', function () {
            renewModalState();
            hideTripDetailsModal();
            showTripModal();
        });

        const closeBtn = document.getElementById('close-trip-details-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                renewModalState();
                clearTripFormData();
            });
        }


        tripDetailsModal.addEventListener('click', function (event) {
            if (event.target === tripDetailsModal) {
                renewModalState();
                clearTripFormData();
            }
        });
    }
});
