type FilterIconProps = {
    className?: string;
};

const svgProps = {
    viewBox: '0 0 24 24',
    fill: 'none',
    xmlns: 'http://www.w3.org/2000/svg',
    'aria-hidden': true as const,
    focusable: false as const,
};

/** Year — solid calendar */
export function FilterYearIcon({ className }: FilterIconProps) {
    return (
        <svg {...svgProps} className={className}>
            <rect x="3.5" y="5" width="17" height="15" rx="2.2" fill="var(--sis-oxford)" />
            <path d="M3.5 9.5h17" stroke="var(--sis-pearl)" strokeWidth="1.6" />
            <path d="M8 3.5v3.2M16 3.5v3.2" stroke="var(--sis-oxford)" strokeWidth="1.7" strokeLinecap="round" />
            <circle cx="9" cy="14.2" r="1.2" fill="var(--sis-pearl)" />
            <circle cx="12.5" cy="14.2" r="1.2" fill="var(--sis-pearl)" />
            <circle cx="16" cy="14.2" r="1.2" fill="var(--sis-pearl)" />
        </svg>
    );
}

/** Gender — solid person */
export function FilterGenderIcon({ className }: FilterIconProps) {
    return (
        <svg {...svgProps} className={className}>
            <circle cx="12" cy="7.2" r="3.2" fill="var(--sis-mist)" />
            <path d="M6.2 19.5c.7-3.6 2.9-5.4 5.8-5.4s5.1 1.8 5.8 5.4Z" fill="var(--sis-mist)" />
        </svg>
    );
}

/** Branch — solid building */
export function FilterBranchIcon({ className }: FilterIconProps) {
    return (
        <svg {...svgProps} className={className}>
            <path d="M5 20.5V7.8L12 3.8l7 4v12.7Z" fill="var(--sis-steel)" />
            <path d="M10 20.5v-5h4v5Z" fill="var(--sis-oxford)" />
            <path d="M8.2 10.2h1.6M14.2 10.2h1.6M8.2 13.2h1.6M14.2 13.2h1.6" stroke="var(--sis-pearl)" strokeWidth="1.5" strokeLinecap="round" />
        </svg>
    );
}

/** Department — solid layers */
export function FilterDepartmentIcon({ className }: FilterIconProps) {
    return (
        <svg {...svgProps} className={className}>
            <path d="M4.5 8.2 12 4.6l7.5 3.6L12 11.8 4.5 8.2Z" fill="var(--sis-night)" />
            <path d="M4.5 12.4 12 16l7.5-3.6v2.4L12 18.8 4.5 14.8v-2.4Z" fill="var(--sis-oxford)" />
            <path d="M4.5 16.2 12 19.8l7.5-3.6v1.8L12 21.6 4.5 18v-1.8Z" fill="var(--sis-steel)" />
        </svg>
    );
}

/** Specialization — solid badge */
export function FilterSpecializationIcon({ className }: FilterIconProps) {
    return (
        <svg {...svgProps} className={className}>
            <circle cx="12" cy="11" r="6.2" fill="var(--sis-oxford)" />
            <circle cx="12" cy="11" r="3" fill="var(--sis-steel)" />
            <path d="M9.4 17.6 8.2 20.4l2.3-.8L12 21.2l1.5-1.6 2.3.8-1.2-2.8Z" fill="var(--sis-oxford)" />
        </svg>
    );
}

/** Class — solid mortarboard */
export function FilterClassIcon({ className }: FilterIconProps) {
    return (
        <svg {...svgProps} className={className}>
            <path d="M3.8 10.2 12 6.2l8.2 4-8.2 4-8.2-4Z" fill="var(--sis-steel)" />
            <path d="M7.2 12.4v3.6c1.4 1.3 3 2 4.8 2s3.4-.7 4.8-2v-3.6" fill="none" stroke="var(--sis-oxford)" strokeWidth="1.8" strokeLinecap="round" />
            <path d="M19.4 10.5v5.2" stroke="var(--sis-oxford)" strokeWidth="1.6" strokeLinecap="round" />
            <circle cx="19.4" cy="16.5" r="1.2" fill="var(--sis-oxford)" />
        </svg>
    );
}

/** Section — solid grid */
export function FilterSectionIcon({ className }: FilterIconProps) {
    return (
        <svg {...svgProps} className={className}>
            <rect x="4" y="4" width="7" height="7" rx="1.2" fill="var(--sis-mist)" />
            <rect x="13" y="4" width="7" height="7" rx="1.2" fill="var(--sis-mist)" />
            <rect x="4" y="13" width="7" height="7" rx="1.2" fill="var(--sis-mist)" />
            <rect x="13" y="13" width="7" height="7" rx="1.2" fill="var(--sis-oxford)" />
        </svg>
    );
}
