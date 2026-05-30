// assets/js/datepicker.js
(function () {
    const style = document.createElement('style');
    style.textContent = `
        .datepicker-popup {
            position: absolute;
            top: 0;
            left: 0;
            min-width: 320px;
            max-width: calc(100vw - 2rem);
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1rem;
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.18);
            padding: 1rem;
            z-index: 9999;
            display: none;
        }
        .datepicker-popup.visible {
            display: block;
        }
        .datepicker-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        .datepicker-title {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f172a;
        }
        .datepicker-btn {
            background: transparent;
            border: none;
            color: #4f46e5;
            font-size: 1.1rem;
            cursor: pointer;
            padding: 0.35rem;
            border-radius: 9999px;
            transition: background 0.2s ease;
        }
        .datepicker-btn:hover {
            background: rgba(79, 70, 229, 0.1);
        }
        .datepicker-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 0.35rem;
        }
        .datepicker-label,
        .datepicker-day {
            text-align: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: #6b7280;
        }
        .datepicker-day {
            min-height: 38px;
            line-height: 38px;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            background: transparent;
            color: #334155;
        }
        .datepicker-day:hover:not(.disabled) {
            background: #eef2ff;
            color: #312e81;
        }
        .datepicker-day.disabled {
            cursor: default;
            color: #cbd5e1;
        }
        .datepicker-day.selected {
            background: #4f46e5;
            color: #ffffff;
            box-shadow: 0 10px 30px rgba(79, 70, 229, 0.18);
        }
        .datepicker-day.today {
            box-shadow: inset 0 0 0 1px rgba(79, 70, 229, 0.25);
        }
        .datepicker-wrapper {
            position: relative;
            display: inline-flex;
            width: 100%;
        }
        .datepicker-display {
            width: 100%;
            cursor: pointer;
            background: #ffffff;
        }
        .datepicker-display:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.14);
        }
    `;
    document.head.appendChild(style);

    const popup = document.createElement('div');
    popup.className = 'datepicker-popup';
    popup.innerHTML = `
        <div class="datepicker-header">
            <button type="button" class="datepicker-btn" data-datepicker-nav="prev" aria-label="เดือนก่อนหน้า">‹</button>
            <h3 class="datepicker-title"></h3>
            <button type="button" class="datepicker-btn" data-datepicker-nav="next" aria-label="เดือนถัดไป">›</button>
        </div>
        <div class="datepicker-grid" aria-hidden="true"></div>
    `;
    document.body.appendChild(popup);

    const dayNames = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
    const monthNames = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

    const state = {
        input: null,
        display: null,
        currentMonth: new Date(),
        minDate: null,
        maxDate: null,
    };

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function toIso(date) {
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    function formatDisplay(date) {
        return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
    }

    function dateFromValue(value) {
        if (!value) return null;
        const parsed = new Date(value);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function isWithinRange(date) {
        if (state.minDate && date < state.minDate) return false;
        if (state.maxDate && date > state.maxDate) return false;
        return true;
    }

    function renderCalendar() {
        if (!state.input || !state.display) return;

        const selected = dateFromValue(state.input.value);
        const today = new Date();
        const month = state.currentMonth.getMonth();
        const year = state.currentMonth.getFullYear();
        const firstDayOfMonth = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        const title = popup.querySelector('.datepicker-title');
        const grid = popup.querySelector('.datepicker-grid');
        title.textContent = `${monthNames[month]} ${year}`;

        let html = '';

        dayNames.forEach((dayName) => {
            html += `<div class="datepicker-label">${dayName}</div>`;
        });

        for (let i = 0; i < firstDayOfMonth; i += 1) {
            html += '<div></div>';
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const current = new Date(year, month, day);
            const isToday = current.toDateString() === today.toDateString();
            const isSelected = selected && current.toDateString() === selected.toDateString();
            const disabled = !isWithinRange(current);
            const classes = ['datepicker-day'];
            if (isToday) classes.push('today');
            if (isSelected) classes.push('selected');
            if (disabled) classes.push('disabled');

            html += `<button type="button" class="${classes.join(' ')}" data-datepicker-day="${day}" ${disabled ? 'disabled' : ''}>${day}</button>`;
        }

        grid.innerHTML = html;
    }

    function positionPopup() {
        if (!state.display) return;

        const rect = state.display.getBoundingClientRect();
        popup.style.top = `${rect.bottom + window.scrollY + 8}px`;

        const left = rect.left + window.scrollX;
        const maxLeft = document.documentElement.clientWidth - popup.offsetWidth - 12;
        popup.style.left = `${Math.max(12, Math.min(left, maxLeft))}px`;
    }

    function openPicker(display, originalInput) {
        state.input = originalInput;
        state.display = display;
        state.minDate = dateFromValue(originalInput.getAttribute('min'));
        state.maxDate = dateFromValue(originalInput.getAttribute('max'));

        const selected = dateFromValue(originalInput.value) || new Date();
        state.currentMonth = new Date(selected.getFullYear(), selected.getMonth(), 1);

        renderCalendar();
        positionPopup();
        popup.classList.add('visible');
    }

    function closePicker() {
        popup.classList.remove('visible');
        state.input = null;
        state.display = null;
    }

    function enhanceDateInput(input) {
        if (input.dataset.datepickerEnhanced) return;
        if (input.type !== 'date') return;

        input.dataset.datepickerEnhanced = '1';
        input.style.position = 'absolute';
        input.style.opacity = '0';
        input.style.pointerEvents = 'none';
        input.style.width = '0';
        input.style.height = '0';

        const wrapper = document.createElement('div');
        wrapper.className = 'datepicker-wrapper';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const display = document.createElement('input');
        display.type = 'text';
        display.readOnly = true;
        display.placeholder = input.placeholder || 'เลือกวันที่';
        display.className = `${input.className || ''} datepicker-display`;
        display.autocomplete = 'off';
        display.value = input.value ? formatDisplay(new Date(input.value)) : '';
        wrapper.insertBefore(display, input);

        display.addEventListener('click', function (event) {
            event.stopPropagation();
            openPicker(display, input);
        });

        input.addEventListener('change', function () {
            display.value = input.value ? formatDisplay(new Date(input.value)) : '';
        });
    }

    function initDatepickers() {
        document.querySelectorAll('input[type="date"]').forEach(enhanceDateInput);
    }

    document.addEventListener('DOMContentLoaded', initDatepickers);
    document.addEventListener('click', function (event) {
        if (!popup.contains(event.target) && state.display && event.target !== state.display) {
            closePicker();
        }
    });

    window.addEventListener('resize', function () {
        if (popup.classList.contains('visible')) {
            positionPopup();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && popup.classList.contains('visible')) {
            closePicker();
        }
    });

    popup.addEventListener('click', function (event) {
        event.stopPropagation();
        const nav = event.target.closest('[data-datepicker-nav]');
        if (nav) {
            const direction = nav.dataset.datepickerNav;
            state.currentMonth.setMonth(state.currentMonth.getMonth() + (direction === 'next' ? 1 : -1));
            renderCalendar();
            return;
        }

        const dayButton = event.target.closest('[data-datepicker-day]');
        if (dayButton && state.input) {
            const day = Number(dayButton.dataset.datepickerDay);
            const selected = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth(), day);
            state.input.value = toIso(selected);
            state.input.dispatchEvent(new Event('change', { bubbles: true }));
            state.display.value = formatDisplay(selected);
            closePicker();
        }
    });
})();
