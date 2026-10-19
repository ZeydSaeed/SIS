import { ChevronDown, CircleAlert, CircleCheck } from 'lucide-react';
import { useState } from 'react';

type Props = {
    gaps: string[];
    completeLabel: string;
    continueLabel: string;
    expandLabel: string;
    onContinue: () => void;
};

/**
 * ملف الطالب: «مستوفي» (أيقونة صح) | «متابعة الملف» زر بعدّاد النواقص يفتح نافذة الملف، وسهم منفصل يطوي قائمة النواقص.
 * اللون والأيقونة والنص يحملون المعنى معًا؛ اللون وحده لا يدل على الحالة.
 */
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
            <span className="sis-student-file sis-student-file--complete">
                <CircleCheck aria-hidden className="sis-student-file__icon" strokeWidth={2} />
                <span className="sis-student-file__label">{completeLabel}</span>
            </span>
        );
    }

    return (
        <div
            className={`sis-student-file sis-student-file--incomplete${gapsOpen ? ' sis-student-file--open' : ''}`}
            onClick={(event) => event.stopPropagation()}
        >
            <div className="sis-student-file__row">
                <button
                    type="button"
                    className="sis-student-file__action"
                    title={continueLabel}
                    onClick={(event) => {
                        event.stopPropagation();
                        onContinue();
                    }}
                >
                    <CircleAlert aria-hidden className="sis-student-file__icon" strokeWidth={2} />
                    <span className="sis-student-file__label">{continueLabel}</span>
                    <span className="sis-student-file__count" dir="ltr">
                        {gaps.length}
                    </span>
                </button>
                <button
                    type="button"
                    className="sis-student-file__toggle"
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
                        className="sis-student-file__chevron"
                        size={14}
                        strokeWidth={2.5}
                    />
                </button>
            </div>
            {gapsOpen ? (
                <ul className="sis-student-file__gaps">
                    {gaps.map((gap, index) => (
                        <li key={`${index}-${gap}`}>{gap}</li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
