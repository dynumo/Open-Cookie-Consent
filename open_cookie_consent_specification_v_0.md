# Open Cookie Consent — Specification (v0.2.3)

**Working title:** Open Cookie Consent by Adam McBride  
**Code prefix/namespace:** `occ_` (PHP), `occ.*` (JS), CSS classes `.occ-*`  
**WordPress slug/folder:** `open-cookie-consent`  
**Licence:** GPL-2.0+  
**Primary market:** UK/EU (GDPR/UK GDPR + PECR)  
**Design values:** ethical defaults; accessibility; performance; transparency; agency‑friendly; open source.

---

## 0) Objectives & Non‑negotiables
- **Only show a banner when required**: if non‑essential cookies/scripts are detected.  
- **Block non‑essential by default** until explicit action.  
- **Google Consent Mode v2 (GCMv2)** supported out of the box.  
- **Accessible UI** (WCAG 2.2 AA): keyboard, SR, contrast, reduced motion.  
- **Edge‑friendly**: zero origin writes on user actions; plays nicely with Cloudflare Enterprise.  
- **Modular architecture** with documented contracts so components can be built in parallel (human or AI agent).  
- **Open Cookie Database (OCD)** used for detection and labelling.  
- **Privacy‑first**: no PII; logs disabled by default; new cookies stay denied.

---

## 1) System Overview

### 1.1 Components
- **Core (WP integration)**: settings, capabilities, enqueue, REST (optional), cron.  
- **UI Module**: small main popup, preferences modal, floating icon, updated‑cookies notice.  
- **Consent Engine**: state store (localStorage), script gating, GCMv2 signalling.  
- **Scanner Module**: scheduled scans (static + optional light runtime), OCD cross‑reference.  
- **Analytics Logger**: Plausible events on consent actions.  
- **(Optional) Aggregate Logger**: daily counters in DB (off by default).

### 1.2 High‑level Flow
1. Scheduled scanner builds cookie inventory snapshot and version.  
2. On page load, core injects current `inventoryVersion`.  
3. If **no non‑essential**: set GCMv2 defaults to *denied*; **no banner**.  
4. If non‑essential present: show **main popup**.  
5. User acts → Consent Engine updates state, applies gating, updates GCMv2; Plausible event optionally fired.  
6. If saved consent `version` ≠ inventory `version`, show **Updated Cookies** notice.

---

## 2) UI Specifications

### 2.1 Main Popup (small modal)
- **Content**: title, short copy, links to Privacy Policy & Cookie Policy.  
- **Buttons (left→right tab order)**: **Reject Non‑Essential**, **Preferences ›**, **Accept All**.  
- **Behaviour**: non‑dismissable without a choice (ESC allowed only if necessary to focus management; configurable).  
- **A11y**: `role="dialog"` `aria-modal="true"`, focus trap, labelled by heading.

### 2.2 Preferences Modal (expanded)
- **Categories**:
  - Necessary (includes Functional and Security as locked subcategories).
  - Analytics (toggle).
  - Personalization (toggle).
  - Marketing (toggle).
  - *(Extensible via filter for additional categories.)*  
- **Buttons (persistent)**: **Accept All**, **Reject Non‑Essential**, **Save Preferences**.  
- **Default states**: all non‑essential **denied** until user grants.  
- **A11y**: keyboard toggles (Space/Enter), visible focus, live region for “saved”.

### 2.3 Floating Reopen Icon
- Bottom corner (configurable).  
- Opens **Preferences**.  
- Pluggable registration for future “icon drawer”: `occ.icon.register({ id:'occ', element, priority:50 })`.

### 2.4 Updated‑Cookies Notice (version mismatch)
- **Trigger**: saved consent `version` ≠ `inventoryVersion`.  
- **Copy (editable)**: “We’ve updated our cookie use. New cookies were added since you set your preferences.”  
- **Buttons (exact & order)**: **Accept All**, **Review Cookies**, **Keep Current Settings**.  
- **Initial focus**: **Review Cookies** (safer default; admin configurable).  
- **Actions**:  
  - *Accept All*: grant all non‑essential; apply gating; update GCM; set `version` to current; fire Plausible `updated_accept_all`.  
  - *Review Cookies*: open Preferences; no change until save; fire `updated_review`.  
  - *Keep Current Settings*: keep existing grants; **new items remain denied**; set `version`; fire `updated_keep`.

---

## 3) Consent Engine

### 3.1 Storage & API
- **Storage**: `localStorage['occ_consent_v1'] = { version, choices:{analytics:'denied'|'granted', advertising:'denied'|'granted'}, ts }`.  
- **Public JS**:  
  - `occ.consent.get()` → state  
  - `occ.consent.set(category, status)`  
  - `occ.consent.grantAllNonNecessary()`  
  - `occ.consent.apply()` (gate/un‑gate scripts)  
  - `occ.storage.updateVersion(version)`

### 3.2 Script Gating Pattern
- Author marks non‑essential scripts:
```html
<script type="text/plain" data-occ-category="analytics" data-src="https://www.googletagmanager.com/gtag/js?id=G-XXXX"></script>
<script type="text/plain" data-occ-category="advertising">fbq('init','…');</script>
```
- Engine converts to real `<script>` when category is **granted**.  
- On revoke, reload page or disable where feasible.

### 3.3 Google Consent Mode v2
- **Defaults before any tag load** (inline): set all relevant signals to `'denied'` except `security_storage:'granted'`.  
- **Mappings**:  
  - Analytics granted → `analytics_storage:'granted'`.  
  - Advertising granted → `ad_storage,'ad_user_data','ad_personalization':'granted'`.  
  - Otherwise `'denied'`.  
- **Order**: defaults → (optionally) load GTM/gtag → update on user action.

---

## 4) Scanner Module

### 4.1 Scheduling
- **WP‑Cron** (default daily 03:00 local). Admin options: Hourly / Daily / Weekly / Custom CRON; time selector for daily.  
- **Scan scope**: homepage by default; optional list of URLs (one per line).  
- **Types**:  
  - **Static scan** (PHP): parse enqueued scripts/HTML for known trackers.  
  - **(Optional) Light runtime sampler** (JS): once during scan window to inspect `document.cookie`/globals; off by default.

### 4.2 Open Cookie Database Integration
- Ship `ocd.json` snapshot (cookie name → vendor, category, description).  
- “Update definitions” button + optional monthly auto‑update.  
- Unknown cookies flagged; admin can categorise and persist as manual mappings.  
- Contribution link to upstream project (optional).

### 4.3 Inventory & Versioning
- Snapshot saved in `occ_settings.inventory`:  
  `{ version: ISO8601 + ':' + sha256(canonical_list), cookies:[{name, category, vendor, source, confidence}], knownNames:[…] }`.  
- **Diff rules** (meaningful changes):  
  - Prompt for new non‑necessary cookies, category escalations, or newly introduced categories.  
  - Ignore expiry/path/flags and changes to “Necessary”.

### 4.4 Admin Notifications
- Optional: email/webhook on meaningful changes with human‑readable diff.  
- Optional Cloudflare API purge hook when version changes (Advanced setting).

---

## 5) Analytics Logging (Plausible)
- **Toggle**: “Send consent events to Plausible”.  
- **Event name**: default `cookie_consent` (editable).  
- **Emitted events**:  
  - `accept_all`, `reject_nonessential`, `preferences_saved`.  
  - Updated‑cookies notice: `updated_accept_all`, `updated_review`, `updated_keep`.  
- **Payload** (props): summarised categories (e.g. `analytics=granted;advertising=denied`).  
- **No GA at MVP**.

---

## 6) (Optional) Aggregate Counters
- **Off by default**.  
- Table: `{$wpdb->prefix}occ_counters (date PK, accept_all INT, reject INT, preferences INT)`.  
- Writes are **batched/debounced** via cron to avoid cache busting.  
- Retention: default 30 days (configurable).  
- No IP/UA/PII stored.

---

## 7) WordPress Admin

### 7.1 Menu & Tabs
- **Menu:** Open Cookie Consent (cap `manage_options`).  
- **Tabs:**  
  1) Overview — status, last scan, quick actions.  
  2) General — position (modal/banner), theme (Auto/Light/Dark), brand colour, radius, page links, language (en‑GB), **Conditional Banner** toggle.  
  3) Categories — manage labels/descriptions; Necessary locked.  
  4) Scanner — run now; view results; pages to scan; OCD update; runtime sampler toggle; notifications.  
  5) Integrations — GCMv2 (on), Plausible settings, DataLayer event name.  
  6) Advanced — custom CSS, optional counters, Cloudflare purge API, debug mode, export/import settings.  
  7) Help — docs, version, credits.

### 7.2 Settings Storage (single option row `occ_settings`)
```json
{
  "ui": {"position":"modal","theme":"auto","color":"#0b6","radius":10,
          "links":{"privacy_page_id":123,"cookie_page_id":456},
          "conditional_banner":true,
          "updated_notice_focus":"review"},
  "categories": {"necessary":{…},"analytics":{"label":"Analytics","desc":"…","enabled":true},
                 "advertising":{"label":"Advertising","desc":"…","enabled":true}},
  "scanner": {"interval":"daily","time_local":"03:00","custom_cron":"",
              "pages":["/"],"ocd_auto_update":true,"runtime_sampler":false,
              "notify":{"email":false,"webhook":""}},
  "inventory": {"version":"2025-09-17T03:00Z:abc…","cookies":[],"knownNames":[]},
  "integrations": {"gcmv2":true,
                   "plausible":{"enabled":true,"event_name":"cookie_consent"},
                   "datalayer_event":"occ_consent_update"},
  "advanced": {"custom_css":"","counters":{"enabled":false,"retention_days":30},
               "debug":false,"cloudflare":{"token":"","zone":""}}
}
```

---

## 8) Developer Contracts (Spec‑style)

### 8.1 Hooks
- **Actions**:
  - `occ_after_scan( array $results )`
  - `occ_before_render_banner( array $state )`
  - `occ_after_consent_update( array $state )`
  - `wp_has_consent` (WP Consent API integration)
- **Filters**:
  - `occ_categories` (add/alter categories)
  - `occ_known_trackers` (extend static heuristics)
  - `occ_should_show_banner` (override conditional logic)
  - `wp_get_consent_type` (WP Consent API category mapping)

### 8.2 JS Events (DOM `CustomEvent`)
- `occ:accept_all`, `occ:reject_nonessential`, `occ:preferences_saved`  
- `occ:updated_accept_all`, `occ:updated_review`, `occ:updated_keep`  
**Detail payload**: `{ categories: Record<string,'granted'|'denied'>, source: 'banner'|'modal'|'update' }`

### 8.3 Public JS API
```js
occ.ui.showBanner();
occ.ui.hideBanner();
occ.ui.openPreferences();
occ.ui.showUpdatedNotice(handlers, { initialFocus: 'review'|'accept'|'keep' });
occ.consent.get();
occ.consent.set(category, status);
occ.consent.grantAllNonNecessary();
occ.consent.apply();
occ.gcm.setDefaults();
occ.gcm.updateFrom(occ.consent.get());
```

---

## 9) Accessibility Requirements
- Contrast ≥ 4.5:1 (themeable).  
- Visible focus outlines; logical tab order.  
- Screen reader labels for buttons and toggles; announce state changes.  
- `prefers-reduced-motion` respected (no animations by default).  
- Icon button has accessible label: “Cookie preferences”.

---

## 10) Performance & Security
- Lightweight assets: Optimized JS and CSS with minimal footprint.  
- No synchronous origin calls on user actions.  
- REST endpoints (if enabled): capability + nonce, rate‑limited.  
- Sanitise/escape all settings; CSP‑friendly patterns; no eval.  
- Optional counters: batched via cron; never per‑click writes.

---

## 11) Multisite
- Per‑site settings by default.  
- Network settings page for defaults/inheritance.  
- WP‑CLI: export/import settings JSON across sites (post‑MVP).

---

## 12) Testing & QA
- **Unit (PHP/JS)**: settings sanitisation, consent state transitions, GCM mapping.  
- **E2E**: keyboard navigation, focus trap, gating works, updated‑cookies flow.  
- **Browsers**: evergreen + Safari 14+, iOS 14+, Android 9+.  
- **Edge cases**: GTM vs vanilla gtag, inline scripts, SPA nav, incognito storage cleared.  
- **Accessibility audit**: axe‑core pass; manual SR test (NVDA/VoiceOver).

---

## 13) Threat Model & Mitigations
- **DoS via logging** → logging off by default; counters batched; optional.  
- **Origin hammering** → no per‑event REST; runtime watcher off by default.  
- **New tracker sneaks in** → stays denied; version bump notice.  
- **Vendor lock‑in** → OCD + manual mappings; export/import.

---

## 14) Roadmap
- **v0.1**: Initial UI, Consent Engine, GCMv2, Plausible, scheduled static scan, OCD snapshot, conditional banner.  
- **v0.2**: Enhanced inventory management, WP Consent API integration, cookie revocation improvements, CSS specificity fixes.  
- **v0.3**: runtime sampler, admin diff UI, email/webhook notify, Cloudflare purge.
- **v0.4**: export/import, WP‑CLI, multisite defaults, icon‑drawer API.  
- **v1.0**: polish, docs site, translations infra, security review.

---

## 15) Implementation Status (v0.2.3)
- ✅ Banner appears **only when non‑essential detected** with conditional display.
- ✅ "Accept All", "Reject Non‑Essential", "Preferences/Save" work via mouse & keyboard, with SR support.
- ✅ Non‑essential scripts gated before consent; activated after grant; immediate cookie clearing on revocation.
- ✅ Updated‑cookies notice shows on version mismatch with proper button handling.
- ✅ WP Consent API integration with category mapping for broader WordPress ecosystem compatibility.
- ✅ High-specificity CSS approach prevents theme conflicts without aggressive resets.
- ✅ Enhanced inventory management with persistent manual entries and card-based admin interface.
- ✅ Google Consent Mode v2 integration with debugging capabilities.
- ✅ Plausible events integration; no server writes by default.
- ✅ All strings translatable; admin uses WP Settings API; inputs sanitised.
- ✅ No per‑click origin hits; compatible with Cloudflare Enterprise.

