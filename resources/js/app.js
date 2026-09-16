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
                const title = matched
                    ? `Found in GHL${attendee.business ? `: ${attendee.business}` : ''}`
                    : 'Not found in synced GHL contacts';

                return `<span title="${escapeHtml(title)}" class="max-w-full truncate rounded-md border px-2 py-1 text-[11px] font-medium ${chipClass}">${escapeHtml(attendee.label)}</span>`;
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

    return `<article class="grid gap-3 px-4 py-3 sm:grid-cols-[64px_minmax(0,1fr)_auto] sm:items-center">
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
    root.querySelector('[data-calendar-summary]').textContent = `${label} · ${visibleEvents.length} unique events${compare.matched + compare.unmatched > 0 ? ` · ${compare.matched} in GHL · ${compare.unmatched} new` : ''}`;

    const list = root.querySelector('[data-calendar-list]');
    const empty = root.querySelector('[data-calendar-empty]');
    list.innerHTML = pageEvents.map(calendarEventCard).join('');
    list.classList.toggle('hidden', pageEvents.length === 0);
    empty.classList.toggle('hidden', pageEvents.length > 0);

    const pagination = root.querySelector('[data-calendar-pagination]');
    pagination.classList.toggle('hidden', lastPage <= 1);
    pagination.innerHTML = `<p class="text-xs text-slate-500 dark:text-slate-400">Page ${currentPage} of ${lastPage} · ${perPage} per page</p>
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

document.querySelectorAll('[data-filter-button]').forEach((button) => {
    button.addEventListener('click', () => applyFilter(button.dataset.filterButton ?? 'all'));
});

document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const isCollapsed = button.getAttribute('aria-expanded') === 'true';

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
setSidebarCollapsed(window.localStorage.getItem('companyDashboard.sidebarCollapsed') === 'true');
initializeCalendarInteractions();
