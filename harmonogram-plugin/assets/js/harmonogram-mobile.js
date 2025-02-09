jQuery(document).ready(function ($) {
    'use strict';

    // Informacja o załadowaniu pliku JS (dla celów debugowania)
    console.log('harmonogram-mobile.js załadowany');

    let currentData = [];
    let monthList = [];
    let currentMonthIndex = 0;
    const currentYear = new Date().getFullYear();

    /**
     * Mapa typów odpadów do klas CSS, nazw wyświetlanych i kolorów
     */
    const wasteTypeMap = {
        "Odpady Zmieszane": { class: "zmieszane", display: "Zmieszane", color: "#000000" },
        "Szkło": { class: "szklo", display: "Szkło", color: "#2C6E49" },
        "Tworzywa sztuczne, metale, opakowania wielomateriałowe": { class: "plastik", display: "Plastik", color: "#FCA311" },
        "Papier": { class: "papier", display: "Papier", color: "#1E88E5" },
        "Odpady Biodegradowalne": { class: "bio", display: "Bio", color: "#603808" }
        // Dodaj inne typy odpadów tutaj, jeśli są
    };

    /**
     * Główna funkcja pobierająca dane harmonogramu (AJAX).
     */
    async function fetchHarmonogram(location) {
        const $listContentDiv = $('.harmonogram-mobile-content');

        if (!location) {
            $listContentDiv.hide();
            $('.download-ical-button').remove(); // Usuń przycisk, jeśli lokalizacja jest odznaczona
            return;
        }

        setLoadingState($listContentDiv);

        try {
            const response = await $.ajax({
                url: harmonogramMobile.ajax_url,
                method: 'POST',
                data: {
                    action: 'harmonogram_get_data',
                    location: location,
                    year: currentYear,
                    nonce: harmonogramMobile.nonce
                }
            });

            handleResponse(response, $listContentDiv, location);
        } catch (error) {
            console.error('Błąd podczas pobierania danych:', error);
            displayErrorMessage($listContentDiv);
        }
    }

    /**
     * Ustaw stan ładowania – komunikat z aria-live
     */
    function setLoadingState($listContentDiv) {
        $listContentDiv.find('.harmonogram-mobile-list')
            .html('<p aria-live="polite">Ładowanie...</p>');
        $listContentDiv.show();
    }

    /**
     * Obsługa odpowiedzi AJAX
     */
    function handleResponse(response, $listContentDiv, location) {
        if (response.success && Array.isArray(response.data.formatted_data)) {
            currentData = filterFutureEntries(response.data.formatted_data);
            monthList = getUniqueMonths(currentData);

            if (currentData.length > 0) {
                currentMonthIndex = 0;
                renderCurrentMonth();
            } else {
                displayNoDataMessage($listContentDiv);
                $('.download-ical-button').remove(); // Usuń przycisk, jeśli brak danych
            }
        } else {
            displayNoDataMessage($listContentDiv);
            $('.download-ical-button').remove(); // Usuń przycisk, jeśli wystąpił błąd
        }
    }

    /**
     * Filtrowanie dat – tylko przyszłe wydarzenia
     */
    function filterFutureEntries(data) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return data.filter(item => new Date(item.data) >= today);
    }

    /**
     * Pobieranie unikalnych miesięcy z zestawu danych
     */
    function getUniqueMonths(data) {
        const months = data.map(item => {
            const date = new Date(item.data);
            return date.toLocaleString('pl-PL', { month: 'long', year: 'numeric' });
        });
        return [...new Set(months)];
    }

    /**
     * Renderowanie bieżącego miesiąca
     */
    function renderCurrentMonth() {
        const currentMonth = monthList[currentMonthIndex];
        const filteredData = currentData.filter(item => {
            const date = new Date(item.data);
            const monthString = date.toLocaleString('pl-PL', { month: 'long', year: 'numeric' });
            return monthString === currentMonth;
        });

        renderAddToCalendarButton();
        renderMonthHeader();
        renderHarmonogramList(filteredData);
        renderMonthNavigation();
    }

    /**
     * Dodanie przycisku do pobrania iCal – z aria-label
     */
    function renderAddToCalendarButton() {
        $('.download-ical-button').remove(); // Usuń istniejący przycisk, jeśli istnieje

        const location = $('#harmonogram-mobile-location-filter').val();
        const year = currentYear; // Można rozszerzyć o wybór roku

        if (!location) {
            return; // Nie pokazuj przycisku, jeśli lokalizacja nie jest wybrana
        }

        // Tworzenie URL do pobrania iCal
        const icalUrl = `${harmonogramMobile.ajax_url}?action=harmonogram_generate_ical&location=${encodeURIComponent(location)}&year=${year}&nonce=${harmonogramMobile.nonce}`;

        const $button = $(`
            <div class="download-ical-button">
                <a href="${icalUrl}" class="button" target="_blank" aria-label="Dodaj do swojego kalendarza">
                    Dodaj do swojego kalendarza
                </a>
            </div>
        `);

        // Umieszczenie przycisku bezpośrednio pod filtrem miejscowości
        $('.harmonogram-dashboard .harmonogram-filters').after($button);
    }

    /**
     * Renderowanie nagłówka bieżącego miesiąca z rolą heading
     */
    function renderMonthHeader() {
        $('.month-header-mobile').remove();
        const $header = $(`
            <div class="month-header-mobile" role="heading" aria-level="2">
                <span class="current-month-mobile">${monthList[currentMonthIndex]}</span>
            </div>
        `);
        $('.harmonogram-mobile-content').prepend($header);
    }

    /**
     * Renderowanie przycisków nawigacyjnych – z rolą navigation
     */
    function renderMonthNavigation() {
        $('.month-navigation-mobile').remove();
        const $navigation = $(`
            <div class="month-navigation-mobile" role="navigation" aria-label="Nawigacja miesięczna">
                <button class="prev-month-mobile" ${currentMonthIndex === 0 ? 'disabled' : ''}>Poprzedni</button>
                <button class="next-month-mobile" ${currentMonthIndex === monthList.length - 1 ? 'disabled' : ''}>Następny</button>
            </div>
        `);

        $navigation.find('.prev-month-mobile').on('click', () => {
            if (currentMonthIndex > 0) {
                currentMonthIndex--;
                renderCurrentMonth();
            }
        });

        $navigation.find('.next-month-mobile').on('click', () => {
            if (currentMonthIndex < monthList.length - 1) {
                currentMonthIndex++;
                renderCurrentMonth();
            }
        });

        $('.harmonogram-mobile-content').append($navigation);
    }

    /**
     * Renderowanie listy harmonogramu
     */
    function renderHarmonogramList(data) {
        const $listContainer = $('.harmonogram-mobile-list');
        $listContainer.empty();

        data.forEach(item => {
            const dateObj = new Date(item.data);
            const daysUntil = calculateDaysUntil(dateObj);

            // Pobranie informacji o typie odpadu
            const wasteInfo = wasteTypeMap[item.rodzaj_odpadow] || { class: "default", display: "Inny", color: "#CCCCCC" };

            const $listItem = $(`
                <li class="harmonogram-mobile-item ${wasteInfo.class}" role="listitem" style="border-left: 5px solid ${wasteInfo.color};">
                    <div class="harmonogram-mobile-date">
                        ${formatDate(dateObj)}
                    </div>
                    <div class="harmonogram-mobile-info">
                        <div class="waste-type" style="color: ${wasteInfo.color}; font-weight: bold;">${wasteInfo.display}</div>
                        <div class="days-until">${daysUntil}</div>
                    </div>
                    <div class="harmonogram-mobile-icon"></div>
                </li>
            `);

            $listContainer.append($listItem);
        });
    }

    /**
     * Formatowanie daty (DD MMM)
     */
    function formatDate(dateObj) {
        const day = dateObj.getDate().toString().padStart(2, '0');
        const monthShort = dateObj
            .toLocaleString('pl-PL', { month: 'short' })
            .replace(/\./g, '');
        return `<span class="day">${day}</span><span class="month">${monthShort}</span>`;
    }

    /**
     * Obliczanie, ile dni pozostało lub zwrócenie nazwy dnia tygodnia
     */
    function calculateDaysUntil(dateObj) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const diffTime = dateObj - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays === 0) return 'Dziś';
        if (diffDays === 1) return 'Jutro';
        if (diffDays > 1 && diffDays <= 14) return `Za ${diffDays} dni`;

        const options = { weekday: 'long' };
        return dateObj.toLocaleDateString('pl-PL', options);
    }

    /**
     * Komunikat o braku danych (aria-live)
     */
    function displayNoDataMessage($listContentDiv) {
        $listContentDiv.find('.harmonogram-mobile-list')
            .html('<p aria-live="polite">Brak danych do wyświetlenia.</p>');
        $listContentDiv.show();
    }

    /**
     * Komunikat o błędzie (aria-live)
     */
    function displayErrorMessage($listContentDiv) {
        $listContentDiv.find('.harmonogram-mobile-list')
            .html('<p aria-live="polite">Wystąpił błąd podczas pobierania danych.</p>');
        $listContentDiv.show();
    }

    /**
     * Zdarzenie zmiany w selekcie (wybór miejscowości)
     */
    $('#harmonogram-mobile-location-filter').on('change', function () {
        const selectedLocation = $(this).val();
        fetchHarmonogram(selectedLocation);
    });

    /**
     * Wywołanie przy pierwszym załadowaniu strony
     */
    fetchHarmonogram($('#harmonogram-mobile-location-filter').val());
});
