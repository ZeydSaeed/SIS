import { ChevronDown } from 'lucide-react';
import { useState } from 'react';

type Props = {
    gaps: string[];
    completeLabel: string;
    continueLabel: string;
    expandLabel: string;
    onContinue: () => void;
};

/** ملف الطالب: مستوفي | متابعة الملف → نافذة | السهم يساراً → طيّ النواقص. */
export function StudentFileCell({
    gaps,
    completeLabel,
    continueLabel,
    expandLabel,
    onContinue,
}: Props) {
    const [gapsOpen, setGapsOpen] = useState(false);

    if (gaps.length === 0) {
        return (
            <span className="sis-admission-enroll-status-text">{completeLabel}</span>
        );
    }

    return (
        <div
            className={`sis-admission-file-cell${gapsOpen ? ' sis-admission-file-cell--open' : ''}`}
            onClick={(event) => event.stopPropagation()}
        >
            <div className="sis-admission-file-cell__row">
                <button
                    type="button"
                    className="sis-admission-enroll-status-text"
                    onClick={(event) => {
                        event.stopPropagation();
                        onContinue();
                    }}
                >
                    {continueLabel} ({gaps.length})
                </button>
                <button
                    type="button"
                    className={`sis-admission-file-cell__toggle${gapsOpen ? ' sis-admission-file-cell__toggle--open' : ''}`}
                    aria-expanded={gapsOpen}
                    aria-label={expandLabel}
                    title={expandLabel}
                    onClick={(event) => {
                        event.stopPropagation();
                        setGapsOpen((open) => !open);
                    }}
                >
                    <ChevronDown
                        aria-hidden
                        className="sis-admission-file-cell__chevron"
                        size={14}
                        strokeWidth={2.5}
                    />
                </button>
            </div>
            {gapsOpen ? (
                <ul className="sis-admission-gaps-accordion__list">
                    {gaps.map((gap, index) => (
                        <li key={`${index}-${gap}`}>{gap}</li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
