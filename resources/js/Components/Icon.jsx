export default function Icon({ name, className = 'h-5 w-5' }) {
    const paths = {
        lock: <><rect x="5" y="10" width="14" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" /></>,
        home: <><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z" /><path d="M9 21v-8h6v8" /></>,
        book: <><path d="M12 5v16M3 3h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5v16h-5a4 4 0 0 0-4 2 4 4 0 0 0-4-2H3Z" /></>,
        calendar: <><rect x="3" y="5" width="18" height="16" rx="3" /><path d="M16 3v4M8 3v4M3 11h18M8 15h2M14 15h2" /></>,
        user: <><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></>,
        arrow: <path d="M4 12h16m-6-6 6 6-6 6" />,
        arrowLeft: <path d="M20 12H4m6-6-6 6 6 6" />,
        chevron: <path d="m9 6 6 6-6 6" />,
        search: <><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 5 5" /></>,
        check: <path d="m5 12 4 4L19 6" />,
        clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
        clipboard: <><rect x="5" y="4" width="14" height="18" rx="2" /><rect x="9" y="2" width="6" height="4" rx="1" /><path d="M9 11h6M9 16h4" /></>,
        sparkles: <><path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5ZM20 2v4M18 4h4" /></>,
        calculator: <><rect x="4" y="2" width="16" height="20" rx="3" /><path d="M8 6h8M8 11h1M15 11h1M8 15h1M15 15h1M8 19h1M15 19h1" /></>,
        atom: <><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(45 12 12)" /><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(-45 12 12)" /><circle cx="12" cy="12" r="1" /></>,
        leaf: <><path d="M20 3C9 2 3 6 4 13c1 7 14 9 16-10Z" /><path d="M3 22 15 10" /></>,
        landmark: <><path d="m3 8 9-5 9 5ZM5 11v7M10 11v7M14 11v7M19 11v7M3 21h18" /></>,
        globe: <><circle cx="12" cy="12" r="9" /><ellipse cx="12" cy="12" rx="4" ry="9" /><path d="M3 12h18" /></>,
        flask: <path d="M9 3h6M10 3v6L5 18a2 2 0 0 0 2 3h10a2 2 0 0 0 2-3l-5-9V3M8 14h8" />,
        chart: <><path d="M4 3v18h17M9 16v-5M14 16V6M19 16v-8" /></>,
        palette: <><path d="M12 3a9 9 0 1 0 0 18h1a2 2 0 0 0 0-4h-1a2 2 0 0 1 0-4h4a5 5 0 0 0 0-10Z" /><path d="M7 9h.01M10 6h.01M16 7h.01" /></>,
        activity: <path d="M2 12h5l3-8 4 16 3-8h5" />,
        logout: <><path d="M9 4H4v16h5M10 12h11m-5-5 5 5-5 5" /></>,
    };

    return <svg aria-hidden="true" className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">{paths[name] ?? paths.book}</svg>;
}
