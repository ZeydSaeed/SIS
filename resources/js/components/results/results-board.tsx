import {
    BookIcon,
    CapIcon,
    EntryIcon,
    EyeIcon,
    LinksIcon,
    PersonKeyIcon,
    QuestionIcon,
    SheetGearIcon,
    ShieldIcon,
    type ResultsBoardIcon,
} from '@/components/results/results-board-icons';
import { t } from '@/i18n';

/** One of the board's nine card colours (`sis-results-tile--1` … `--9` in app.css). */
type Tile = 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9;

type BoardCard = {
    key: string;
    title: string;
    subtitle?: string;
    bar: string;
    icon: ResultsBoardIcon;
    tile: Tile;
};

/**
 * «النتائج» — the layout of the cyber_security control panel (owner's request, 2026-11-28): the progress strip with
 * its legend, then one coloured card per item (round badge with its picture, title, progress bar), in the same order
 * and colours. Pictures, bars, styling and order only: it reads nothing — no teachers, subjects, curricula, grades or
 * students — so every bar is empty and every share is «—».
 */
export function ResultsBoard() {
    const b = t().results.board;
    const cards: BoardCard[] = [
        { key: 'entry', title: b.entry, subtitle: b.entrySubtitle, bar: b.entryBar, icon: EntryIcon, tile: 1 },
        { key: 'links', title: b.links, bar: b.linksBar, icon: LinksIcon, tile: 2 },
        { key: 'teachers', title: b.teachers, bar: b.teachersBar, icon: PersonKeyIcon, tile: 3 },
        { key: 'students', title: b.students, bar: b.studentsBar, icon: CapIcon, tile: 4 },
        { key: 'permissions', title: b.permissions, bar: b.permissionsBar, icon: ShieldIcon, tile: 5 },
        { key: 'appeals', title: b.appeals, bar: b.appealsBar, icon: QuestionIcon, tile: 9 },
        { key: 'card', title: b.card, bar: b.cardBar, icon: SheetGearIcon, tile: 6 },
        { key: 'teacher-view', title: b.teacherView, bar: b.teacherViewBar, icon: EyeIcon, tile: 7 },
        { key: 'subjects', title: b.subjects, bar: b.subjectsBar, icon: BookIcon, tile: 8 },
    ];

    return (
        <div className="sis-results-board">
            <section className="sis-results-board__progress" aria-labelledby="results-board-progress">
                <h2 id="results-board-progress" className="sis-results-board__progress-title">
                    {b.progressTitle}
                </h2>
                <div className="sis-results-board__strip" aria-hidden>
                    {cards.map((card) => (
                        <span key={card.key} className="sis-results-board__strip-part">
                            <span className={`sis-results-board__fill sis-results-tile--${card.tile}`} style={{ width: '0%' }} />
                        </span>
                    ))}
                </div>
                <ul className="sis-results-board__legend">
                    {cards.map((card) => (
                        <li key={card.key} className="sis-results-board__legend-item">
                            <span className={`sis-results-board__dot sis-results-tile--${card.tile}`} aria-hidden />
                            <span className="sis-results-board__legend-label">{card.bar}</span>
                            <span className="sis-results-board__legend-share">· —</span>
                        </li>
                    ))}
                </ul>
            </section>

            <ul className="sis-results-board__cards">
                {cards.map((card) => {
                    const Icon = card.icon;

                    return (
                        <li key={card.key} className="sis-results-board__cell">
                            <div className={`sis-results-board__card sis-results-tile--${card.tile}`}>
                                <span className="sis-results-board__badge" aria-hidden>
                                    <Icon className="sis-results-board__icon" />
                                </span>
                                <span className="sis-results-board__title">
                                    {card.title}
                                    {card.subtitle ? <span className="block">{card.subtitle}</span> : null}
                                </span>
                                <span className="sis-results-board__card-progress">
                                    <span className="sis-results-board__card-progress-head">
                                        <span>{card.bar}</span>
                                        <span className="sis-results-board__card-share">—</span>
                                    </span>
                                    <span
                                        role="progressbar"
                                        aria-label={card.bar}
                                        aria-valuemin={0}
                                        aria-valuemax={100}
                                        aria-valuenow={0}
                                        className="sis-results-board__card-bar"
                                    >
                                        <span className="sis-results-board__fill" style={{ width: '0%' }} />
                                    </span>
                                </span>
                            </div>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
