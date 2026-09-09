/**
 * Renders the budget breakdown for the currently selected trip and handles
 * add / edit / delete of budget items via AJAX calls to budget.php.
 * Every successful call returns a freshly recalculated summary, which is
 * used to repaint the totals and category breakdown immediately —
 * no page refresh required.
 */

(function () {
    const contentEl = document.getElementById('budgetContent');
    if (!contentEl) return; // no trips / not on the budget page

    const tabsEl = document.getElementById('budgetTripTabs');

    // Local cache of BUDGET_DATA so we don't have to re-fetch after every edit;
    // the server response for each write already gives us the updated summary.
    const budgetData = Object.assign({}, BUDGET_DATA);

    function formatCurrency(value) {
        return 'NZD ' + Number(value).toLocaleString('en-NZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getTripById(tripId) {
        return BUDGET_TRIPS.find(t => parseInt(t.id, 10) === parseInt(tripId, 10));
    }

    function categoryOptionsHtml(selected) {
        return BUDGET_CATEGORIES.map(cat =>
            `<option value="${cat}" ${cat === selected ? 'selected' : ''}>${cat}</option>`
        ).join('');
    }

    function currencyOptionsHtml(selected) {
        return BUDGET_CURRENCIES.map(cur =>
            `<option value="${cur}" ${cur === selected ? 'selected' : ''}>${cur}</option>`
        ).join('');
    }

    function render(tripId) {
        const summary = budgetData[tripId];
        const trip = getTripById(tripId);
        if (!summary || !trip) {
            contentEl.innerHTML = '<p class="budget-error">Unable to load this trip\'s budget.</p>';
            return;
        }

        const categoryTotals = summary.category_totals || {};
        const grandTotal = summary.grand_total || 0;
        const hasBudgetCap = summary.budget_cap !== null && summary.budget_cap !== undefined;
        const budgetCap = hasBudgetCap ? Number(summary.budget_cap) : null;
        const customItems = summary.custom_items || [];

        // Category breakdown bars (percentage of grand total, min 0 avoids div by zero)
        const breakdownRows = Object.keys(categoryTotals).map(cat => {
            const amount = categoryTotals[cat] || 0;
            const pct = grandTotal > 0 ? Math.round((amount / grandTotal) * 100) : 0;
            return `
                <div class="budget-breakdown-row">
                    <div class="budget-breakdown-label">
                        <span>${escapeHtml(cat)}</span>
                        <span>${formatCurrency(amount)} (${pct}%)</span>
                    </div>
                    <div class="budget-breakdown-bar-track">
                        <div class="budget-breakdown-bar-fill" style="width:${pct}%;"></div>
                    </div>
                </div>`;
        }).join('');

        const customItemRows = customItems.length === 0
            ? '<p class="budget-empty-row">No manually added items yet. Use the form below to add one (e.g. travel insurance, visa fees, souvenirs).</p>'
            : customItems.map(item => `
                <div class="budget-item-row" data-item-id="${item.id}">
                    <div class="budget-item-info">
                        <strong>${escapeHtml(item.item_name)}</strong>
                        <span class="budget-item-category">${escapeHtml(item.category)}</span>
                    </div>
                    <div class="budget-item-amounts">
                        <span>${Number(item.amount).toLocaleString('en-NZ', {minimumFractionDigits:2, maximumFractionDigits:2})} ${escapeHtml(item.currency)}</span>
                        <span class="budget-item-nzd">= ${formatCurrency(item.amount_nzd)}</span>
                    </div>
                    <div class="budget-item-actions">
                        <button type="button" class="budget-edit-btn" data-item-id="${item.id}">Edit</button>
                        <button type="button" class="budget-delete-btn" data-item-id="${item.id}">Remove</button>
                    </div>
                </div>`).join('');

        contentEl.innerHTML = `
            <div class="budget-summary-card">
                <div class="budget-trip-heading">
                    <h2>${escapeHtml(trip.title)}</h2>
                    <span>${escapeHtml(trip.destination)} &middot; ${escapeHtml(trip.start_date)} → ${escapeHtml(trip.end_date)}</span>
                </div>

                <div class="budget-grand-total">
                    <span class="budget-grand-total-label">Total Trip Cost</span>
                    <span class="budget-grand-total-value" id="budgetGrandTotal">${formatCurrency(grandTotal)}</span>
                </div>

                ${hasBudgetCap && grandTotal > budgetCap ? `
                    <div class="budget-cap-warning" role="alert">
                        <strong>Spending exceeds the set budget cap.</strong>
                        <span>You are ${formatCurrency(grandTotal - budgetCap)} over your ${formatCurrency(budgetCap)} cap. You can continue planning.</span>
                    </div>` : ''}

                <form id="budgetCapForm" class="budget-cap-form">
                    <input type="hidden" name="trip_id" value="${tripId}">
                    <label for="budgetCapInput">Budget cap</label>
                    <div class="budget-cap-controls">
                        <input id="budgetCapInput" name="budget_cap" type="number" min="0" step="0.01" placeholder="No cap" value="${hasBudgetCap ? budgetCap.toFixed(2) : ''}">
                        <button type="submit" class="budget-cap-save-btn">${hasBudgetCap ? 'Update cap' : 'Set cap'}</button>
                        ${hasBudgetCap ? '<button type="button" class="budget-cap-remove-btn" id="budgetCapRemoveBtn">Remove cap</button>' : ''}
                    </div>
                    <p class="budget-cap-help">Leave the field empty to remove the cap.</p>
                    <p class="budget-form-error" id="budgetCapError"></p>
                </form>

                <div class="budget-breakdown" id="budgetBreakdown">
                    ${breakdownRows}
                </div>
            </div>

            <div class="budget-items-card">
                <h3>Additional Budget Items</h3>
                <p class="budget-items-subtitle">Flights, accommodation, and activities you've saved to this trip are counted automatically above. Add anything else here — it'll be converted to NZD and included in the total right away.</p>

                <div class="budget-items-list" id="budgetItemsList">
                    ${customItemRows}
                </div>

                <form id="budgetAddForm" class="budget-add-form">
                    <input type="hidden" name="trip_id" value="${tripId}">
                    <div class="budget-form-row">
                        <label>
                            Category
                            <select name="category" required>${categoryOptionsHtml('')}</select>
                        </label>
                        <label>
                            Item
                            <input type="text" name="item_name" placeholder="e.g. Travel insurance" required>
                        </label>
                    </div>
                    <div class="budget-form-row">
                        <label>
                            Amount
                            <input type="number" name="amount" step="0.01" min="0" placeholder="0.00" required>
                        </label>
                        <label>
                            Currency
                            <select name="currency">${currencyOptionsHtml('NZD')}</select>
                        </label>
                        <button type="submit" class="budget-add-btn">Add Item</button>
                    </div>
                    <p class="budget-form-error" id="budgetFormError"></p>
                </form>
            </div>
        `;

        attachItemEvents(tripId);
        attachFormEvents(tripId);
        attachCapEvents(tripId);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function attachFormEvents(tripId) {
        const form = document.getElementById('budgetAddForm');
        if (!form) return;

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const errorEl = document.getElementById('budgetFormError');
            errorEl.textContent = '';

            const formData = new FormData(form);
            formData.append('ajax_action', 'add_item');

            postToBudgetApi(formData, tripId, function (data) {
                if (!data.success) {
                    errorEl.textContent = data.message || 'Unable to add item.';
                }
            });
        });
    }

    function attachCapEvents(tripId) {
        const form = document.getElementById('budgetCapForm');
        if (!form) return;

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const errorEl = document.getElementById('budgetCapError');
            errorEl.textContent = '';
            const formData = new FormData(form);
            formData.append('ajax_action', 'set_budget_cap');
            postToBudgetApi(formData, tripId, function (data) {
                if (!data.success) {
                    errorEl.textContent = data.message || 'Unable to update the budget cap.';
                }
            });
        });

        const removeButton = document.getElementById('budgetCapRemoveBtn');
        if (removeButton) {
            removeButton.addEventListener('click', function () {
                const formData = new FormData();
                formData.append('ajax_action', 'set_budget_cap');
                formData.append('trip_id', tripId);
                formData.append('budget_cap', '');
                postToBudgetApi(formData, tripId);
            });
        }
    }

    function attachItemEvents(tripId) {
        contentEl.querySelectorAll('.budget-delete-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                if (!confirm('Remove this budget item?')) return;
                const formData = new FormData();
                formData.append('ajax_action', 'delete_item');
                formData.append('item_id', btn.dataset.itemId);
                formData.append('trip_id', tripId);
                postToBudgetApi(formData, tripId);
            });
        });

        contentEl.querySelectorAll('.budget-edit-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                startInlineEdit(tripId, btn.dataset.itemId);
            });
        });
    }

    function startInlineEdit(tripId, itemId) {
        const summary = budgetData[tripId];
        const item = (summary.custom_items || []).find(i => String(i.id) === String(itemId));
        if (!item) return;

        const row = contentEl.querySelector(`.budget-item-row[data-item-id="${itemId}"]`);
        if (!row) return;

        row.innerHTML = `
            <form class="budget-inline-edit-form">
                <input type="hidden" name="item_id" value="${item.id}">
                <input type="hidden" name="trip_id" value="${tripId}">
                <select name="category">${categoryOptionsHtml(item.category)}</select>
                <input type="text" name="item_name" value="${escapeHtml(item.item_name)}" required>
                <input type="number" name="amount" step="0.01" min="0" value="${item.amount}" required>
                <select name="currency">${currencyOptionsHtml(item.currency)}</select>
                <button type="submit" class="budget-save-btn">Save</button>
                <button type="button" class="budget-cancel-btn">Cancel</button>
            </form>
        `;

        const form = row.querySelector('form');
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(form);
            formData.append('ajax_action', 'update_item');
            postToBudgetApi(formData, tripId);
        });
        row.querySelector('.budget-cancel-btn').addEventListener('click', function () {
            render(tripId); // re-render to discard the inline edit
        });
    }

    function postToBudgetApi(formData, tripId, onDone) {
        fetch('budget.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.summary) {
                    budgetData[tripId] = data.summary;
                    render(tripId); // repaint totals + breakdown immediately, no reload
                }
                if (typeof onDone === 'function') onDone(data);
            })
            .catch(err => {
                console.error('Budget request failed:', err);
                if (typeof onDone === 'function') onDone({ success: false, message: 'Network error. Please try again.' });
            });
    }

    // ---------- Trip tab switching ----------
    if (tabsEl) {
        tabsEl.querySelectorAll('.budget-trip-tab').forEach(tab => {
            tab.addEventListener('click', function () {
                tabsEl.querySelectorAll('.budget-trip-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                SELECTED_TRIP_ID = parseInt(tab.dataset.tripId, 10);
                render(SELECTED_TRIP_ID);
            });
        });
    }

    // Initial render
    if (SELECTED_TRIP_ID) {
        render(SELECTED_TRIP_ID);
    }
})();