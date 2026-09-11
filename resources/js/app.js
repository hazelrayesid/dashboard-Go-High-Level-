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

document.querySelectorAll('[data-filter-button]').forEach((button) => {
    button.addEventListener('click', () => applyFilter(button.dataset.filterButton ?? 'all'));
});

document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const isCollapsed = button.getAttribute('aria-expanded') === 'true';

        setSidebarCollapsed(isCollapsed);
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
setSidebarCollapsed(window.localStorage.getItem('companyDashboard.sidebarCollapsed') === 'true');
