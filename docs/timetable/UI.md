# Timetable — UI

The existing builder (`resources/js/pages/timetable/index.tsx`) is kept as it was: Days → Periods → Cards,
colours, RTL, drag & drop, swap, shift, audit, A3 print. Engine features are additions built only from existing
components and classes (`RegistrySheetDialog`, `SheetSection`, `SisListSelect`, `ConfirmDialog`, `Button`,
`sis-timetable-*`, `sis-admission-*`) — no new CSS, colours or layout system.

## Ribbon

- «الصفحة الرئيسية» › عرض: by section · by teacher · **by room** (room picker in the filters).
- «تحرير» › **محرك الجدول**: توليد الجدول · الأنشطة والمجموعات · القيود · الإتاحة · الإصدارات والنشر · إعدادات الجدول
  (beside «الجاهزية والجودة» and «تدقيق الجدول»). Commands disable themselves without the data / permission.

## Sheets (`resources/js/components/timetable/engine/`)

| Sheet | What it does |
|-------|--------------|
| `generate-sheet` | Scope (school / shown sections / focused section / focused teacher), mode, time budget, objectives, what-if scenarios, load preview; runs with live progress (polls only while a run is active), cancel; review of a run: placed / unplaced, why each block was not placed + single-blocker hints, relaxed rules, diff, quality; apply / discard / save as version |
| `activities-sheet` | Activities of the focused section, «إنشاء من المنهج», inline edit (type, weekly, block, distribution, room / room type / workshop, week, note), end; new activity with joined sections, groups, co-teacher + sessions; divisions: split by count or capacity, end |
| `constraints-sheet` | Rules with priority badge, scope («ينطبق على»), params, reason; new rule form generated from the server catalogue |
| `availability-sheet` | Teacher / room / section / workshop × days × lessons; click cycles available → unavailable → avoid → preferred; saves changed cells |
| `versions-sheet` | Snapshot, submit, approve / reject (approvers), publish from a date, archive, restore (confirm), view read-only, compare A/B, CSV export links, stale warning |
| `settings-sheet` | Working days, cycle weeks, teacher / subject daily limits, double changeover |
| `lesson-engine-tools` | Inside «تعديل الحصة»: lock / unlock, «اقتراح أماكن» (moves / swaps with impact), «بديل ليوم» (ranked substitutes → assign) |

## Grid additions

- Card: lock icon; group, co-teacher, week markers on the second line; «+n» when parallel groups share a cell;
  locked cards open but do not drag.
- Banners: read-only version view (with «العودة إلى الجدول الحالي»), stale published timetable.
- Student page `timetable/student` (read-only week).

## Known UI limits

- A section cell shows one lesson plus «+n» for parallel groups (the tooltip names the markers); the full list is
  in the group / student views and exports.
- Block moves (doubles / triples) go through generation or shift, not «اقتراح أماكن».
