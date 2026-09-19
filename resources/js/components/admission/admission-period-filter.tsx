import {
    ADMISSION_PERIOD_FILTER_ALL,
    type AdmissionActivePeriodSummary,
} from '@/components/admission/admission-workspace';
import { t } from '@/i18n';

type Props = {
    periods: AdmissionActivePeriodSummary[];
    selectedPeriodId?: number | null;
    onPeriodSelect: (periodId: number) => void;
};

/** Period-name filter — Inertia visit is owned by the page shell. */
export function AdmissionPeriodFilter({
    periods,
    selectedPeriodId = null,
    onPeriodSelect,
}: Props) {
    const i18n = t().admission;
    const value =
        selectedPeriodId !== null && selectedPeriodId > 0
            ? String(selectedPeriodId)
            : String(ADMISSION_PERIOD_FILTER_ALL);

    return (
        <label className="sis-admission-period-filter" dir="rtl">
            <span className="shrink-0 font-medium">{i18n.filterByPeriod}</span>
            <select
                value={value}
                onChange={(event) => {
                    onPeriodSelect(Number(event.target.value));
                }}
                className="sis-ops-hub__link sis-admission-year-control"
                aria-label={i18n.filterByPeriod}
            >
                <option value={ADMISSION_PERIOD_FILTER_ALL}>{i18n.allPeriods}</option>
                {periods.map((period) => (
                    <option key={period.id} value={period.id}>
                        {period.name}
                    </option>
                ))}
            </select>
        </label>
    );
}
