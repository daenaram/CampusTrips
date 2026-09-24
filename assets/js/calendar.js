// --------- Calendar Modal Behaviour ----------
document.addEventListener('DOMContentLoaded', function() {
    const calendarModalBackdrop = document.getElementById('calendar-modal-backdrop');
    const floatingCalendarBtn = document.getElementById('floating-calendar-btn');
    const calendarCloseBtn = document.getElementById('calendar-close-btn');
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');
    const calendarMonthYear = document.getElementById('calendar-month-year');
    const calendarDaysContainer = document.getElementById('calendar-days');
    const calendarTripsList = document.getElementById('calendar-trips-list');

    let currentDate = new Date();

    // Parse trip dates and prepare data
    const tripsByDate = {};
    const allTripDates = new Set();

    if (typeof tripsData !== 'undefined' && Array.isArray(tripsData)) {
        tripsData.forEach(trip => {
            const startDate = new Date(trip.start_date);
            const endDate = new Date(trip.end_date);
            
            // Add all dates in the trip range to the set
            let currentDateInRange = new Date(startDate);
            while (currentDateInRange <= endDate) {
                const dateStr = currentDateInRange.toISOString().split('T')[0];
                allTripDates.add(dateStr);
                
                if (!tripsByDate[dateStr]) {
                    tripsByDate[dateStr] = [];
                }
                tripsByDate[dateStr].push(trip);
                
                currentDateInRange.setDate(currentDateInRange.getDate() + 1);
            }
        });
    }

    function formatDate(date) {
        const options = { year: 'numeric', month: 'long' };
        return date.toLocaleDateString('en-US', options);
    }

    function getDaysInMonth(date) {
        return new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
    }

    function getFirstDayOfMonth(date) {
        return new Date(date.getFullYear(), date.getMonth(), 1).getDay();
    }

    function renderCalendar() {
        calendarMonthYear.textContent = formatDate(currentDate);
        calendarDaysContainer.innerHTML = '';

        const daysInMonth = getDaysInMonth(currentDate);
        const firstDay = getFirstDayOfMonth(currentDate);
        const previousDaysInMonth = getDaysInMonth(new Date(currentDate.getFullYear(), currentDate.getMonth() - 1));

        // Previous month's days
        for (let i = firstDay - 1; i >= 0; i--) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day other-month';
            dayElement.textContent = previousDaysInMonth - i;
            calendarDaysContainer.appendChild(dayElement);
        }

        // Current month's days
        for (let day = 1; day <= daysInMonth; day++) {
            const dayElement = document.createElement('div');
            const dateStr = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            
            dayElement.className = 'calendar-day';
            dayElement.textContent = day;

            if (allTripDates.has(dateStr)) {
                dayElement.classList.add('has-trip');
                dayElement.style.cursor = 'pointer';
                dayElement.addEventListener('click', () => showTripsForDate(dateStr));
            }

            calendarDaysContainer.appendChild(dayElement);
        }

        // Next month's days
        const totalCells = calendarDaysContainer.children.length;
        const remainingCells = 42 - totalCells; // 6 weeks * 7 days
        for (let day = 1; day <= remainingCells; day++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day other-month';
            dayElement.textContent = day;
            calendarDaysContainer.appendChild(dayElement);
        }
    }

    function showTripsForDate(dateStr) {
        const trips = tripsByDate[dateStr] || [];
        
        if (trips.length === 0) {
            calendarTripsList.innerHTML = '<p class="no-trips-message">No trips on this date.</p>';
            return;
        }

        let html = `<h3>Trips on ${new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' })}</h3>`;
        
        trips.forEach(trip => {
            html += `
                <div class="calendar-trip-item">
                    <p class="calendar-trip-item-title">${trip.title}</p>
                    <p class="calendar-trip-item-details"><strong>Destination:</strong> ${trip.destination}</p>
                    <p class="calendar-trip-item-dates"><strong>Trip dates:</strong> ${trip.start_date} to ${trip.end_date}</p>
                </div>
            `;
        });

        calendarTripsList.innerHTML = html;
    }

    function showCalendarModal() {
        calendarModalBackdrop.classList.add('visible');
        renderCalendar();
        showTripsForDate(currentDate.toISOString().split('T')[0]);
    }

    function closeCalendarModal() {
        calendarModalBackdrop.classList.remove('visible');
    }

    floatingCalendarBtn.addEventListener('click', showCalendarModal);
    calendarCloseBtn.addEventListener('click', closeCalendarModal);

    prevMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    });

    nextMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    });

    calendarModalBackdrop.addEventListener('click', (event) => {
        if (event.target === calendarModalBackdrop) {
            closeCalendarModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && calendarModalBackdrop.classList.contains('visible')) {
            closeCalendarModal();
        }
    });
});
