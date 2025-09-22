/**
 * Open Cookie Consent - Consent Engine
 * Handles consent state, localStorage, script gating, and Google Consent Mode v2
 */

(function() {
    'use strict';

    const STORAGE_KEY = 'occ_consent_v1';

    window.occ = window.occ || {};

    class ConsentEngine {
        constructor() {
            this.state = this.loadState();
            this.categories = {};
            this.callbacks = [];
            this.reloadSuggested = false;

            this.initGCM();
        }

        loadState() {
            try {
                const stored = localStorage.getItem(STORAGE_KEY);
                if (stored) {
                    const parsed = JSON.parse(stored);
                    if (parsed && typeof parsed === 'object') {
                        parsed.choices = parsed.choices || {};
                        return parsed;
                    }
                }
            } catch (e) {
                console.warn('OCC: Failed to load consent state from localStorage', e);
            }

            return {
                version: '',
                choices: {},
                timestamp: 0
            };
        }

        saveState() {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(this.state));
            } catch (e) {
                console.warn('OCC: Failed to save consent state to localStorage', e);
            }
        }

        get() {
            return {
                version: this.state.version,
                choices: { ...this.state.choices },
                timestamp: this.state.timestamp
            };
        }

        set(category, status) {
            if (!category || !['granted', 'denied'].includes(status)) {
                return false;
            }

            const categoryConfig = this.categories[category];
            if (categoryConfig?.locked) {
                status = 'granted';
            }

            if (this.state.choices[category] === status) {
                return false;
            }

            this.state.choices[category] = status;
            this.state.timestamp = Date.now();
            this.saveState();

            this.applyGating();
            this.updateGCM();
            this.triggerCallbacks();
            return true;
        }

        setMultiple(choices) {
            let changed = false;

            Object.entries(choices || {}).forEach(([category, status]) => {
                if (!['granted', 'denied'].includes(status)) {
                    return;
                }

                const categoryConfig = this.categories[category];
                if (categoryConfig?.locked) {
                    status = 'granted';
                }

                if (this.state.choices[category] !== status) {
                    this.state.choices[category] = status;
                    changed = true;
                }
            });

            if (changed) {
                this.state.timestamp = Date.now();
                this.saveState();
                this.applyGating();
                this.updateGCM();
                this.triggerCallbacks();
            }

            return changed;
        }

        grantAllNonNecessary() {
            const choices = {};

            Object.entries(this.categories).forEach(([category, config]) => {
                if (config?.locked) {
                    choices[category] = 'granted';
                } else {
                    choices[category] = 'granted';
                }
            });

            return this.setMultiple(choices);
        }

        rejectAllNonEssential() {
            const choices = {};

            Object.entries(this.categories).forEach(([category, config]) => {
                choices[category] = config?.locked ? 'granted' : 'denied';
            });

            return this.setMultiple(choices);
        }

        updateVersion(version) {
            if (typeof version !== 'string') {
                return false;
            }

            if (this.state.version !== version) {
                this.state.version = version;
                this.saveState();
                return true;
            }

            return false;
        }

        hasVersionMismatch() {
            const currentVersion = window.occInventoryVersion || '';
            if (!currentVersion) {
                return false;
            }

            if (!this.state.timestamp) {
                return false;
            }

            return this.state.version !== currentVersion;
        }

        applyGating() {
            const result = this.processGatedScripts();
            this.reloadSuggested = result.disabled > 0;
            return result;
        }

        processGatedScripts() {
            const gatedScripts = document.querySelectorAll('script[type="text/plain"][data-occ-category]');
            let enabledCount = 0;
            let disabledCount = 0;

            gatedScripts.forEach((script) => {
                const category = script.getAttribute('data-occ-category');
                const status = this.state.choices[category];

                if (status === 'granted') {
                    enabledCount += this.enableScript(script);
                } else {
                    if (this.disableScript(script)) {
                        disabledCount += 1;
                    }
                }
            });

            return { enabled: enabledCount, disabled: disabledCount };
        }

        enableScript(script) {
            if (script.hasAttribute('data-occ-enabled')) {
                return 0;
            }

            let replacementId = script.getAttribute('data-occ-replacement-id');
            if (!replacementId) {
                replacementId = `occ-replacement-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
                script.setAttribute('data-occ-replacement-id', replacementId);
            }

            const newScript = document.createElement('script');

            if (script.hasAttribute('data-src')) {
                newScript.src = script.getAttribute('data-src');
            }

            if (script.textContent.trim()) {
                newScript.textContent = script.textContent;
            }

            Array.from(script.attributes).forEach((attr) => {
                if (!['type', 'data-occ-category', 'data-src', 'data-occ-enabled', 'data-occ-replacement-id'].includes(attr.name)) {
                    newScript.setAttribute(attr.name, attr.value);
                }
            });

            newScript.setAttribute('data-occ-injected', 'true');
            newScript.setAttribute('data-occ-replacement-id', replacementId);

            script.setAttribute('data-occ-enabled', 'true');
            script.parentNode.insertBefore(newScript, script.nextSibling);
            return 1;
        }

        disableScript(script) {
            if (!script.hasAttribute('data-occ-enabled')) {
                return false;
            }

            let removed = false;
            const replacementId = script.getAttribute('data-occ-replacement-id');

            if (replacementId) {
                const injected = document.querySelectorAll(`script[data-occ-replacement-id="${replacementId}"][data-occ-injected]`);
                injected.forEach((node) => {
                    if (node.parentNode) {
                        node.parentNode.removeChild(node);
                        removed = true;
                    }
                });
            } else {
                let sibling = script.nextSibling;
                while (sibling) {
                    if (sibling.nodeType === Node.ELEMENT_NODE && sibling.tagName === 'SCRIPT' && sibling.getAttribute('data-occ-injected')) {
                        sibling.parentNode.removeChild(sibling);
                        removed = true;
                    }
                    sibling = sibling.nextSibling;
                }
            }

            script.removeAttribute('data-occ-enabled');
            script.removeAttribute('data-occ-replacement-id');
            return removed;
        }

        shouldReload(reset = true) {
            const value = !!this.reloadSuggested;
            if (reset) {
                this.reloadSuggested = false;
            }
            return value;
        }

        initGCM() {
            if (!window.gtag || !window.occData?.settings?.gcmv2) {
                return;
            }

            this.updateGCM();
        }

        updateGCM() {
            if (!window.gtag || !window.occData?.settings?.gcmv2) {
                console.log('OCC: GCM update skipped - gtag or gcmv2 not available');
                return;
            }

            const analytics = this.state.choices.analytics || 'denied';
            const advertising = this.state.choices.advertising || this.state.choices.marketing || 'denied';
            
            const consentUpdate = {
                security_storage: 'granted',
                analytics_storage: analytics,
                ad_storage: advertising,
                ad_user_data: advertising,
                ad_personalization: advertising
            };

            console.log('OCC: Sending GCM update:', consentUpdate);
            window.gtag('consent', 'update', consentUpdate);
        }

        setCategories(categories) {
            this.categories = categories || {};

            Object.entries(this.categories).forEach(([key, config]) => {
                if (!(key in this.state.choices)) {
                    this.state.choices[key] = config?.locked ? 'granted' : 'denied';
                } else if (config?.locked) {
                    this.state.choices[key] = 'granted';
                }
            });

            Object.keys(this.state.choices).forEach((key) => {
                if (!(key in this.categories)) {
                    delete this.state.choices[key];
                }
            });

            this.saveState();
            this.applyGating();
        }

        onChange(callback) {
            if (typeof callback === 'function') {
                this.callbacks.push(callback);
            }
        }

        triggerCallbacks() {
            this.callbacks.forEach((callback) => {
                try {
                    callback(this.get());
                } catch (e) {
                    console.warn('OCC: Callback error', e);
                }
            });
            
            // Sync with WP Consent API after consent changes
            this.syncWithWpConsentApi();
            
            // Emit event for WP Consent API integration
            this.emitEvent('occ:consent_updated', { categories: this.state.choices });
        }

        sendConsentUpdate(action, source = 'unknown') {
            try { this.pushDataLayer(action, source); } catch (e) {}
            try { this.sendPlausible(action, source); } catch (e) {}
        }

        pushDataLayer(action, source) {
            const eventName = window.occData?.settings?.datalayerEvent || 'occ_consent_update';
            if (!eventName) {
                return;
            }

            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: eventName,
                occ_action: action,
                occ_source: source,
                occ_categories: { ...this.state.choices },
                occ_version: this.state.version
            });
        }

        sendPlausible(action, source) {
            const settings = window.occData?.settings?.plausible || {};
            if (!settings.enabled) {
                return;
            }

            const payload = {
                name: settings.event_name || 'cookie_consent',
                url: location.href,
                domain: location.hostname,
                props: {
                    action,
                    source,
                    categories: Object.entries(this.state.choices)
                        .map(([key, value]) => `${key}=${value}`)
                        .join(';')
                }
            };

            const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
            if (navigator.sendBeacon) {
                navigator.sendBeacon('https://plausible.io/api/event', blob);
            } else {
                fetch('https://plausible.io/api/event', {
                    method: 'POST',
                    body: blob,
                    keepalive: true,
                    headers: { 'Content-Type': 'application/json' }
                }).catch(() => {});
            }
        }

        emitEvent(eventName, detail = {}) {
            const event = new CustomEvent(eventName, {
                detail: {
                    categories: { ...this.state.choices },
                    source: detail.source || 'unknown',
                    ...detail
                },
                bubbles: true,
                cancelable: false
            });

            document.dispatchEvent(event);
        }
        
        /**
         * WP Consent API Integration
         */
        syncWithWpConsentApi() {
            if (typeof window.wp_set_consent !== 'function') {
                return;
            }
            
            // Check if WP Consent API integration is enabled
            const wpConsentEnabled = window.occData?.settings?.integrations?.wp_consent_api?.enabled ?? true;
            if (!wpConsentEnabled) {
                return;
            }
            
            // Map OCC categories to WP Consent API categories
            const mapping = {
                'functional': 'functional',
                'necessary': 'functional', 
                'security': 'functional',
                'analytics': 'statistics',
                'personalization': 'preferences',
                'marketing': 'marketing'
            };
            
            // Sync each category
            Object.entries(this.state.choices).forEach(([occCategory, status]) => {
                const wpCategory = mapping[occCategory];
                if (wpCategory) {
                    const wpStatus = (status === 'granted') ? 'allow' : 'deny';
                    window.wp_set_consent(wpCategory, wpStatus);
                    console.log(`OCC: Synced ${occCategory} (${status}) → WP Consent API ${wpCategory} (${wpStatus})`);
                }
            });
            
            // Trigger consent type event for WP Consent API
            window.wp_consent_type = 'optin';
            const consentTypeEvent = new CustomEvent('wp_consent_type_defined');
            document.dispatchEvent(consentTypeEvent);
        }
    }

    window.occ.consent = new ConsentEngine();

    window.occ.gcm = {
        setDefaults() {
            if (!window.gtag) {
                return;
            }

            window.gtag('consent', 'default', {
                ad_storage: 'denied',
                ad_user_data: 'denied',
                ad_personalization: 'denied',
                analytics_storage: 'denied',
                security_storage: 'granted'
            });
        },

        updateFrom(consentState) {
            if (!window.gtag) {
                return;
            }

            const update = {
                security_storage: 'granted',
                analytics_storage: consentState.choices.analytics || 'denied',
                ad_storage: consentState.choices.advertising || 'denied',
                ad_user_data: consentState.choices.advertising || 'denied',
                ad_personalization: consentState.choices.advertising || 'denied'
            };

            window.gtag('consent', 'update', update);
        }
    };

    window.occ.consent.apply = function() {
        return window.occ.consent.applyGating();
    };

    window.occ.storage = {
        updateVersion(version) {
            return window.occ.consent.updateVersion(version);
        },

        get() {
            return window.occ.consent.get();
        },

        clear() {
            try {
                localStorage.removeItem(STORAGE_KEY);
                window.occ.consent.state = window.occ.consent.loadState();
                return true;
            } catch (e) {
                console.warn('OCC: Failed to clear consent state', e);
                return false;
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        try {
            if (window.occData?.settings?.advanced?.test_cookie) {
                document.cookie = 'occ_test_cookie=1; path=/; SameSite=Lax; max-age=3600';
            }
        } catch (e) {}

        if (window.occData?.settings?.categories) {
            window.occ.consent.setCategories(window.occData.settings.categories);
        } else {
            window.occ.consent.applyGating();
        }

        if (window.occ.consent.hasVersionMismatch()) {
            window.occ.ui?.showUpdatedNotice();
        }
    });
})();


