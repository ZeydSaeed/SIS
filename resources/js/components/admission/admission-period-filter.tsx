import {
    ADMISSION_PERIOD_FILTER_ALL,
    type AdmissionActivePeriodSummary,
} from '@/components/admission/admission-workspace';
import { SisListSelect } from '@/components/sis/sis-list-select';
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
            <SisListSelect
                value={value}
                options={[
                    { value: String(ADMISSION_PERIOD_FILTER_ALL), label: i18n.allPeriods },
                    ...periods.map((period) => ({
                        value: String(period.id),
                        label: period.name,
                    })),
                ]}
                onChange={(next) => onPeriodSelect(Number(next))}
                triggerClassName="sis-ops-hub__link sis-admission-year-control"
                dir="rtl"
                ariaLabel={i18n.filterByPeriod}
            />
        </label>
    );
}
