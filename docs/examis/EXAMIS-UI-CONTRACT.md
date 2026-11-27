# EXAMIS — عقد الواجهة (مسودة — المرحلة 5)

**الحالة:** `IN PROGRESS`. يخضع لـ `.cursor/architecture/UI-CONTRACT.md` و`02-ui-ux.mdc` و`react-inertia.mdc` و`17-color-typography-governance.mdc`
ويتقدّم عليه أي منها عند التعارض. البناء مأذون وحدةً وحدة (DEC-020)؛ أول صفحة (U-01) تُثبّت هذا العقد قبل أي صفحة ثانية.

## 1. ما وُجد فعلًا في SIS (2026-10-24)

| العنصر | المسار | ملاحظة |
|---|---|---|
| «شريط التبويبات» = تبويبات الـ ribbon في شريط العنوان (ملف، الرئيسية، تحرير، إضافة، إعدادات، قوائم، أدوات، تقارير، تعليمات) | `resources/js/components/sis/chrome-tabs.ts` (SSOT)، `title-bar-menu.tsx`، `title-bar-ribbon.tsx`، `page-ribbon-context.tsx` | التسميات من i18n `chrome.tabs` فقط |
| التنقل السابق/التالي داخل التبويب | `title-bar-page-nav.tsx` + `lib/navigation-history` | يتبع اتجاه القراءة |
| تراجع/إعادة للتعديلات | `title-bar-history.tsx` | — |
| مكونات مشتركة | `components/sis/`: `data-table`، `table-sort`، `table-row-selection`، `table-status-badge`، `status-chip`، `sis-list-select`، `sis-multi-select`، `sis-search-field`، `ribbon-filter`، `ops-year-filter`، `ops-form-field`، `confirm-dialog`، `message-dialog`، `error-dialog`، `empty-state`، `loading-state`، `error-state`، `page-header`، `workflow-flash-host`، `sis-workflow-notice` | لا يُنشأ بديل لأي منها |
| ثيم الجداول | الصنف `sis-table-theme` على غلاف الصفحة (`resources/css/app.css`) | يطبق على كل صفحة قائمة |

**غير موجود / NOT VERIFIED:** مدير «تبويبات صفحات مفتوحة» متعددة المستندات (مثل المتصفح). لم يُعثر عليه؛ إن كان المقصود شيئًا آخر فيجب أن يحدده المالك.

## 2. قواعد ملزمة لصفحات EXAMIS

1. **لا تغيير** في ألوان أو مقاسات أو تخطيط الصفحات القائمة؛ الصفحة الجديدة تعيد استخدام المكونات القائمة (تفضيل المالك الدائم).
2. الألوان: Night / Oxford / Steel / Mist / Pearl فقط؛ الخطوط: Segoe UI / Tahoma / Calibri / Aptos؛ لا shadcn/Instrument Sans جديد.
3. لا ribbon جديد ولا مدير تبويبات موازٍ؛ الصفحة تسجّل أوامرها في `page-ribbon-context`، **4 أوامر كحد أقصى لكل مجموعة**، ولا قيمة افتراضية `[]` في معاملات الـ hook (سببت React #185 سابقًا).
4. **لا معرّفات خام** كمدخل أساسي: الطالب/التسجيل/المادة/الفصل/السنة/الجلسة تُختار بـ `sis-list-select` / `sis-search-field` مع بحث وترقيم في الخادم.
5. Inertia فقط (لا fetch/axios/React Query)؛ النماذج: Inertia Form + Wayfinder + FormRequest؛ تحقق الخادم هو المرجع؛ أخطاء الحقول مربوطة بها.
6. الجداول: ترقيم وتصفية في الخادم؛ لا آلاف الصفوف في DOM.
7. **لغتان (DEC-026):** كل نص في `resources/js/i18n/ar.ts` **و** `en.ts` معًا (`LOCALES = ['ar','en']`، اللغة من cookie `locale`، الاتجاه من `direction()`)؛
   لا نص عربي مكتوب داخل المكوّن؛ لا `dir="rtl"` ثابت على عناصر تتبع اللغة (الأخطاء TS17001 في الصفحات الأصلية سببها `dir` مكرر). لا تشغيل `vp check --fix` على `ar.ts`.
7b. التجاوب: كل صفحة تُفحص على 390 px (هاتف) و820 px (لوحي) و1366 px (سطح مكتب) في الاتجاهين، دون تمرير أفقي على مستوى الصفحة؛ الجداول الواسعة تتمرر داخل حاويتها.
8. أي فعل يغيّر بيانات رسمية (اعتماد، نشر، سحب، إلغاء، قبول مراجعة) يمر بـ `confirm-dialog` مع السبب حيث يشترطه الخادم، ويمنع الإرسال المكرر.
9. لا منطق أكاديمي في React: الأهلية، الدرجة، النجاح، الترتيب تأتي من الخادم.
10. الحالات: تحميل (`loading-state`)، فراغ (`empty-state`)، خطأ (`error-state` / `error-dialog`)، نجاح (`workflow-flash-host`).
11. أسماء أوامر الـ ribbon يجب أن تكون مميزة للقارئ الآلي (وُجد سابقًا ثلاثة أوامر نشر بنفس الاسم).

## 3. قالب توصيف كل صفحة (يُملأ قبل البناء)

الهدف والمستخدم · البيانات (prop Inertia وحدّها الأقصى) · الأفعال وصلاحية كل فعل · حالات التحميل/الفراغ/الخطأ · المدخلات والتحقق ·
الحفظ/الإلغاء/التأكيد · التغييرات غير المحفوظة · رسائل النجاح/الفشل · الاختبارات (Feature + 403 عبر المدارس + متصفح RTL 390/820/1366 px).

## 4. الصفحات المرشحة (تعتمد على DEC-P01)

| الصفحة | تغطي | backend القائم |
|---|---|---|
| إدارة الامتحان والجلسات | F-01، F-09، F-11 | `CreateExam*`، `UpdateExam*`، `CancelExam*`، `*ExamSession*` |
| تسكين صف في جلسة | F-10 | L-A4 |
| كشف درجات الجلسة | F-16 | L-A5 |
| مراجعات الدرجات | F-18 | L-A2 |
| أنواع الامتحانات وأوزان السنة | F-02 | L-A3 |
| كشوف نتائج الفصل/السنة + النشر + التسوية | F-20، F-21 | L-A1، L-A6، L-A7 |
| «نتائجي» للطالب/ولي الأمر | F-21 | `GetPortalPublishedResultsHandler` |
