import { SisListSelect } from '@/components/sis/sis-list-select';
import { t } from '@/i18n';

type Props = {
    value?: string | null;
    onChange: (value: string | null) => void;
};

/** Enrollment placement status filter — same card layout as year/period filters. */
export function AdmissionEnrollmentStatusFilter({
    value = null,
    onChange,
}: Props) {
    const i18n = t().workflow;
    const selected =
        value === 'awaiting' || value === 'completed' ? value : '';

    return (
        <label className="sis-admission-period-filter" dir="rtl">
            <span className="shrink-0 font-medium">{i18n.enrollmentStatusFilter}</span>
            <SisListSelect
                value={selected}
                options={[
                    { value: '', label: i18n.enrollmentStatusAll },
                    { value: 'awaiting', label: i18n.continueEnrollment },
                    { value: 'completed', label: i18n.enrollmentCompleted },
                ]}
                onChange={(next) => {
                    if (next === 'awaiting' || next === 'completed') {
                        onChange(next);
                        return;
                    }

                    onChange(null);
                }}
                triggerClassName="sis-ops-hub__link sis-admission-year-control"
                dir="rtl"
                ariaLabel={i18n.enrollmentStatusFilter}
            />
        </label>
    );
}
