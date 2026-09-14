# Phase Ops School Context — Start Authorization

**Status:** OPEN  
**Branch:** `feature/phase-ops-school-context`  
**Predecessor:** `feature/phase-ops-page-content` @ `a19db68`

## Root cause

Web Inertia module pages require school context (`require.school.context`), but the web session never set `current_school_id`, and `allow_implicit_single_school` was `false`. Result: 403 / empty module pages (including Window Manager iframes).

## Intent

Bootstrap allowed school into web session, share school + academic years to UI, school switcher, Arabic empty/forbidden surfaces. No guest PII. Tenant isolation preserved (only allowed school IDs).
