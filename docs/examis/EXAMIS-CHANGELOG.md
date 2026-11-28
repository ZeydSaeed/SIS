# EXAMIS — سجل التغييرات المؤكدة

التغييرات السابقة لهذا البروتوكول (U1–U15، A1–A3، B، C، إرجاع الصفحات) مسجلة في تاريخ Git (`bc4de38` … `4472454`) و`docs/sis/exams/SIS-EXAM-REQUIREMENTS-TRACEABILITY.md`.

## 2026-11-28 (14) — نافذة «نشر النتائج؟» بنمط «معلومات التسجيل»، في وسط الشاشة، وتمرير عمودي سلس — بطلب المالك

- **الأصناف:** أصناف نافذة «معلومات التسجيل» نفسها: `sis-student-sheet-dialog sis-enrollment-record-sheet` على النافذة، و`sis-admission-draft-form sis-student-record-form` على المحتوى.
  - شريط العنوان بلون `#17345c` (`--sis-curriculum-chrome`)، والجداول بيضاء ورؤوسها كحلية، والأزرار كحلية.
  - رأسا القسمين بلون واحد (أُزيل `tone="dark"`).
  - الترتيب «إلغاء» ثم «نشر».
- **الموضع:** تُفتح في وسط الشاشة (`50vw / 50vh`)، لا في وسط منطقة المحتوى بجانب الشريط الجانبي، إلى أن تُسحب.
- **التمرير:**
  - لا تمرير أفقي ولا شريط أفقي، والجداول بعرض النافذة.
  - `useDragScroll` على جسم النافذة: سحب بالفأرة مع انزلاق بعد الإفلات، وعجلة سلسة، وشريط عمودي رفيع.
- **الفحص في المتصفح** (Chrome headless + CDP، قاعدة `sis_ui_probe` مؤقتة حُذفت بعده، والشاشة 1582×904):
  - النافذة 1152×834 عند (215، 35)، أي في الوسط تمامًا.
  - شريط العنوان `rgb(23,52,92)`، ورأسا القسمين `rgb(65,90,119)`.
  - الجسم بلا تجاوز أفقي (1140/1140).
  - العجلة نقلت الجسم 597px، والسحب أعاده إلى 277px.
- **الفحوص:** `tsc` 20 = خط الأساس، و`npm run build` نجح، وprettier نظيف.

## 2026-11-28 (13) — إصلاح «تحرير» في `/results/entry-window` بعد فحص في المتصفح (أبلغ المالك: «لم تظهر التعديلات»)

- **السبب (من فحص في المتصفح):** قاعدة الشريط `.sis-ribbon--fit … .sis-ribbon__items { flex-wrap: wrap; max-width: 20rem }` تلفّ أوامر المجموعة في سطر ثانٍ. ارتفاع الشريط الثابت (6.75rem) يقصّ السطر الثاني، فتختفي أيقونات السطر الأول وتتداخل التسميات («إجراءات» بستة أوامر، و«توزيعات الفروع» بثمانية). والطيّ لا يرى ذلك لأنه يقيس العرض فقط.
- **الإصلاح:**
  - صنف `sis-ribbon--results-page` على شريط صفحات `/results`، في `title-bar-ribbon.tsx` في القائمتين.
  - CSS: `flex-wrap: nowrap; max-width: none` للمجموعات، فتبقى كل مجموعة في سطر واحد، وما لا يتسع يُطوى في زر.
  - جداول نافذة النشر صارت بعرض النافذة (`table-layout: auto; width: 100%`)، وكان عمود «الشعبة» مقطوعًا.
- **الفحص في المتصفح:** Chrome headless عبر CDP، على قاعدة مؤقتة `sis_ui_probe` (migrate + seed، حُذفت بعد الفحص)، و`php -S :8123`، وجلسة لمستخدم الفحص، والنافذة بعرض 1600:
  - «إجراءات» و«حالة» و«توزيعات الفروع» كاملة في سطر واحد بكل أيقوناتها.
  - «توزيعات الاختصاصات» و«الصفوف» و«الشعب» مطويّة، وزر الاختصاصات يفتح قائمة بكل الاختصاصات.
  - نافذة النشر تظهر بعد «كل الصفوف والشعب» ثم «نشر»: بلا طبقة خلفية، ونقطة في الصفحة خلفها تصيب عنصر الصفحة، و207 صفوف، والجدول 767/767 بلا تمرير جانبي.
  - لا أخطاء في وحدة تحكم المتصفح.
- **الفحوص:** `tsc` 20 = خط الأساس، و`npm run build` نجح.

## 2026-11-28 (12) — `/results/entry-window`: نافذة «نشر النتائج؟» بنمط النظام وغير مانعة، وطيّ مجموعات «تحرير» — بطلب المالك

- **النافذة:** `PublishSheet` بإطار نوافذ النظام (`sis-admission-sheet-dialog`، كما في نافذة سجل التنسيب):
  - شريط علوي داكن يُسحب، وفيه تكبير وإغلاق، ويمكن تغيير حجمها.
  - **غير مانعة:** `modal={false}` بلا طبقة خلفية، فالصفحة خلفها تبقى قابلة للاستعمال.
  - المحدد للنشر جدول (الفرع، الاختصاص، الصف، الشعبة) بدل نص طويل، ثم قسم «النواقص».
  - CSS: `.sis-results-publish-dialog`.
- **الشريط:** `title-bar-ribbon.tsx` يفعّل طيّ المجموعات (`accordion`) لصفحات `/results` أيضًا، كما في الجدول والحضور:
  - المجموعة التي لا تتسع تُطوى في زر يفتح أوامرها، فتبقى كل الأيقونات متاحة.
  - تعود المجموعات كاملة عند اتساع النافذة.
- **الفحوص:** `tsc` 20 = خط الأساس، و`npm run build` نجح. المتصفح `NOT RUN`.

## 2026-11-28 (11) — `/results/entry-window`: ثبات أيقونات «إجراءات»، وتوزيعات كاملة — بطلب المالك

- **سبب حركة الأيقونات:**
  - أمر «فتح الإدخال» كان يُستبدل بأمر آخر («إغلاق الإدخال»).
  - تسميات تغيّر طولها: «النواقص» تصير «إخفاء النواقص»، و«نشر النتائج» تصير «نشر المحدد».
- **الإصلاح:**
  - «فتح الإدخال» و«إغلاق الإدخال» زران ثابتان، والحالي منهما مضغوط.
  - «النواقص» و«نشر» بتسمية واحدة لا تتغير، والحالة تظهر بالضغط.
- **«توزيعات»: أربع مجموعات من بنية المدرسة كلها:**
  - **توزيعات الفروع:** كل الفروع.
  - **توزيعات الاختصاصات:** أسماء الاختصاصات بلا تكرار.
  - **توزيعات الصفوف:** بترتيب المرحلة.
  - **توزيعات الشعب:** A، B، … أبجديًا.
  - الضغط يحدد الشعب غير المنشورة المطابقة بين المعروضة أو يلغي تحديدها. إذا لم يوجد شيء تظهر رسالة.
- **الفحوص:** `tsc` 20 = خط الأساس، و`npm run build` نجح. المتصفح `NOT RUN`.

## 2026-11-28 (10) — إصلاح: `/results/entry-window` كانت تعرض صفحة فارغة (أبلغ المالك)

- **السبب:** خطأ أُدخل في (9). `places` كانت مصفوفة جديدة في كل رسم، وهي ضمن تبعيات أوامر «تحرير». فكل رسم يعيد تسجيل الشريط، والتسجيل يعيد الرسم، في حلقة لا تنتهي (Maximum update depth)، فتنهار الصفحة وتظهر فارغة.
- **الإصلاح:** `places` صارت `useMemo` على `groups`. بقية تبعيات `editGroups` و`homeGroups` ثابتة (حالة الصفحة، `t()` نفس الكائن، `showInfo` بـ `useCallback`).
- **الفحوص:** `tsc` 20 = خط الأساس، و`npm run build` نجح، وprettier نظيف. المتصفح `NOT RUN`.

## 2026-11-28 (9) — `/results/entry-window`: تبويب «تحرير» كاملًا بشكل «المناهج» — بطلب المالك

- **«إجراءات»:** فتح/إغلاق الإدخال، و«النواقص»، و«نشر»، و«إخفاء النتائج»، و«إلغاء التحديد» (جديد).
- **«حالة» (جديد):** أعداد وتصفية للعرض: الكل، منشورة، غير منشورة، المحددة. البطاقات تعرض الشعب المطابقة فقط.
- **«توزيعات» (جديد):**
  - زر لكل فرع: مصنع للصناعي، ونبتة للزراعي، وحقيبة للتجاري، ومبنى لغيرها. وزر لكل صف.
  - يحدد كل الشعب غير المنشورة فيه أو يلغي تحديدها، والعدد في التلميح.
- **كل الأوامر مفعّلة:** عندما لا يمكن تنفيذ الأمر تظهر رسالة بدل التعطيل: حدّد أولًا، أو أغلق الإدخال قبل النشر، أو أخفِ النتائج قبل فتح الإدخال، أو لا يوجد ما يُخفى.
- **الفحوص:** `tsc` 20 = خط الأساس، و`npm run build` نجح. المتصفح `NOT RUN`.

## 2026-11-28 (8) — `/results/entry-window`: أعمدة الصفوف داخل كل فرع، والعين — بطلب المالك

- **داخل كل فرع:**
  - عمود لكل صف بترتيب المرحلة (الأول، ثم الثاني، …)، وفي رأس العمود مربع «الصف: …» يحدد العمود كله.
  - في كل عمود بطاقات الاختصاصات، والشعب فيها مرتبة أبجديًا (A ثم B …).
  - تمرير أفقي رفيع إذا ضاقت الشاشة.
- **في الكود:** الرسم في مكوّنين، `BranchBlock` و`PlaceCard`.
- **العين:** مفتوحة ما دام إدخال الدرجات مغلقًا، أي أن النتائج قابلة للإعلان، ومغلقة والإدخال مفتوح.
- **الفحوص:** `tsc` 20 = خط الأساس، و`npm run build` نجح. المتصفح `NOT RUN`.

## 2026-11-28 (7) — `/results/entry-window`: كل فرع مجموعة بلونه، عين النشر، «إخفاء النتائج» مفعّل — بطلب المالك

- **المجموعات:**
  - كل فرع قسم مستقل بإطار بلونه، وعنوانه «الفرع: …» بخلفية لونه الفاتح.
  - مربع العنوان يحدد كل صفوف الفرع وشعبه.
  - البطاقة داخل القسم تعرض «الاختصاص — الصف» فقط.
- **الألوان حسب الفرع:**
  - التجاري أزرق (212)، والزراعي أخضر (128)، والصناعي برتقالي (28).
  - كل فرع آخر له لون بالزاوية الذهبية حسب ترتيبه في فروع المدرسة.
  - كل بطاقات الفرع بنفس اللون، واللون لا يتغير مع التصفية.
- **العين:** مفتوحة (`Eye`) إذا نُشر شيء، كل الشعب أو بعضها، ومغلقة (`EyeOff`) إذا لم يُنشر شيء.
- **«إخفاء النتائج» في «تحرير»:** صار مفعّلًا دائمًا. إذا لم يكن شيء منشورًا تظهر رسالة «لا توجد نتائج منشورة لإخفائها.».
- **الفحوص:** `tsc` 20 = خط الأساس، و`npm run build` نجح. المتصفح `NOT RUN`.

## 2026-11-28 (6) — تبويبات «النتائج» بشكل «المناهج» — بطلب المالك، تنسيق فقط

- **`app-sidebar-header.tsx`:** أُضيف `/results` إلى صفحات `sis-chrome--word-tabs`. التبويب المفتوح صار بإطار رفيع وخلفية الشريط نفسها، ملتحمًا مع اللوحة تحته.
- **`title-bar-ribbon.tsx`:** صفحات `/results` تأخذ `sis-ribbon--curriculum-page` (`isResultsPage`)، أي سطح شريط المناهج الأبيض وفواصل المجموعات وعناوينها في الوسط. هو تنسيق فقط، بلا أوامر ولا سلوك.
- **الفحوص:** `tsc` 20 = خط الأساس، و`npm run build` نجح. المتصفح `NOT RUN`.

## 2026-11-28 (5) — `/results/entry-window`: إصلاح تبويب «تحرير» والتصفية، ولون لكل بطاقة — بطلب المالك

- **السبب:** الصفحة كانت تسجّل الشريط («تحرير» والتصفية) والزر الرئيسي في مكوّن يقع خارج `AppLayout`. موفّر الشريط داخل `AppLayout`، فلم يصله شيء، فلا «تحرير» ولا تصفية.
- **الإصلاح:** المحتوى صار مكوّنًا داخليًا (`EntryWindowContent`) داخل `AppLayout`، كما في صفحة المعلمين.
- **الألوان:** لكل بطاقة (الفرع — الاختصاص — الصف) لون فاتح فريد بالزاوية الذهبية (`hsl(h 70% 96%)`، إطار `hsl(h 50% 78%)`). اللون مأخوذ من موضع البطاقة في القائمة الكاملة، فلا يتغير مع التصفية.
- **الفحوص:** `tsc` 20 = خط الأساس، و`npm run build` نجح. الخادم لم يتغير. المتصفح `NOT RUN`.

## 2026-11-28 (4) — `/results/entry-window` بتنسيق النظام، «الصفوف والشعب» من بنية المدرسة، تبويب «تحرير» — بطلب المالك

- **التنسيق:**
  - غلاف `sisListPageClass` مع `sis-scroll-y-thin`.
  - أقسام بإطار `--sis-powder-blue` كما في صفحة المنهج.
  - جدول «النواقص» بثيم الجداول (`sis-admission-drafts-table`).
  - الأزرار من جسم الصفحة انتقلت إلى الشريط.
- **حُذف** سطر «عرض للتصميم فقط…». «المراحل والشعب» صارت «الصفوف والشعب».
- **الصفوف والشعب من النظام:**
  - `entryWindow` يمرر `structure`: الفروع والاختصاصات والصفوف والشعب للسنة، من `EnrollmentReadRepositoryInterface::listFilterOptions`، وهو نفس مصدر صفحات المناهج.
  - البطاقة = الفرع — الاختصاص — الصف، وتحتها شعبه.
  - التصفية في «الصفحة الرئيسية»: السنة والفرع والاختصاص والصف والشعبة، و«إلغاء الفلترة».
  - لا تُقرأ درجات ولا طلاب ولا نتائج.
- **تبويب «تحرير» (مجموعة «إدخال ونشر»):** «فتح الإدخال» أو «إغلاق الإدخال»، و«النواقص»، و«نشر» (بنافذة تأكيد)، و«إخفاء النتائج».
- **الحفظ:** الفتح والنشر ما زالا داخل الصفحة فقط، لا يُحفظان (قرار المالك السابق).
- **الفحوص (T-0138):**
  - الاختبار يتحقق من `structure.*` و`filters`، وأن `results` و`students` غير موجودة: 3/3.
  - `tsc` 20 = خط الأساس. `npm run build` نجح. `pint` نظيف.
  - `architecture:validate`: لا مخالفة في ملفات النتائج.
  - المتصفح: `NOT RUN`.

## 2026-11-28 (3) — صفحة «إدخال الدرجات / إعلان النتائج للطلبة» (`/results/entry-window`) — بطلب المالك: **تصميم فقط**

- **cyber_security: قراءة فقط.** قُرئ قسم `tab=window` في `overview.tsx` و`publication-panel.tsx` ونصوص `lang/ar.json`.
- **الوظيفة:** تُفتح من بطاقة «إدخال الدرجات» في `/results`، وهي البطاقة الوحيدة التي لها رابط.
- **المحتوى:**
  - قسم «إدخال الدرجات للهيئة التدريسية»: قفل، وحالة الإدخال، وزر فتح/إغلاق.
  - قسم «إعلان النتائج للطلبة»: المراحل والشعب بمربعات اختيار وكل المراحل، وزر «النواقص» بجدول (المرحلة والشعبة، المادة، المدرّس، النقص)، وزر «نشر» بنافذة تأكيد، وشارة «منشورة» مع «إخفاء».
- **تصميم فقط بقرار المالك:**
  - لا تُقرأ بيانات ولا يُحفظ شيء. الحالة في الصفحة نفسها وتعود عند التحديث.
  - المراحل الثلاث والشعبتان A/B ثابتة للعرض، وجدول النواقص فارغ («لا توجد بيانات بعد»).
  - في أعلى الصفحة سطر يقول إنها للتصميم فقط.
- **الخادم:**
  - مسار `GET /results/entry-window` (`results.entry_window`).
  - `ResultsPageController::entryWindow` يتحقق من `viewOfficial` وسياق المدرسة ثم يعرض الصفحة بلا خصائص.
- **الفحوص (T-0137):**
  - اختبار جديد في `PhaseUiResTermResultsPagePostgreSqlTest`. نتيجة `--filter "PhaseUiResTermResultsPage|PhaseOpsPageContentGuest"`: 3/3.
  - `tsc` 20 = خط الأساس. `npm run build` نجح.
  - `pint`: `routes/web.php` يُبلغ عن `ordered_imports`، وهو سابق لأن الـ imports لم تُلمس.
  - المتصفح: `NOT RUN`.

## 2026-11-28 (2) — «النتائج» بتنسيق نظرة «المناهج» العامة، وأسماء جديدة للبطاقات — بطلب المالك

- **`/results` يستعمل `ModuleOverview`**، المكوّن المشترك الذي تعرضه `/curriculum/curricula/overview`:
  - شريط الحصص ومفتاحه بجانب البطاقات.
  - بطاقات بأيقونة lucide كبيرة مجرّدة (`bareIcon`).
  - لون كل بطاقة بزاوية ذهبية حسب ترتيبها (`hsl(n·137.508 70% 45%)`)، كما يُلوَّن كل منهج.
  - الترتيب كما كان. النسب كلها 0 لأن الصفحة بلا بيانات.
- **`ModuleOverview`:** صار `href` اختياريًا. البطاقة بلا `href` صندوق غير قابل للنقر (`role="group"`). باقي الصفحات لم تتغير لأن كل بطاقاتها لها روابط.
- **أُزيلت** `results-board.tsx` و`results-board-icons.tsx` وقسمها في `app.css`، لأنها لم تعد مستعملة.
- **الأسماء الجديدة:**
  - «إدخال الدرجات» وتحتها «إعلان النتائج للطلبة»
  - «صلاحيات المدرسين»
  - «ما يراه الطالب في النتيجة»
  - «الصلاحيات الفردية لمدرس»
  - «إعدادات نتائج الطلبة»
  - «نسب إنجاز إدخال الدرجات»
- **بطاقتان جديدتان في آخر الصفحة:** «النتائج» (`ScrollText`) و«التقارير الأكاديمية» (`FileChartColumn`). لونهما بالطريقة نفسها (رقم 10 و11)، وبلا بيانات ولا روابط.
- **الفحوص:** `tsc` 20 = خط الأساس. `npm run build` نجح. `PhaseUiResTermResultsPage` و`PhaseOpsPageContentGuest`: 2/2 على قاعدة مؤقتة حُذفت بعد الفحص. المتصفح الحي: `NOT RUN`.

## 2026-11-28 — «النتائج» (`/results`): لوحة بتصميم لوحة تحكم cyber_security — بطلب المالك (جلسة `5e7bb484`)

- **cyber_security: قراءة فقط.** قُرئت `overview.tsx` و`settings-tabs.tsx` و`settings-icons.tsx` و`status-tiles.tsx` وألوان `themes.css`/`palette.css` والنصوص العربية من `lang/ar.json`. لم يُعدَّل فيه شيء.
- **حُذف** محتوى `/results` السابق (قائمة نتائج الفصل بمعرّف التسجيل + فلتر السنة + روابط الصفحات الفرعية).
- **جديد:**
  - `components/results/results-board.tsx`: شريط «نسبة الإنجاز في كل بطاقة» مع مفتاح الألوان، ثم 9 بطاقات بنفس الترتيب والألوان. كل بطاقة فيها شارة دائرية بالرسم، والعنوان، وشريط تقدّم.
  - `components/results/results-board-icons.tsx`: الرسوم التسعة.
  - `app.css`: قسم «النتائج» board، فيه ألوان البطاقات التسعة بقيم oklch نفسها.
  - مفاتيح `results.board.*` في `ar.ts` و`en.ts`.
- **بلا شريط جانبي أو شريط أدوات من cyber_security، وبلا بيانات:** لا مدرسين ولا مواد ولا مناهج ولا درجات ولا طلاب. الأشرطة فارغة والنسب «—».
- **الخادم:** `ResultsPageController::index` يتحقق من `viewOfficial` وسياق المدرسة ثم يعرض `results/index` بلا خصائص. لا قراءة للنتائج، فلا يُسجَّل حدث وصول إلى البيانات.
- **باقية كما هي:** الصفحات `/results/show` و`/term` و`/transcript` و`/publications` و`/term-publications` ومساراتها. الوصول إليها الآن بعنوانها فقط، فرابطها كان في الصفحة المحذوفة.
- **الاختبارات:** حُدّث `PhaseUiResTermResultsPagePostgreSqlTest` (يتحقق أن `results` و`filters` غير موجودة) و`GradesResultsPickersWebPostgreSqlTest` (أُزيلت منه فحوص منتقي `/results`). النتائج في T-0134.

## 2026-10-27 مساءً (2) — إكمال واجهة بنك الأسئلة (L-F7، L-F8) — بموافقة المالك

**جديد:**
- `resources/js/components/bank/question-status.tsx`: شارة الحالة على `StatusBadge` + نغمات الجدول القائمة؛ `formatInstant` بمنطقة صريحة.

**معدّل:**
- `QuestionBankReadRepository`: الأوقات ISO 8601 UTC؛ `created_at` و`submitted_by_name` للنسخة.
- `QuestionBankController`: `timezone` من `attendance.default_timezone`، و`UTC` عند قيمة غير صالحة.
- `pages/bank/questions/index.tsx`: شارات الحالة، حالة التحميل، «لا نتائج» مقابل «لا أسئلة بعد»، «مسح التصفية»، عدد النتائج.
- `pages/bank/questions/show.tsx`: بطاقات `sis-ops-hub__gate`، زر رجوع، سجل الحالة بالأسماء والأوقات، سبب غياب الإجراءات.
- `ar.ts`/`en.ts`: 20 مفتاحًا في `bank`.
- الاختبارات: `QuestionBankWebPostgreSqlTest` (+ التوقيت) و`e2e/bank/questions.spec.ts` (+ التنقل والتوقيت بمتصفح UTC).

**لم يتغير:** لا migration، لا صلاحيات، لا handlers، لا `app.css`، لا `sis`.

**اكتُشف (عام في SIS):** L-F9، L-F10.

## 2026-10-27 مساءً — فحص إغلاق 7.3a + معاينة محلية

- لا تغيير في كود التطبيق.
- `e2e/bank/questions.spec.ts`: اختبار جديد (البحث، التصفية، الصفحة الفارغة، حفظ مرفوض، الإنجليزية).
- خطة L-F5 مكتوبة (PROPOSED، بلا migration).
- قاعدتان مؤقتتان (DEC-059) وخادم معاينة على قاعدة المعاينة فقط.
- اكتُشف L-F7 (أوقات UTC) وL-F8 (شكل البطاقة) — لم يُصلحا.

## 2026-10-27 — 7.3a: بنك الأسئلة (الأسئلة فقط) — PASS WITH CONDITIONS

بتفويض المالك (أجوبة DEC-047..054). قرارات التصميم DEC-055..058 **PROPOSED**. لا commit؛ `sis` لم تُرحَّل.

**جديد:**
- migration `2026_10_27_100000_create_bank_questions_and_versions.php` (مخطط `bank`، جدولان، triggers، RLS)
- `app/Domain/Bank/**` (الأنواع، الحالات، `QuestionContent`، `QuestionReviewRules`، `QuestionIdempotencyGuard`، `QuestionBankException`، واجهة المستودع)
- `app/Application/Bank/**` (6 أوامر + handlers + Results، استعلامان، منفذ نطاق التأليف، `QuestionAuthorAccess`)
- `app/Infrastructure/Bank/TeachingAssignmentQuestionAuthorScope.php`، `app/Infrastructure/Persistence/Bank/*` (كتابة + قراءة)، `Eloquent/BankQuestionRecord.php`
- `app/Security/Policies/QuestionBankPolicy.php`، `app/Http/Controllers/Bank/QuestionBankController.php`، `app/Http/Requests/Bank/*`
- `resources/js/pages/bank/questions/{index,show}.tsx`، `resources/js/components/bank/question-forms.tsx`
- الاختبارات: `tests/Unit/Domain/Bank/QuestionContentTest.php`، `QuestionBankSchemaPostgreSqlTest`، `QuestionBankWebPostgreSqlTest`، `e2e/bank/questions.spec.ts`

**معدّل:**
- `config/security.php` و`Permission.php` (3 صلاحيات؛ `grades_teacher` += view/author، `grades_manager` += الثلاث)
- `SecurityServiceProvider` (policy)، `ArchitectureServiceProvider` (3 bindings)، `SchemaHelper` (`bank`)
- `routes/web.php` (8 routes)، `ExamPageController` + `pages/exams/index.tsx` (رابط «بنك الأسئلة» في الشريط)
- `ar.ts`/`en.ts` (`bank`، `flash.bank`، `errors.codes['bank.*']`)
- `tests/Support/Database/PostgreSqlRlsActor.php` (`bank`)
- التوثيق: `database-blueprint.md` (bank + 3 جداول EXAMIS كانت ناقصة، المجموع 117)، `indexing-matrix.md`، `schema-change-impact.md`، ADR-024 (قسم «ما بُني في 7.3a»)
- المختبر: `e2e_u01_fixture.php` (إجراء `bank-approver`)

**اكتُشف:** L-F5 (CHECK يقبل NULL في جدولين سابقين)، L-F6 (tsc الكامل محجوب بالذاكرة).

## 2026-10-27 — الانحدار الكامل بعد 7.2 → 7.2 = PASS

- لا تغيير في كود التطبيق. T-0088..T-0092: فحص الجلستين (الأخرى نظّفت C: ولم تعدّل المستودع)، الانحدار الكامل 836 اختبارًا (0 فشل جديد، 14 أُصلح)، `tests/Feature/Exams` 136/136.
- المختبر: `scripts/compare_runs.py` (فرق الاختبارات الفاشلة بين تشغيلين).

## 2026-10-26/27 — 7.2: إدارة الامتحانات والجلسات من الويب — PASS WITH CONDITIONS

**جديد:**
- `app/Http/Controllers/Exams/ExamManagementController.php` (8 أفعال)
- `resources/js/components/exams/exam-forms.tsx` (نافذتا الامتحان والجلسة)
- `resources/js/components/exams/edit-ribbon.tsx`
- `tests/…/ExamManagementWebPostgreSqlTest.php` (6)
- `e2e/exams/management.spec.ts`

**معدّل:**
- `ExamPageController.php` (`names`، `admin`)
- `routes/web.php` (8 routes + `whereNumber` لـ L-F1)
- `pages/exams/index.tsx` (أسماء، شريط «امتحان جديد/عرض»، صف مختار)
- `pages/exams/show.tsx` (أعيدت كتابته: شريط بثلاث مجموعات، أسماء، `examSession` — L-F4)
- `ar.ts` (`exams.admin.run`)
- `en.ts` (`exams`، `flash.exams`)

**لم يتغير:** لا migration · لا handler · لا مجال · لا `sis`.

## 2026-10-26 — U-03 متصفح + R-09 — PASS

- U-03: Playwright بكل المشاريع 20/20؛ spec جديد `e2e/results/term-and-transcript.spec.ts`.
- **R-09:**
  - `PortalPartyAccessService::isPortalOnly()`.
  - `HandleInertiaRequests` (`portalNav.portalOnly`).
  - `app-sidebar.tsx` (تصفية الوحدات).
  - `pages/dashboard.tsx` (بلاطة «نتائجي» وحدها للحساب البوابي فقط).
  - `tests/…/PortalNavigationPostgreSqlTest.php` (3).
  - `e2e/portal/navigation.spec.ts`.
- `public/build` أُعيد بناؤه مرتين (DEC-040)؛ النسخة قبل U-03 في `sis-examis-lab/build-backup-2026-10-26-pre-u03`.
- المختبر: `e2e_u01_fixture.php` (`term`، `transcript`).

## 2026-10-25 — U-03: نشر نتائج الفصول وكشوف الدرجات — PASS WITH CONDITIONS

**جديد:**
- `app/Http/Controllers/Results/TermResultPublicationController.php`
- `resources/js/pages/results/term-publications.tsx`
- `resources/js/components/results/active-publications.tsx` (قائمة النشرات + السحب، مشتركة)
- `tests/Feature/Database/PostgreSql/TermAndTranscriptPublicationPostgreSqlTest.php` (12)

**معدّل:**
- `routes/web.php` (+2 routes، import)
- `ResultPublicationController.php` (خلية `transcript` في الصفوف)
- `pages/results/publications.tsx` (زر/عمود الكشوف، رابط صفحة الفصول، استعمال `ActivePublications`)
- `ar.ts` / `en.ts` (`termPublications`، نصوص الكشوف، ترجمات رموز `results.batch.*` ومطابقة الفصل، `flash.results.batch.publish`)

**لم يتغير:** لا migration · لا handler · لا `public/build` · لا اتصال بـ `sis` · «نتائجي» دون تعديل.

**المختبر:** `scripts/pg_space.php` (قراءة فقط)؛ `tmp/` (مجلد TEMP على D:).

## 2026-10-25 — R-07 + R-08 + build + U-02 «نتائجي»

**R-07/R-08 (كود، غير محفوظ في commit):**
- `ResultPublicationException`: إضافة `snapshotChanged` (`results.publish.changed`) و`idempotencyConflict` (`results.publish.idempotency_conflict`).
- `WithdrawResultPublicationHandler`: رفض المفتاح المُعاد لنشر آخر.
- `PublishYearResultsHandler`: بناء اللقطة وقفلها وإدراجها في معاملة واحدة.
- `ResultPublicationRepositoryInterface::lockSnapshot` + التنفيذ في `EloquentResultPublicationRepository` (`FOR SHARE OF r`، مع `officialColumns` مشتركة مع `drifted`).
- نصوص الرمزين الجديدين في `ar.ts` و`en.ts`.

**البناء:** `npm run build` — البناء الحالي جديد، والسابق في `sis-examis-lab/build-backup-2026-10-25`.

**U-02:**
- جديد: `app/Http/Controllers/Portal/PortalPublishedResultsPageController.php` · `resources/js/pages/portal/results.tsx` (مسار موجود في HEAD، والمحتوى جديد) ·
  `tests/Feature/Database/PostgreSql/PortalResultsPagePostgreSqlTest.php` (6) · `tests/Support/Results/SeedsOfficialYearScene.php` (مستخرج من اختبار U-01) · `e2e/portal/my-results.spec.ts`.
- معدّل: `routes/web.php` (route + import؛ Pint رتّب الـ imports) · `HandleInertiaRequests.php` (`portalNav`) · `app-sidebar.tsx` (كما في HEAD + سطر تعليق) ·
  `ar.ts` / `en.ts` (`portalResults`).
- لا migration، لا كتابة على `sis`.
- المختبر: `e2e_u01_fixture.php` (إضافة `portal-user` و`lock-test`).

## 2026-10-25 — U-01 (مدخل نشر/سحب نتائج السنة) — PASS WITH CONDITIONS

**كود (غير محفوظ في commit):**
- جديد: `app/Http/Controllers/Results/ResultPublicationController.php` · `resources/js/pages/results/publications.tsx` ·
  `tests/Feature/Database/PostgreSql/ResultPublicationPagePostgreSqlTest.php` (11) · `e2e/results/publications.spec.ts` (4 + setup).
- معدّل: `routes/web.php` (3 routes + import) · `app/Application/Results/Support/PublicationSnapshotBuilder.php` (سطر واحد، أنواع) ·
  `resources/js/i18n/ar.ts` (كتلة `results.publications`) · `resources/js/i18n/en.ts` (+74 سطرًا: الصفحة، رسائل flash، رموز أخطاء النشر، المطابقة) ·
  `resources/js/pages/results/index.tsx` (رابط واحد).
- لا migration، لا تغيير في handlers أو المصدر الرسمي، لا كتابة على `sis`.
- المختبر: `e2e_u01_fixture.php`؛ probe يقبل `sis_e2e_examis_*` (DEC-030)؛ نسخة احتياطية `build-backup-2026-10-25`؛ مخرجات `runs/2026-10-25-u01/`.

## 2026-10-25 (الجلسة 3، استئناف) — U-00 مكتمل

- لا تغيير في كود التطبيق ولا `sis`. قاعدتا `sis_test_examis_20261025_pg`/`_ex` أُنشئتا وفُحصتا وحُذفتا.
- T-0021..T-0025؛ تصنيف كامل للفشلات (TEST-REGISTER §1d)؛ L-F2، L-F3؛ نتيجة بوابة U-00 في ACCEPTANCE.
- أدوات جديدة في `sis-examis-lab/scripts/`: `pint_overlap.py`، `errors_by_message.py`.

## 2026-10-24 (الجلسة 3) — U-00 جزئي

- لا تغيير في كود التطبيق ولا `sis`. قاعدتان مؤقتتان أُنشئتا وحُذفتا.
- تقوية `examis_db_probe.php` (خروج 5 عند أي عدم تطابق)؛ `phpstan_sum.py` جديد في `sis-examis-lab/scripts/`؛ مخرجات في `sis-examis-lab/runs/2026-10-24-u00-partial/`.
- T-0006..T-0020 في سجل الاختبارات. R1 أُوقف بطلب المالك قبل انتهائه.

## 2026-10-24 (الجلسة 2) — تدقيق R-01 وإثبات عزل الاختبار

- **لا تغيير في كود التطبيق ولا قاعدة `sis`.** قاعدة مؤقتة `sis_test_examis_20261024_1` أُنشئت ورُحّلت بالاختبارات ثم حُذفت.
- سُجّل تفويض إعادة البناء كقرارات DEC-020..028؛ حُسمت DEC-P01/P02/P05؛ أُضيفت DEC-P06.
- جرد آلي: 25 handler يتيم من 73 (L-A8)؛ مسار R-01 (L-A9)؛ تصنيف 52 مدخلًا في Git (L-G)؛ L-F1.
- T-0003..T-0005 في سجل الاختبارات؛ خطة U-00 وU-01 في معايير القبول؛ العقد: لغتان وتجاوب (DEC-026).
- المختبر: `D:\Projects\sis-examis-lab\README.md` و`scripts/` (خارج المستودع).

## 2026-10-24 — تأسيس البروتوكول (المراحل 0–2، بداية 3 و5)

- **وثائق فقط. لا تغيير في كود التطبيق ولا قاعدة البيانات ولا SurveyKing.**
- أُنشئ `docs/examis/` (14 ملفًا): MASTER-PLAN، PROJECT-STATE، DECISIONS، PARITY-MATRIX، SURVEYKING-ANALYSIS، LEGACY-REGISTER، TEST-REGISTER،
  CHANGELOG، NEXT-SESSION، UI-CONTRACT، SECURITY-MODEL، DATA-MODEL، ACCEPTANCE-CRITERIA، OPERATIONS.
- أُضيف قسم `EXAMIS PROJECT PROTOCOL` إلى `CLAUDE.md` (إضافة فقط، لم يُحذف شيء).
- أُضيف الإجراء `.claude/skills/examis-project-protocol/SKILL.md`.
- لم يُعمل commit (بانتظار طلب المالك).
