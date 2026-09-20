import {
    admissionQueryMatches,
    admissionSearchSegments,
    type AdmissionActivePeriodSummary,
} from '@/components/admission/admission-workspace';
import { t } from '@/i18n';

type Props = {
    periods: AdmissionActivePeriodSummary[];
    selectedPeriodId?: number | null;
    onPeriodSelect?: (periodId: number) => void;
    searchQuery?: string;
};

/** Compact active-period capacity table — same palette as periods registration table. */
export function AdmissionActivePeriodsTable({
    periods,
    selectedPeriodId = null,
    onPeriodSelect,
    searchQuery = '',
}: Props) {
    const i18n = t().admission;
    const visible = searchQuery.trim() === ''
        ? periods
        : periods.filter((period) => admissionQueryMatches(period.name, searchQuery));

    if (periods.length === 0) {
        return (
            <p className="sis-admission-active-periods__empty">{i18n.noActivePeriod}</p>
        );
    }

    if (visible.length === 0) {
        return (
            <p className="sis-admission-active-periods__empty">{i18n.emptySearch}</p>
        );
    }

    return (
        <div className="sis-admission-periods-table sis-admission-active-periods-table">
            <div className="sis-admission-periods-table__scroller">
                <table>
                    <caption className="sr-only">{i18n.periodsTitle}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{i18n.periodName}</th>
                            <th scope="col">{i18n.maxApplications}</th>
                            <th scope="col">{i18n.periodApplicationsCount}</th>
                            <th scope="col">{i18n.remainingInPeriod}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visible.map((period) => {
                            const selected = selectedPeriodId === period.id;
                            const remainingValue =
                                period.remaining ?? i18n.unlimitedCapacity;
                            const totalCount = period.total_count ?? 0;
                            const maxValue =
                                period.max_applications ?? i18n.unlimitedCapacity;

                            return (
                                <tr
                                    key={period.id}
                                    className={
                                        selected
                                            ? 'sis-admission-periods-table__row--selected'
                                            : undefined
                                    }
                                    onClick={() => onPeriodSelect?.(period.id)}
                                >
                                    <td>
                                        {admissionSearchSegments(period.name, searchQuery).map(
                                            (segment, index) =>
                                                segment.hit ? (
                                                    <mark
                                                        key={`hit-${index}`}
                                                        className="sis-admission-search-hit"
                                                    >
                                                        {segment.text}
                                                    </mark>
                                                ) : (
                                                    <span key={`plain-${index}`}>{segment.text}</span>
                                                ),
                                        )}
                                    </td>
                                    <td className="sis-admission-periods-table__max" dir="ltr">
                                        {maxValue}
                                    </td>
                                    <td className="sis-admission-periods-table__num" dir="ltr">
                                        {totalCount}
                                    </td>
                                    <td className="sis-admission-periods-table__num" dir="ltr">
                                        {remainingValue}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
