jQuery(document).ready(function ($) {
    /**
     * Funkcja wyświetlająca lekki dymek (tooltip) po najechaniu myszą.
     * @param {string} message - Tekst komunikatu.
     * @param {jQuery} $target - Element, nad którym ma się pojawić dymek.
     */
    function showHoverBubble(message, $target) {
        var $bubble = $('<div class="hover-bubble"></div>').text(message);
        $('body').append($bubble);
        // Pozycjonowanie dymka – umieszczamy go nad elementem, wycentrowanego horyzontalnie
        var targetOffset = $target.offset();
        var targetWidth = $target.outerWidth();
        $bubble.css({
            position: 'absolute',
            top: targetOffset.top - $bubble.outerHeight() - 8 + 'px',
            left: targetOffset.left + (targetWidth / 2) - ($bubble.outerWidth() / 2) + 'px',
            opacity: 0
        });
        // Animacja pojawienia
        $bubble.animate({ opacity: 1 }, 200);
        // Po opuszczeniu elementu usuwamy dymek
        $target.on('mouseleave.hoverBubble', function () {
            $bubble.animate({ opacity: 0 }, 200, function () {
                $(this).remove();
            });
            $target.off('mouseleave.hoverBubble');
        });
    }

    // Selektory DOM
    const locationInput = $('#location-filter');
    const downloadsDiv  = $('#harmonogram-downloads');
    const linkIcal      = $('#harmonogram-ical');
    const linkPdf       = $('#harmonogram-pdf');
    const loadingMessage = $('#loading-message');

    const tabButtons  = $('.harmonogram-tab-buttons .tab-btn');
    const tabContents = $('.harmonogram-tab-content .tab-content');

    const listContainer  = $('#harmonogram-list');
    const tableBody      = $('#harmonogram-table-content tbody');
    const calendarEl     = $('#custom-calendar');

    const messagesContainer = $('.harmonogram-messages');

    let calendarData  = [];
    let selectedDate  = new Date();
    let selectedMonth = selectedDate.getMonth();
    const currentYear = selectedDate.getFullYear();

    // Stałe kolory dla rodzajów odpadów
    const wasteColors = {
        'Odpady Zmieszane': '#000000',
        'Szkło': '#2C6E49',
        'Tworzywa sztuczne, metale, opakowania wielomateriałowe': '#FCA311',
        'Papier': '#1E88E5',
        'Odpady Biodegradowalne': '#603808',
        'Inny': '#CCCCCC'
    };

    // Lista rodzajów odpadów, które mają być traktowane jako jedna grupa
    const groupedWasteTypes = ['Tworzywa sztuczne', 'Metale', 'Opakowania wielomateriałowe'];

    // Funkcja „normalizująca” rodzaj odpadów
    function getConsolidatedWasteType(type) {
        if (groupedWasteTypes.includes(type)) {
            return 'Tworzywa sztuczne, metale, opakowania wielomateriałowe';
        }
        return type;
    }

    // Pomocnicza funkcja do zamiany pierwszej litery na wielką
    function capitalizeFirstLetter(string) {
        if (!string) return '';
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    // Inicjalizacja Feather Icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }

    // Tworzenie kontenera na przyciski i pobieranie
    const controlsContainer = $('<div>', {
        class: 'harmonogram-controls',
        style: 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;'
    });

    $('.harmonogram-tab-buttons').css({
        display: 'flex',
        gap: '10px',
        margin: '0',
        padding: '0'
    }).appendTo(controlsContainer);

    downloadsDiv.css({
        display: 'flex',
        gap: '10px',
        margin: '0'
    }).appendTo(controlsContainer);

    controlsContainer.insertBefore(listContainer);

    // Dodanie klas przyciskom
    tabButtons.addClass('tab-btn-light');
    downloadsDiv.find('a').addClass('harmonogram-download-link');

    // Obsługa kliknięcia w zakładki (zmiana widoku)
    tabButtons.on('click', function () {
        tabButtons.removeClass('active').attr('aria-selected', 'false');
        $(this).addClass('active').attr('aria-selected', 'true');

        tabContents.hide();
        const selectedTab = $(this).data('tab');
        $('#harmonogram-' + selectedTab).show();

        if (selectedTab === 'list') {
            $('.navigation-buttons').show();
        } else {
            $('.navigation-buttons').hide();
            if (selectedTab === 'calendar') {
                renderCustomCalendar();
            }
        }
    });

    // Dodatkowa obsługa – przy najechaniu kursorem wyświetlamy dymek z opisem widoku
    tabButtons.on('mouseenter', function () {
        let message = "";
        const tab = $(this).data('tab');
        if (tab === "list") {
            message = "Widok listy";
        } else if (tab === "calendar") {
            message = "Widok kalendarza";
        } else if (tab === "table") {
            message = "Widok tabeli";
        }
        showHoverBubble(message, $(this));
    });

    // Dodanie obsługi klawiatury dla zakładek
    tabButtons.on('keydown', function (e) {
        let index = tabButtons.index(this);
        if (e.key === 'ArrowRight') {
            let next = tabButtons.eq(index + 1);
            if (!next.length) { next = tabButtons.first(); }
            next.focus();
            e.preventDefault();
        } else if (e.key === 'ArrowLeft') {
            let prev = tabButtons.eq(index - 1);
            if (!prev.length) { prev = tabButtons.last(); }
            prev.focus();
            e.preventDefault();
        }
    });

    // Inicjalizacja Autocomplete
    locationInput.autocomplete({
        minLength: 3,
        source: function (request, response) {
            $.ajax({
                url: harmonogramPlugin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'harmonogram_search_locations',
                    term: request.term,
                    nonce: harmonogramPlugin.nonce
                },
                success: function (data) {
                    if (data.success) {
                        response(data.data);
                    } else {
                        response([]);
                    }
                },
                error: function () {
                    response([]);
                }
            });
        },
        select: function (event, ui) {
            $(this).val(ui.item.value);
            fetchHarmonogram(ui.item.value);
            return false;
        }
    }).autocomplete("instance")._renderItem = function (ul, item) {
        return $("<li>")
            .append("<div>" + item.label + "</div>")
            .appendTo(ul);
    };

    // Główna funkcja AJAX pobierająca dane
    async function fetchHarmonogram(location) {
        if (!location) return;

        loadingMessage.show();
        downloadsDiv.hide();
        $('.harmonogram-tabs').hide();
        listContainer.empty();
        tableBody.empty();
        calendarData = [];
        messagesContainer.hide();

        try {
            const response = await $.ajax({
                url: harmonogramPlugin.ajax_url,
                method: 'POST',
                data: {
                    action: 'harmonogram_get_data',
                    location: location,
                    year: currentYear,
                    nonce: harmonogramPlugin.nonce
                }
            });

            loadingMessage.hide();

            if (response.success) {
                $('.harmonogram-tabs').show();
                downloadsDiv.show();

                linkIcal.attr(
                    'href',
                    `${harmonogramPlugin.ajax_url}?action=harmonogram_generate_ical&location=${encodeURIComponent(location)}&year=${encodeURIComponent(currentYear)}&nonce=${harmonogramPlugin.nonce}`
                );

                if (response.data.pdf_url) {
                    linkPdf.attr('href', response.data.pdf_url).show();
                } else {
                    linkPdf.hide();
                }

                if (Array.isArray(response.data.formatted_data)) {
                    calendarData = response.data.formatted_data;
                } else if (typeof response.data.formatted_data === 'object') {
                    calendarData = Object.values(response.data.formatted_data);
                } else {
                    alert('Otrzymano dane w nieprawidłowym formacie.');
                    return;
                }

                renderList(calendarData);
                renderTable(calendarData);
                renderCustomCalendar();
                renderMonthlyList(calendarData, selectedMonth);
                createNavigationButtons();

                messagesContainer.fadeIn(500);

                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            } else {
                alert(response.data.message || harmonogramPlugin.no_data_message);
            }
        } catch (error) {
            console.error('Błąd podczas pobierania danych:', error);
            loadingMessage.hide();
            alert(harmonogramPlugin.fetch_error_message);
        }
    }

    // Obsługa – wyświetlenie dymka po najechaniu na linki do pobierania
    linkIcal.on('mouseenter', function () {
        showHoverBubble("Pobierz iCal", $(this));
    });
    linkPdf.on('mouseenter', function () {
        showHoverBubble("Pobierz PDF", $(this));
    });

    // Render widoku LISTA
    function renderList(data) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const groupedData = data.reduce((acc, item) => {
            const dateObj = new Date(item.data);
            dateObj.setHours(0, 0, 0, 0);
            if (dateObj < today) return acc;
            const month = dateObj.toLocaleString('pl-PL', { month: 'long' });
            if (!acc[month]) acc[month] = [];
            acc[month].push(item);
            return acc;
        }, {});

        let html = '';

        for (const [month, items] of Object.entries(groupedData)) {
            html += `<h3 class="harmonogram-month-header">${capitalizeFirstLetter(month)}</h3>`;
            html += '<ul class="harmonogram-list" role="list">';

            items.forEach(item => {
                const dateObj  = new Date(item.data);
                const day      = dateObj.getDate();
                const diffDays = Math.ceil((dateObj - today) / (1000 * 60 * 60 * 24));
                const daysUntil = diffDays === 0 ? 'dziś' : `za ${diffDays} dni`;

                const consolidatedType = getConsolidatedWasteType(capitalizeFirstLetter(item.rodzaj_odpadow));
                const wasteColor       = wasteColors[consolidatedType] || wasteColors['Inny'];

                html += 
                    `<li class="harmonogram-list-item" role="listitem">
                        <div class="harmonogram-item-left">
                            <span class="harmonogram-date">${day} ${capitalizeFirstLetter(month)}</span>
                            <span class="harmonogram-days">${daysUntil}</span>
                        </div>
                        <div class="harmonogram-item-center">
                            ${consolidatedType}
                        </div>
                        <div class="harmonogram-item-right">
                            <div class="waste-indicator-container">
                                <span class="waste-indicator"
                                      style="background-color: ${wasteColor};"
                                      data-tooltip="${consolidatedType}"
                                      aria-hidden="true">
                                </span>
                            </div>
                        </div>
                    </li>`;
            });

            html += '</ul>';
        }

        listContainer.html(html);
        $('.navigation-buttons').show();
    }

    // Render widoku TABELA
    function renderTable(data) {
        const tableData = {};

        data.forEach(item => {
            const originalType = capitalizeFirstLetter(item.rodzaj_odpadow);
            const rodzaj       = getConsolidatedWasteType(originalType);
            const dateObj      = new Date(item.data);
            const month        = dateObj.getMonth();
            const day          = dateObj.getDate();

            if (!tableData[rodzaj]) {
                tableData[rodzaj] = Array.from({ length: 12 }, () => []);
            }
            tableData[rodzaj][month].push(day);
        });

        let html = '<thead><tr><th>Rodzaj Odpadów</th><th>Kolor</th>';
        const monthShortNames = ['Sty', 'Lut', 'Mar', 'Kwi', 'Maj', 'Cze', 'Lip', 'Sie', 'Wrz', 'Paź', 'Lis', 'Gru'];

        monthShortNames.forEach(month => {
            html += `<th>${month}</th>`;
        });
        html += '</tr></thead>';

        html += '<tbody>';
        Object.entries(tableData).forEach(([rodzaj, months]) => {
            const rowClass   = rodzaj.replace(/\s+/g, '-').toLowerCase();
            const wasteColor = wasteColors[rodzaj] || wasteColors['Inny'];

            html += 
                `<tr class="harmonogram-table-row ${rowClass}">
                    <td>${rodzaj}</td>
                    <td class="color-cell" style="background-color: ${wasteColor};"></td>`;
            months.forEach(days => {
                html += `<td>${days.length > 0 ? days.join(', ') : ''}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody>';

        $('#harmonogram-table-content').html(html);
        $('.navigation-buttons').hide();
    }

    // Render listy miesięcznej
    function renderMonthlyList(data, monthIndex) {
        const monthNames = [
            'Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec',
            'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'
        ];
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const filteredData = data.filter(item => {
            const itemDate = new Date(item.data);
            return (
                itemDate.getMonth() === monthIndex &&
                itemDate.getFullYear() === currentYear &&
                itemDate >= today
            );
        });

        let html = `<h3 class="harmonogram-month-header">${monthNames[monthIndex]}</h3>`;
        if (filteredData.length === 0) {
            html += '<p>Brak danych dla tego miesiąca.</p>';
        } else {
            html += '<ul class="harmonogram-list" role="list">';
            filteredData.forEach(item => {
                const dateObj  = new Date(item.data);
                const day      = dateObj.getDate();
                const diffDays = Math.ceil((dateObj - today) / (1000 * 60 * 60 * 24));

                let daysUntil = '';
                if (diffDays <= 12) {
                    daysUntil = diffDays === 0 ? 'dziś' : `za ${diffDays} dni`;
                } else {
                    daysUntil = dateObj.toLocaleString('pl-PL', { weekday: 'long' });
                }

                const consolidatedType = getConsolidatedWasteType(capitalizeFirstLetter(item.rodzaj_odpadow));
                const eventColor       = wasteColors[consolidatedType] || wasteColors['Inny'];

                html += 
                    `<li class="harmonogram-list-item" role="listitem">
                        <div class="harmonogram-item-left">
                            <span class="harmonogram-date">${day} ${monthNames[monthIndex]}</span>
                            <span class="harmonogram-days">${daysUntil}</span>
                        </div>
                        <div class="harmonogram-item-center">
                            ${consolidatedType}
                        </div>
                        <div class="harmonogram-item-right">
                            <div class="waste-indicator-container">
                                <span class="waste-indicator"
                                      style="background-color: ${eventColor};"
                                      data-tooltip="${consolidatedType}"
                                      aria-hidden="true">
                                </span>
                            </div>
                        </div>
                    </li>`;
            });
            html += '</ul>';
        }

        listContainer.html(html);
        $('.navigation-buttons').show();
    }

    // Tworzenie przycisków nawigacyjnych dla listy
    function createNavigationButtons() {
        $('.navigation-buttons').remove();

        const navigationContainer = $('<div>', { class: 'navigation-buttons' });
        const prevButton = $('<button>', { text: 'Poprzedni miesiąc', class: 'btn prev-month' });
        const nextButton = $('<button>', { text: 'Następny miesiąc', class: 'btn next-month' });

        prevButton.on('click', function () {
            // Usuwamy dymek (topline) przy kliknięciu
            $('.hover-bubble').remove();
            selectedMonth = (selectedMonth - 1 + 12) % 12;
            selectedDate.setMonth(selectedMonth);
            renderMonthlyList(calendarData, selectedMonth);
        });

        nextButton.on('click', function () {
            $('.hover-bubble').remove();
            selectedMonth = (selectedMonth + 1) % 12;
            selectedDate.setMonth(selectedMonth);
            renderMonthlyList(calendarData, selectedMonth);
        });

        // Usunięto zdarzenia mouseenter – tooltipy nie będą wyświetlane przy najechaniu
        // prevButton.on('mouseenter', function () {
        //     showHoverBubble("Poprzedni miesiąc", $(this));
        // });
        // nextButton.on('mouseenter', function () {
        //     showHoverBubble("Następny miesiąc", $(this));
        // });

        navigationContainer.append(prevButton, nextButton);
        listContainer.after(navigationContainer);

        if ($('.harmonogram-tab-buttons .tab-btn.active').data('tab') === 'list') {
            navigationContainer.show();
        }
    }

    // Render kalendarza z rolami ARIA
    function renderCustomCalendar() {
        if (!calendarEl.length) return;

        const currentMonth    = selectedDate.getMonth();
        const currentYear     = selectedDate.getFullYear();
        const daysInMonth     = new Date(currentYear, currentMonth + 1, 0).getDate();
        const firstDayOfMonth = new Date(currentYear, currentMonth, 1).getDay();
        const monthYearText   = `${selectedDate.toLocaleString('pl-PL', { month: 'long' })} ${currentYear}`;
        const daysOfWeek      = ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Niedz'];
        const adjustedFirstDayIndex = (firstDayOfMonth === 0 ? 6 : firstDayOfMonth - 1);

        calendarEl.empty();

        // Nagłówek kalendarza
        const header = $('<div>', { class: 'calendar-header' });
        const prevBtn = $('<button>', { 
            text: 'Poprzedni',
            'aria-label': 'Poprzedni miesiąc'
        }).on('click', () => {
            $('.hover-bubble').remove();
            selectedDate.setMonth(selectedDate.getMonth() - 1);
            selectedMonth = selectedDate.getMonth();
            renderCustomCalendar();
        });
        const nextBtn = $('<button>', { 
            text: 'Następny',
            'aria-label': 'Następny miesiąc'
        }).on('click', () => {
            $('.hover-bubble').remove();
            selectedDate.setMonth(selectedDate.getMonth() + 1);
            selectedMonth = selectedDate.getMonth();
            renderCustomCalendar();
        });
        // Usunięto zdarzenia mouseenter – tooltipy nie będą wyświetlane przy najechaniu
        // prevBtn.on('mouseenter', function () {
        //     showHoverBubble("Poprzedni miesiąc", $(this));
        // });
        // nextBtn.on('mouseenter', function () {
        //     showHoverBubble("Następny miesiąc", $(this));
        // });
        const monthYearDiv = $('<div>', { class: 'month-year' }).text(monthYearText);

        header.append(prevBtn, monthYearDiv, nextBtn);
        calendarEl.append(header);

        // Siatka kalendarza z rolą grid
        const grid = $('<div>', { class: 'calendar-grid', role: 'grid' });
        daysOfWeek.forEach(day => {
            const dayHeader = $('<div>', { class: 'calendar-day-header', role: 'columnheader' }).text(day);
            grid.append(dayHeader);
        });

        // Puste komórki przed pierwszym dniem
        for (let i = 0; i < adjustedFirstDayIndex; i++) {
            const emptyCell = $('<div>', { class: 'calendar-day empty', role: 'gridcell' });
            grid.append(emptyCell);
        }

        // Komórki dni
        for (let day = 1; day <= daysInMonth; day++) {
            const dayCell = $('<div>', { 
                class: 'calendar-day',
                role: 'gridcell',
                tabindex: 0
            });
            const dayNumber = $('<div>', { class: 'day-number' }).text(day);
            dayCell.append(dayNumber);

            const eventsForDay = calendarData.filter(item => {
                const eventDate = new Date(item.data);
                return (
                    eventDate.getFullYear() === currentYear &&
                    eventDate.getMonth() === currentMonth &&
                    eventDate.getDate() === day
                );
            });

            if (eventsForDay.length > 0) {
                dayCell.addClass('has-waste');

                const uniqueWasteTypes = [
                    ...new Set(
                        eventsForDay.map(item =>
                            getConsolidatedWasteType(capitalizeFirstLetter(item.rodzaj_odpadow))
                        )
                    )
                ];

                let bottomBarColor;
                if (uniqueWasteTypes.length === 1) {
                    bottomBarColor = wasteColors[uniqueWasteTypes[0]] || wasteColors['Inny'];
                } else {
                    bottomBarColor = wasteColors['Odpady Zmieszane'];
                }
                dayCell.css('border-bottom', `4px solid ${bottomBarColor}`);

                const tooltipText = eventsForDay
                    .map(event => `- ${capitalizeFirstLetter(event.rodzaj_odpadow)}`)
                    .join(', ');

                dayCell.attr('aria-label', `${day} ${monthYearText}. ${tooltipText}`);
                dayCell.attr('data-tooltip', tooltipText);

                const indicatorContainer = $('<div>', { class: 'waste-indicator-container' });
                uniqueWasteTypes.forEach(type => {
                    const eventColor = wasteColors[type] || wasteColors['Inny'];
                    const indicator = $('<span>', {
                        class: 'waste-indicator',
                        style: `background-color: ${eventColor};`,
                        'data-tooltip': type,
                        'aria-hidden': 'true'
                    });
                    indicatorContainer.append(indicator);
                });
                dayCell.append(indicatorContainer);
            }

            grid.append(dayCell);
        }

        // Puste komórki po ostatnim dniu
        const totalCells = adjustedFirstDayIndex + daysInMonth;
        const remainingCells = totalCells % 7 !== 0 ? 7 - (totalCells % 7) : 0;
        for (let i = 0; i < remainingCells; i++) {
            const emptyCell = $('<div>', { class: 'calendar-day empty', role: 'gridcell' });
            grid.append(emptyCell);
        }

        calendarEl.append(grid);
        $('.navigation-buttons').hide();
    }

    // Obsługa tooltipa – myszką dla wskaźników i elementów listy (istniejący mechanizm)
    const $tooltip = $('<div class="custom-tooltip"></div>').appendTo('body');
    $(document).on('mouseenter', '.calendar-day.has-waste, .waste-indicator, .harmonogram-list-item .waste-indicator', function () {
        const text = $(this).attr('data-tooltip') || '';
        $tooltip.text(text).addClass('show');
    });
    $(document).on('mousemove', '.calendar-day.has-waste, .waste-indicator, .harmonogram-list-item .waste-indicator', function (e) {
        const offset = 10;
        $tooltip.css({
            top: e.pageY + offset + 'px',
            left: e.pageX + offset + 'px'
        });
    });
    $(document).on('mouseleave', '.calendar-day.has-waste, .waste-indicator, .harmonogram-list-item .waste-indicator', function () {
        $tooltip.removeClass('show');
    });

    // Obsługa tooltipa przy fokusowaniu klawiaturą
    $(document).on('focus', '.calendar-day[tabindex="0"]', function () {
        const text = $(this).attr('data-tooltip') || '';
        $tooltip.text(text).addClass('show');
    });
    $(document).on('blur', '.calendar-day[tabindex="0"]', function () {
        $tooltip.removeClass('show');
    });
});
