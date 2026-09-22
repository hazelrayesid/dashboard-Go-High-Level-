const matchesFilter = (key, filter) => {
    if (filter === 'all') {
        return true;
    }

    if (filter === 'remaining') {
        return key.includes('remaining') || key.includes('top4-signup') || key.includes('crazy-domains');
    }

    return key.includes(filter);
};

const currentDateRange = () => {
    const params = new URLSearchParams(window.location.search);

    return {
        from: params.get('from') || null,
        to: params.get('to') || null,
    };
};

const matchesDateRange = (date) => {
    const range = currentDateRange();

    if (! range.from && ! range.to) {
        return true;
    }

    if (! date) {
        return false;
    }

    return (! range.from || date >= range.from) && (! range.to || date <= range.to);
};

const updateVisibleCount = () => {
    const visibleRows = Array.from(document.querySelectorAll('[data-company-row]')).filter((row) => ! row.hidden);
    const counter = document.querySelector('[data-visible-count]');
    const emptyQueue = document.querySelector('[data-empty-queue]');

    if (counter) {
        counter.textContent = `${visibleRows.length} visible records`;
    }

    if (emptyQueue) {
        emptyQueue.hidden = visibleRows.length > 0;
        emptyQueue.classList.toggle('hidden', visibleRows.length > 0);
    }
};

const setActiveFilter = (filter) => {
    document.querySelectorAll('[data-filter-button]').forEach((button) => {
        button.dataset.active = String(button.dataset.filterButton === filter);
    });
};

const applyFilter = (filter) => {
    setActiveFilter(filter);

    document.querySelectorAll('[data-company-row]').forEach((row) => {
        const key = row.dataset.filterKey ?? '';
        const createdDate = row.dataset.createdDate ?? '';

        row.hidden = ! matchesFilter(key, filter) || ! matchesDateRange(createdDate);
    });

    document.querySelectorAll('[data-segment-section]').forEach((section) => {
        const sectionKey = section.dataset.filterKey ?? '';

        section.hidden = ! matchesFilter(sectionKey, filter);
    });

    document.querySelectorAll('[data-queue-section]').forEach((section) => {
        const sectionKey = section.dataset.filterKey ?? '';
        const visibleRows = Array.from(section.querySelectorAll('[data-company-row]')).filter((row) => ! row.hidden);

        section.hidden = ! matchesFilter(sectionKey, filter) && visibleRows.length === 0;
    });

    updateVisibleCount();
};

const setSidebarCollapsed = (isCollapsed) => {
    const shell = document.querySelector('[data-dashboard-shell]');
    const sidebar = document.querySelector('[data-sidebar]');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const openIcon = document.querySelector('[data-sidebar-open-icon]');
    const closedIcon = document.querySelector('[data-sidebar-closed-icon]');
    const mobilePanel = document.querySelector('[data-mobile-sidebar-panel]');
    const isDesktop = window.matchMedia('(min-width: 1280px)').matches;

    if (! isDesktop) {
        sidebar?.classList.toggle('-translate-x-56', ! isCollapsed);
        sidebar?.classList.toggle('translate-x-0', isCollapsed);
        mobilePanel?.classList.toggle('hidden', ! isCollapsed);
        mobilePanel?.classList.toggle('flex', isCollapsed);

        document.querySelectorAll('[data-sidebar-label], [data-sidebar-content]').forEach((element) => {
            element.classList.toggle('hidden', ! isCollapsed);
            element.classList.remove('xl:hidden');
        });

        openIcon?.classList.toggle('hidden', ! isCollapsed);
        closedIcon?.classList.toggle('hidden', isCollapsed);
        toggle?.setAttribute('aria-expanded', String(isCollapsed));

        return;
    }

    sidebar?.classList.remove('-translate-x-56', 'translate-x-0');
    mobilePanel?.classList.remove('hidden');
    mobilePanel?.classList.add('flex');

    document.querySelectorAll('[data-sidebar-label], [data-sidebar-content]').forEach((element) => {
        element.classList.remove('hidden');
    });

    shell?.classList.toggle('xl:grid-cols-[80px_minmax(0,1fr)]', isCollapsed);
    shell?.classList.toggle('xl:grid-cols-[288px_minmax(0,1fr)]', ! isCollapsed);
    sidebar?.classList.toggle('xl:w-20', isCollapsed);

    document.querySelectorAll('[data-sidebar-label], [data-sidebar-content]').forEach((element) => {
        element.classList.toggle('xl:hidden', isCollapsed);
    });

    document.querySelectorAll('[data-sidebar-compact]').forEach((element) => {
        element.classList.toggle('hidden', ! isCollapsed);
    });

    openIcon?.classList.toggle('hidden', isCollapsed);
    closedIcon?.classList.toggle('hidden', ! isCollapsed);

    if (toggle) {
        toggle.setAttribute('aria-expanded', String(! isCollapsed));
    }

    window.localStorage.setItem('companyDashboard.sidebarCollapsed', String(isCollapsed));
};

const applyTheme = (theme) => {
    const isDark = theme === 'dark';
    const lightIcon = document.querySelector('[data-theme-light-icon]');
    const darkIcon = document.querySelector('[data-theme-dark-icon]');

    document.documentElement.classList.toggle('dark', isDark);
    lightIcon?.classList.toggle('hidden', isDark);
    darkIcon?.classList.toggle('hidden', ! isDark);
    window.localStorage.setItem('companyDashboard.theme', theme);
};

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const formatCalendarDate = (date) => {
    const parsed = new Date(`${date}T00:00:00`);

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(parsed);
};

const calendarCompare = (events) => events
    .flatMap((event) => event.attendee_matches ?? [])
    .reduce((carry, attendee) => ({
        matched: carry.matched + (attendee.matched ? 1 : 0),
        unmatched: carry.unmatched + (attendee.matched ? 0 : 1),
    }), { matched: 0, unmatched: 0 });

const calendarEventCard = (event) => {
    const attendees = event.attendee_matches?.length
        ? `<div class="mt-2 flex flex-wrap gap-1.5" title="${escapeHtml(event.attendees_title ?? '')}">
            ${event.attendee_matches.map((attendee) => {
                const matched = Boolean(attendee.matched);
                const chipClass = matched
                    ? 'border-teal-200 bg-teal-50 text-teal-800 dark:border-teal-500/30 dark:bg-teal-500/10 dark:text-teal-200'
                    : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200';
                const matchCount = Number(attendee.match_count || (matched ? 1 : 0));
                const businesses = (attendee.businesses ?? []).filter(Boolean).slice(0, 3).join(', ');
                const title = matched
                    ? `Found in GHL (${matchCount})${businesses ? `: ${businesses}` : (attendee.business ? `: ${attendee.business}` : '')}`
                    : 'Not found in synced GHL contacts';
                const label = `${attendee.label}${matched ? ` (${matchCount})` : ''}`;

                return `<span title="${escapeHtml(title)}" class="max-w-full truncate rounded-md border px-2 py-1 text-[11px] font-medium ${chipClass}">${escapeHtml(label)}</span>`;
            }).join('')}
        </div>`
        : ((event.attendees ?? []).length
            ? `<div class="mt-2 flex flex-wrap gap-1.5" title="${escapeHtml(event.attendees_title ?? '')}">
                ${(event.attendees ?? []).map((attendee) => `<span class="max-w-full truncate rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-medium text-slate-600 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-300">${escapeHtml(attendee)}</span>`).join('')}
            </div>`
            : '');
    const meetLink = event.meeting_link
        ? `<a href="${escapeHtml(event.meeting_link)}" target="_blank" rel="noreferrer" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-teal-500 dark:hover:text-teal-300">Meet</a>`
        : '';
    const openLink = event.link
        ? `<a href="${escapeHtml(event.link)}" target="_blank" rel="noreferrer" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-sky-300 hover:text-sky-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-sky-500 dark:hover:text-sky-300">Open</a>`
        : '';

    return `<article class="grid gap-3 px-3 py-3 sm:grid-cols-[64px_minmax(0,1fr)_auto] sm:items-center sm:px-4">
        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-center dark:border-slate-800 dark:bg-slate-950/70">
            <p class="text-[11px] font-semibold uppercase text-slate-500 dark:text-slate-400">${escapeHtml(event.day)}</p>
            <p class="mt-1 text-sm font-semibold text-slate-950 dark:text-white">${escapeHtml(event.date)}</p>
        </div>
        <div class="min-w-0">
            <h5 class="truncate text-sm font-semibold text-slate-950 dark:text-white">${escapeHtml(event.title)}</h5>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">${escapeHtml(event.time)} &middot; ${escapeHtml(event.calendar)}</p>
            ${attendees}
        </div>
        <div class="flex items-center gap-2">${meetLink}${openLink}</div>
    </article>`;
};

const calendarAsideCard = (event) => `<div class="rounded-md border border-slate-200 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-center justify-between gap-3">
        <p class="truncate text-xs font-semibold text-slate-950 dark:text-white">${escapeHtml(event.title)}</p>
        <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">${escapeHtml(event.date)}</span>
    </div>
    <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">${escapeHtml(event.time)}</p>
</div>`;

const setCalendarDayState = (root, selectedDate) => {
    const selectedClasses = ['border-teal-600', 'bg-teal-600', 'text-white', 'shadow-sm', 'dark:border-teal-400', 'dark:bg-teal-500', 'dark:text-slate-950'];
    const rangeClasses = ['border-teal-200', 'bg-teal-50', 'text-teal-800', 'dark:border-teal-500/30', 'dark:bg-teal-500/10', 'dark:text-teal-100'];
    const monthClasses = ['border-slate-200', 'bg-white', 'text-slate-700', 'dark:border-slate-800', 'dark:bg-slate-900', 'dark:text-slate-200'];
    const mutedClasses = ['border-transparent', 'text-slate-300', 'dark:text-slate-700'];

    root.querySelectorAll('[data-calendar-day]').forEach((day) => {
        day.classList.remove(...selectedClasses, ...rangeClasses, ...monthClasses, ...mutedClasses);

        if (day.dataset.calendarDay === selectedDate) {
            day.classList.add(...selectedClasses);
        } else if (day.dataset.calendarInRange === '1') {
            day.classList.add(...rangeClasses);
        } else if (day.dataset.calendarCurrentMonth === '1') {
            day.classList.add(...monthClasses);
        } else {
            day.classList.add(...mutedClasses);
        }

        const dot = day.querySelector('span');
        dot?.classList.toggle('bg-white', day.dataset.calendarDay === selectedDate);
        dot?.classList.toggle('dark:bg-slate-950', day.dataset.calendarDay === selectedDate);
        dot?.classList.toggle('bg-teal-500', day.dataset.calendarDay !== selectedDate);
    });
};

const updateCalendarUrl = (date, page) => {
    const url = new URL(window.location.href);

    if (date) {
        url.searchParams.set('calendar_date', date);
        url.searchParams.set('calendar_month', date.slice(0, 7));
    } else {
        url.searchParams.delete('calendar_date');
    }

    url.searchParams.set('calendar_page', String(page));
    window.history.pushState({}, '', url);
};

const renderCalendar = (root, selectedDate = null, page = 1, updateUrl = true) => {
    const events = JSON.parse(root.querySelector('[data-calendar-events-json]')?.textContent || '[]');
    const perPage = Number(root.dataset.calendarEventsPerPage || 6);
    const visibleEvents = selectedDate ? events.filter((event) => event.date_key === selectedDate) : events;
    const lastPage = Math.max(Math.ceil(visibleEvents.length / perPage), 1);
    const currentPage = Math.min(Math.max(page, 1), lastPage);
    const pageEvents = visibleEvents.slice((currentPage - 1) * perPage, currentPage * perPage);
    const compare = calendarCompare(visibleEvents);
    const label = selectedDate
        ? formatCalendarDate(selectedDate)
        : (root.dataset.calendarRangeLabel || root.dataset.calendarMonthLabel || '');

    root.dataset.calendarSelectedDate = selectedDate || '';
    root.querySelector('[data-calendar-selected-total]').textContent = String(visibleEvents.length);
    root.querySelector('[data-calendar-summary]').textContent = `${label} - ${visibleEvents.length} unique events${compare.matched + compare.unmatched > 0 ? ` - ${compare.matched} in GHL - ${compare.unmatched} new` : ''}`;

    const list = root.querySelector('[data-calendar-list]');
    const empty = root.querySelector('[data-calendar-empty]');
    list.innerHTML = pageEvents.map(calendarEventCard).join('');
    list.classList.toggle('hidden', pageEvents.length === 0);
    empty.classList.toggle('hidden', pageEvents.length > 0);

    const pagination = root.querySelector('[data-calendar-pagination]');
    pagination.classList.toggle('hidden', lastPage <= 1);
    pagination.innerHTML = `<p class="text-xs text-slate-500 dark:text-slate-400">Page ${currentPage} of ${lastPage} - ${perPage} per page</p>
        <div class="flex items-center gap-2">
            ${currentPage > 1
                ? `<a href="#" data-calendar-page="${currentPage - 1}" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-sky-300 hover:text-sky-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-sky-500 dark:hover:text-sky-300">Previous</a>`
                : '<span class="rounded-md border border-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-300 dark:border-slate-800 dark:text-slate-600">Previous</span>'}
            ${currentPage < lastPage
                ? `<a href="#" data-calendar-page="${currentPage + 1}" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-sky-300 hover:text-sky-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-sky-500 dark:hover:text-sky-300">Next page</a>`
                : '<span class="rounded-md border border-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-300 dark:border-slate-800 dark:text-slate-600">Next page</span>'}
        </div>`;

    root.querySelector('[data-calendar-clear]').classList.toggle('hidden', ! selectedDate);
    root.querySelector('[data-calendar-aside-list]').innerHTML = visibleEvents.length
        ? visibleEvents.slice(0, 3).map(calendarAsideCard).join('')
        : '<div class="rounded-md border border-dashed border-slate-300 bg-white px-3 py-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">No event markers yet.</div>';
    setCalendarDayState(root, selectedDate);

    if (updateUrl) {
        updateCalendarUrl(selectedDate, currentPage);
    }
};

const initializeCalendarInteractions = () => {
    document.querySelectorAll('[data-calendar-root]').forEach((root) => {
        const hasRange = Boolean(root.dataset.calendarRangeFrom || root.dataset.calendarRangeTo);

        root.querySelectorAll('[data-calendar-day]').forEach((day) => {
            day.addEventListener('click', (event) => {
                const canSwitchLocally = hasRange
                    ? day.dataset.calendarInRange === '1'
                    : day.dataset.calendarCurrentMonth === '1';

                if (! canSwitchLocally) {
                    return;
                }

                event.preventDefault();
                renderCalendar(root, day.dataset.calendarDay, 1);
            });
        });

        root.querySelector('[data-calendar-clear]')?.addEventListener('click', (event) => {
            event.preventDefault();
            renderCalendar(root, null, 1);
        });

        root.addEventListener('click', (event) => {
            const button = event.target.closest('[data-calendar-page]');
            if (! button) {
                return;
            }

            event.preventDefault();
            renderCalendar(root, root.dataset.calendarSelectedDate || null, Number(button.dataset.calendarPage || 1));
        });
    });
};

const initializeEmailMatchDialog = () => {
    const dialog = document.querySelector('[data-email-match-dialog]');
    const openButton = document.querySelector('[data-email-match-open]');

    if (! dialog || ! openButton) {
        return;
    }

    openButton.addEventListener('click', () => {
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
            return;
        }

        dialog.setAttribute('open', '');
    });

    dialog.querySelectorAll('[data-email-match-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
};

const initializeDashboardHorizontalScroll = () => {
    const scrollArea = document.querySelector('[data-dashboard-scroll]');
    const control = document.querySelector('[data-mobile-scroll-control]');
    const range = document.querySelector('[data-dashboard-scroll-range]');

    if (! scrollArea || ! control || ! range) {
        return;
    }

    const refresh = () => {
        const max = Math.max(scrollArea.scrollWidth - scrollArea.clientWidth, 0);
        const isDesktop = window.matchMedia('(min-width: 1280px)').matches;

        range.max = String(max);
        range.value = String(Math.min(scrollArea.scrollLeft, max));
        control.classList.toggle('hidden', isDesktop || max <= 0);
    };

    range.addEventListener('input', () => {
        scrollArea.scrollLeft = Number(range.value);
    });

    scrollArea.addEventListener('scroll', () => {
        range.value = String(scrollArea.scrollLeft);
    }, { passive: true });

    window.addEventListener('resize', refresh);
    refresh();
};

const initializeDateControls = () => {
    const formatter = new Intl.DateTimeFormat('en-US', {
        month: '2-digit',
        day: '2-digit',
        year: 'numeric',
    });

    document.querySelectorAll('[data-date-shell]').forEach((shell) => {
        const button = shell.querySelector('[data-date-button]');
        const input = shell.querySelector('[data-date-input]');
        const label = shell.querySelector('[data-date-label]');

        if (! button || ! input || ! label || shell.dataset.bound === 'true') {
            return;
        }

        shell.dataset.bound = 'true';

        const updateLabel = () => {
            if (! input.value) {
                return;
            }

            label.textContent = formatter.format(new Date(`${input.value}T00:00:00`));
        };

        button.addEventListener('click', () => {
            if (typeof input.showPicker === 'function') {
                input.showPicker();
                return;
            }

            input.click();
        });

        input.addEventListener('change', updateLabel);
        updateLabel();
    });
};

const initializeEmailMetricControls = () => {
    const formatter = new Intl.NumberFormat('en-US');

    document.querySelectorAll('[data-email-performance-card]').forEach((card) => {
        const dataElement = card.querySelector('[data-email-metric-data]');

        if (! dataElement || card.dataset.metricBound === 'true') {
            return;
        }

        card.dataset.metricBound = 'true';

        let state;

        try {
            state = JSON.parse(dataElement.textContent || '{}');
        } catch {
            return;
        }

        const metricOptions = state.metrics || {};
        const points = state.points || [];
        const chart = state.chart || {};
        const plotLeft = Number(chart.plot_left || 46);
        const plotRight = Number(chart.plot_right || 884);
        const plotBottom = Number(chart.plot_bottom || 178);
        const plotHeight = Number(chart.plot_height || 160);
        const title = card.querySelector('[data-email-metric-title]');
        const current = card.querySelector('[data-email-metric-current]');
        const label = card.querySelector('[data-email-metric-label]');
        const value = card.querySelector('[data-email-metric-value]');
        const rows = card.querySelector('[data-email-metric-rows]');
        const area = card.querySelector('[data-email-area]');
        const line = card.querySelector('[data-email-line]');
        const axis = card.querySelector('[data-email-axis-label]');
        const svg = card.querySelector('[data-email-chart]');
        const pointLayer = card.querySelector('[data-email-points]');
        const chartWrap = card.querySelector('[data-email-chart-wrap]');
        const tooltip = card.querySelector('[data-email-chart-tooltip]');
        const details = card.querySelector('details');
        let activeMetricKey = state.active || 'open_rate';
        let activeCoords = [];

        const formatValue = (number, unit = '') => unit === '%'
            ? `${Number(number || 0).toFixed(2)}%`
            : formatter.format(Math.round(Number(number || 0)));

        const metricNumerator = (point, metricKey) => {
            if (metricKey === 'click_rate') {
                return Number(point.clicked || 0);
            }

            if (metricKey === 'open_rate') {
                return Number(point.opened || 0);
            }

            return Number(point[metricOptions[metricKey]?.point_key] || 0);
        };

        const tooltipMetricText = (metricKey, point, valueNumber) => {
            const metric = metricOptions[metricKey] || metricOptions.open_rate;

            if (metric?.unit === '%') {
                const numerator = metricNumerator(point, metricKey);
                const delivered = Number(point.delivered || 0);
                const noun = metricKey === 'click_rate' ? 'clicked' : 'opened';

                return `${formatValue(valueNumber, '%')} (${formatter.format(numerator)} ${noun} / ${formatter.format(delivered)} deliveries)`;
            }

            return formatValue(valueNumber, metric?.unit || '');
        };

        const pointPath = (metric) => {
            const key = metric.point_key;
            const maxPoint = points.reduce((max, point) => Math.max(max, Number(point[key] || 0)), 0);
            const tickMax = Math.max(Number(metric.tick_min || 5), Math.ceil(Math.max(maxPoint, Number(metric.value || 0)) / 5) * 5 || 5);
            const count = Math.max(points.length, 1);
            const coords = points.map((point, index) => {
                const x = count === 1 ? (plotLeft + plotRight) / 2 : plotLeft + ((plotRight - plotLeft) * (index / (count - 1)));
                const y = plotBottom - (plotHeight * Math.min(Number(point[key] || 0), tickMax) / tickMax);

                return { x: Number(x.toFixed(2)), y: Number(y.toFixed(2)) };
            });

            if (coords.length === 0) {
                return { linePath: '', areaPath: '', tickMax, coords: [] };
            }

            let linePath = `M ${coords[0].x} ${coords[0].y}`;

            for (let index = 1; index < coords.length; index++) {
                const previous = coords[index - 1];
                const currentPoint = coords[index];
                const controlX = Number(((previous.x + currentPoint.x) / 2).toFixed(2));

                linePath += ` C ${controlX} ${previous.y} ${controlX} ${currentPoint.y} ${currentPoint.x} ${currentPoint.y}`;
            }

            const areaPath = `${linePath} L ${coords[coords.length - 1].x} ${plotBottom} L ${coords[0].x} ${plotBottom} Z`;

            return { linePath, areaPath, tickMax, coords };
        };

        const setActiveOption = (metricKey) => {
            card.querySelectorAll('[data-email-metric-option]').forEach((option) => {
                const isActive = option.dataset.emailMetricOption === metricKey;

                option.classList.toggle('bg-slate-50', isActive);
                option.classList.toggle('font-medium', isActive);
                option.classList.toggle('text-blue-600', isActive);
                option.classList.toggle('dark:bg-slate-900', isActive);
                option.classList.toggle('dark:text-blue-300', isActive);
                option.classList.toggle('hover:bg-slate-50', ! isActive);
                option.classList.toggle('dark:hover:bg-slate-900', ! isActive);
                option.querySelector('[data-email-metric-check]')?.classList.toggle('hidden', ! isActive);
            });
        };

        const renderMetric = (metricKey, updateUrl = true) => {
            const metric = metricOptions[metricKey] || metricOptions.open_rate;

            if (! metric) {
                return;
            }

            title.textContent = `${metric.label} (for All Campaigns)`;
            current.textContent = metric.label;
            label.textContent = metric.label;
            value.textContent = formatValue(metric.value, metric.unit);
            axis.textContent = metric.label;
            svg?.setAttribute('aria-label', `${metric.label} chart`);

            rows.innerHTML = Object.entries(metric.rows || {}).map(([rowLabel, rowValue]) => `
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-600 dark:text-slate-400">${escapeHtml(rowLabel)}</span>
                    <span class="font-medium text-slate-800 dark:text-slate-200">${formatter.format(Math.round(Number(rowValue || 0)))}</span>
                </div>
            `).join('') + `<p class="text-xs text-slate-400 dark:text-slate-500">${formatter.format(Number(state.stats_count || 0))} campaign stats loaded</p>`;

            activeMetricKey = metricKey;
            const paths = pointPath(metric);
            activeCoords = paths.coords || [];
            area?.setAttribute('d', paths.areaPath);
            line?.setAttribute('d', paths.linePath);

            if (pointLayer) {
                pointLayer.innerHTML = activeCoords.map((coord, index) => `
                    <circle data-email-point="${index}" cx="${coord.x}" cy="${coord.y}" r="4" fill="#687291" stroke="white" stroke-width="2" class="transition-all duration-150 dark:stroke-slate-950"></circle>
                `).join('');
            }

            card.querySelectorAll('[data-email-y-tick]').forEach((tick) => {
                const index = Number(tick.dataset.emailYTick || 0);
                const tickValue = Math.round((paths.tickMax / 5) * index);

                tick.textContent = `${formatter.format(tickValue)}${metric.unit || ''}`;
            });

            setActiveOption(metricKey);
            details?.removeAttribute('open');

            if (updateUrl) {
                const url = new URL(window.location.href);
                url.searchParams.set('email_metric', metricKey);
                window.history.replaceState({}, '', url);
            }
        };

        const renderTooltip = (event) => {
            if (! svg || ! tooltip || ! chartWrap || activeCoords.length === 0 || points.length === 0) {
                return;
            }

            const metric = metricOptions[activeMetricKey] || metricOptions.open_rate;
            const key = metric?.point_key || 'open_rate';
            const rect = svg.getBoundingClientRect();
            const viewBox = svg.viewBox.baseVal;
            const pointerX = viewBox.x + ((event.clientX - rect.left) / rect.width) * viewBox.width;
            const nearestIndex = activeCoords.reduce((nearest, coord, index) => {
                const currentDistance = Math.abs(coord.x - pointerX);
                const nearestDistance = Math.abs(activeCoords[nearest].x - pointerX);

                return currentDistance < nearestDistance ? index : nearest;
            }, 0);
            const nearestCoord = activeCoords[nearestIndex];
            const interval = activeCoords.length > 1
                ? Math.abs(activeCoords[1].x - activeCoords[0].x)
                : 48;
            const hoverDistance = Math.abs(nearestCoord.x - pointerX);
            const hoverRange = Math.min(Math.max(interval / 2, 24), 72);

            if (hoverDistance > hoverRange) {
                tooltip.classList.add('hidden');
                pointLayer?.querySelectorAll('[data-email-point]').forEach((pointElement) => {
                    pointElement.setAttribute('r', '4');
                    pointElement.setAttribute('opacity', '1');
                });
                return;
            }

            pointLayer?.querySelectorAll('[data-email-point]').forEach((pointElement) => {
                const isActivePoint = pointElement.dataset.emailPoint === String(nearestIndex);

                pointElement.setAttribute('r', isActivePoint ? '6' : '4');
                pointElement.setAttribute('opacity', isActivePoint ? '1' : '0.85');
            });

            const point = points[nearestIndex] || {};
            const valueNumber = Number(point[key] || 0);
            const zeroText = tooltipMetricText(activeMetricKey, { delivered: 0, opened: 0, clicked: 0 }, 0);
            const workflowText = tooltipMetricText(activeMetricKey, point, valueNumber);
            const rowsHtml = [
                ['#3b82f6', 'Email Campaign', zeroText],
                ['#a78bfa', 'Workflow Campaign', workflowText],
                ['#38bdf8', 'Bulk Action Campaign', zeroText],
                ['#14b8a6', 'Email sequences', zeroText],
                ['#687291', 'All Campaigns', workflowText],
            ].map(([color, name, text]) => `
                <div class="mt-1 flex items-start gap-1.5">
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full" style="background-color: ${color}"></span>
                    <span><span class="font-semibold">${escapeHtml(name)}:</span> ${escapeHtml(text)}</span>
                </div>
            `).join('');

            tooltip.innerHTML = `<div class="font-semibold">Date: ${escapeHtml(point.label || point.date || '')}</div>${rowsHtml}`;
            tooltip.classList.remove('hidden');

            const wrapRect = chartWrap.getBoundingClientRect();
            const tooltipWidth = tooltip.offsetWidth || 260;
            const tooltipHeight = tooltip.offsetHeight || 120;
            const left = Math.min(Math.max(event.clientX - wrapRect.left + 12, 8), Math.max(wrapRect.width - tooltipWidth - 8, 8));
            const top = Math.min(Math.max(event.clientY - wrapRect.top - tooltipHeight - 12, 8), Math.max(wrapRect.height - tooltipHeight - 8, 8));

            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
        };

        card.querySelectorAll('[data-email-metric-option]').forEach((option) => {
            option.addEventListener('click', (event) => {
                event.preventDefault();
                renderMetric(option.dataset.emailMetricOption || 'open_rate');
            });
        });

        svg?.addEventListener('mousemove', renderTooltip);
        svg?.addEventListener('mouseleave', () => {
            tooltip?.classList.add('hidden');
            pointLayer?.querySelectorAll('[data-email-point]').forEach((pointElement) => {
                pointElement.setAttribute('r', '4');
                pointElement.setAttribute('opacity', '1');
            });
        });
        renderMetric(state.active || 'open_rate', false);
    });
};

const initializeEmailBreakdownPagination = () => {
    const pageSize = 5;

    document.querySelectorAll('[data-email-breakdown]').forEach((root) => {
        const rows = Array.from(root.querySelectorAll('[data-email-breakdown-row]'));
        const pagination = root.querySelector('[data-email-breakdown-pagination]');
        const label = root.querySelector('[data-email-breakdown-pagination-label]');
        const count = root.querySelector('[data-email-breakdown-count]');
        const previous = root.querySelector('[data-email-breakdown-prev]');
        const next = root.querySelector('[data-email-breakdown-next]');
        let page = 1;

        if (rows.length <= pageSize) {
            return;
        }

        const render = () => {
            const lastPage = Math.max(Math.ceil(rows.length / pageSize), 1);
            page = Math.min(Math.max(page, 1), lastPage);

            const start = (page - 1) * pageSize;
            const end = start + pageSize;

            rows.forEach((row, index) => {
                row.hidden = index < start || index >= end;
            });

            pagination?.classList.remove('hidden');
            pagination?.classList.add('flex');

            if (label) {
                label.textContent = `Page ${page} of ${lastPage} - ${pageSize} per page`;
            }

            if (count) {
                count.textContent = `${start + 1}-${Math.min(end, rows.length)} of ${rows.length} email actions`;
            }

            if (previous) {
                previous.disabled = page <= 1;
            }

            if (next) {
                next.disabled = page >= lastPage;
            }
        };

        previous?.addEventListener('click', () => {
            page -= 1;
            render();
        });

        next?.addEventListener('click', () => {
            page += 1;
            render();
        });

        render();
    });
};

document.querySelectorAll('[data-filter-button]').forEach((button) => {
    button.addEventListener('click', () => applyFilter(button.dataset.filterButton ?? 'all'));
});

document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const isDesktop = window.matchMedia('(min-width: 1280px)').matches;
        const isCollapsed = isDesktop
            ? button.getAttribute('aria-expanded') === 'true'
            : button.getAttribute('aria-expanded') !== 'true';

        setSidebarCollapsed(isCollapsed);
    });
});

document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        applyTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
    });
});

document.querySelectorAll('[data-refresh-page]').forEach((button) => {
    button.addEventListener('click', () => window.location.reload());
});

document.querySelectorAll('[data-copy-value]').forEach((button) => {
    button.addEventListener('click', async () => {
        await navigator.clipboard.writeText(button.dataset.copyValue ?? '');

        const originalText = button.textContent;
        button.textContent = 'Copied';
        window.setTimeout(() => {
            button.textContent = originalText;
        }, 1200);
    });
});

applyFilter('all');
applyTheme(document.documentElement.classList.contains('dark') ? 'dark' : 'light');
setSidebarCollapsed(window.matchMedia('(min-width: 1280px)').matches && window.localStorage.getItem('companyDashboard.sidebarCollapsed') === 'true');
initializeCalendarInteractions();
initializeEmailMatchDialog();
initializeDashboardHorizontalScroll();
initializeDateControls();
initializeEmailMetricControls();
initializeEmailBreakdownPagination();
