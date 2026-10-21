// Renders the page inventory the crawl (e2e/smoke/pages.spec.ts) records in storage/app/e2e/inventory into
// docs/testing/SIS-UI-INVENTORY.md: every page with its component, fields (label · kind · required), commands and
// table columns. Run after a full suite: node e2e/support/coverage-inventory.mjs
import fs from 'node:fs';
import path from 'node:path';

const dir = path.join('storage', 'app', 'e2e', 'inventory');
const out = path.join('docs', 'testing', 'SIS-UI-INVENTORY.md');
const pages = fs.readdirSync(dir).filter((f) => f.endsWith('.json')).map((f) => JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8')))
    .sort((a, b) => a.uri.localeCompare(b.uri));

// Row-level controls repeat per record (a checkbox / «عرض أماكن التدريس» per teacher …): count them, list the rest.
const perRow = /^(تحديد|اختيار|عرض أماكن التدريس|صفحة \d+|\d+)[:\s]|^\d+$/;
const esc = (s) => String(s ?? '').replace(/\|/g, '\\|').replace(/\n/g, ' ');

let md = `# SIS — UI inventory (generated)

Generated from the crawl of every page (\`e2e/smoke/pages.spec.ts\`) on ${new Date().toISOString().slice(0, 10)} — the
administrator's view of the seeded e2e dataset. Per page: the Inertia component, the visible fields (label · kind ·
required), the commands (buttons, tabs, menu items — per-row controls counted, not listed) and the tables. Dialogs
are not open in this snapshot; their fields are covered in the module specs (see SIS-DATA-AND-UI-COVERAGE.md).

| Pages | Fields | Commands | Tables |
|---|---|---|---|
| ${pages.length} | ${pages.reduce((n, p) => n + p.fields.length, 0)} | ${pages.reduce((n, p) => n + p.buttons.length, 0)} | ${pages.reduce((n, p) => n + p.tables.length, 0)} |

`;
for (const p of pages) {
    const rowFields = p.fields.filter((f) => perRow.test(f.label));
    const fields = p.fields.filter((f) => !perRow.test(f.label));
    const rowButtons = p.buttons.filter((b) => perRow.test(b));
    const buttons = p.buttons.filter((b) => !perRow.test(b));
    md += `## \`/${p.uri}\` — ${esc(p.headings?.[0] ?? p.title)}\n\n`;
    md += `Component \`${p.component}\` · ${p.fields.length} fields (${rowFields.length} per-row) · ${p.buttons.length} commands (${rowButtons.length} per-row) · ${p.dialogTriggers} dialog triggers\n\n`;
    if (fields.length) {
        md += `Fields: ${fields.map((f) => `${esc(f.label || f.name || '—')} (${f.kind}${f.required ? ', required' : ''})`).join(' · ')}\n\n`;
    }
    if (buttons.length) {
        md += `Commands: ${buttons.map(esc).join(' · ')}\n\n`;
    }
    for (const t of p.tables) {
        md += `Table (${t.rows} rows): ${t.columns.map(esc).join(' · ') || '—'}\n\n`;
    }
}
fs.writeFileSync(out, md);
console.log(`${out}: ${pages.length} pages`);
