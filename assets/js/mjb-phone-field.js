/**
 * International phone field using libphonenumber-js (all countries, AsYouType formatting).
 * Requires window.libphonenumber (vendor/libphonenumber-max.js).
 * Writes E.164 into [data-mjb-phone-e164] and ISO2 into [data-mjb-phone-iso].
 */
(function () {
    'use strict';

    var lib = typeof window !== 'undefined' ? window.libphonenumber : null;

    function isoToFlag(iso) {
        iso = String(iso || '').toUpperCase();
        if (!/^[A-Z]{2}$/.test(iso)) {
            return '🏳️';
        }
        // Regional indicator symbols.
        var A = 0x1f1e6;
        return String.fromCodePoint(A + (iso.charCodeAt(0) - 65), A + (iso.charCodeAt(1) - 65));
    }

    function countryName(iso) {
        try {
            if (typeof Intl !== 'undefined' && Intl.DisplayNames) {
                var dn = new Intl.DisplayNames(['en'], { type: 'region' });
                var n = dn.of(iso);
                if (n) {
                    return n;
                }
            }
        } catch (e) {
            /* fall through */
        }
        return iso;
    }

    function buildCountries() {
        if (!lib || typeof lib.getCountries !== 'function') {
            return [];
        }
        var list = lib.getCountries().map(function (iso) {
            var dial = '';
            try {
                dial = String(lib.getCountryCallingCode(iso) || '');
            } catch (e) {
                dial = '';
            }
            return {
                iso: iso,
                dial: dial,
                name: countryName(iso),
                flag: isoToFlag(iso)
            };
        });
        list.sort(function (a, b) {
            return a.name.localeCompare(b.name, 'en', { sensitivity: 'base' });
        });
        return list;
    }

    var COUNTRIES = [];

    function ensureCountries() {
        if (!COUNTRIES.length) {
            COUNTRIES = buildCountries();
        }
        return COUNTRIES;
    }

    function byIso(iso) {
        ensureCountries();
        iso = String(iso || '').toUpperCase();
        for (var i = 0; i < COUNTRIES.length; i++) {
            if (COUNTRIES[i].iso === iso) {
                return COUNTRIES[i];
            }
        }
        return COUNTRIES[0] || { iso: 'US', dial: '1', name: 'United States', flag: '🇺🇸' };
    }

    /**
     * Detect the visitor's country for dial-code default.
     * Order: explicit override → region in navigator.languages → IANA timezone map → US.
     * Exposed as window.mjbDetectCountryIso for city autocomplete ranking.
     */
    function detectCountryIso(explicit) {
        var countries = ensureCountries();
        var valid = {};
        for (var i = 0; i < countries.length; i++) {
            valid[countries[i].iso] = true;
        }

        function accept(iso) {
            iso = String(iso || '').toUpperCase();
            // Common non-ISO aliases.
            if (iso === 'UK') {
                iso = 'GB';
            }
            return valid[iso] ? iso : '';
        }

        var forced = accept(explicit);
        if (forced) {
            return forced;
        }

        // 1) BCP 47 locale region: en-ZA → ZA, en-GB → GB, fr-CA → CA
        var langs = [];
        try {
            if (typeof navigator !== 'undefined') {
                if (navigator.languages && navigator.languages.length) {
                    for (var li = 0; li < navigator.languages.length; li++) {
                        langs.push(navigator.languages[li]);
                    }
                }
                if (navigator.language) {
                    langs.push(navigator.language);
                }
                if (navigator.userLanguage) {
                    langs.push(navigator.userLanguage);
                }
            }
        } catch (e1) {
            /* ignore */
        }
        for (var j = 0; j < langs.length; j++) {
            var tag = String(langs[j] || '');
            // en-ZA, en_ZA, zh-Hans-CN
            var m = tag.match(/[-_]([A-Za-z]{2})$/);
            if (m) {
                var fromLang = accept(m[1]);
                if (fromLang) {
                    return fromLang;
                }
            }
            // Some tags put region in the middle: zh-CN-xxx rare
            var parts = tag.split(/[-_]/);
            for (var p = parts.length - 1; p >= 1; p--) {
                if (/^[A-Za-z]{2}$/.test(parts[p]) && parts[p].length === 2 && parts[p] === parts[p].toUpperCase()) {
                    var mid = accept(parts[p]);
                    if (mid) {
                        return mid;
                    }
                }
                // Also accept lowercase region in en-za
                if (/^[A-Za-z]{2}$/.test(parts[p])) {
                    var low = accept(parts[p]);
                    // Avoid treating language subtags (en, fr) as countries when 2-letter language codes
                    // that aren't countries: only accept if in our dial list AND not pure language-only tag
                    if (low && p === parts.length - 1) {
                        return low;
                    }
                }
            }
        }

        // 2) IANA timezone → country (covers users with generic en-US browser in another country)
        var tz = '';
        try {
            tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        } catch (e2) {
            tz = '';
        }
        if (tz) {
            var fromTz = accept(timezoneToCountry(tz));
            if (fromTz) {
                return fromTz;
            }
        }

        // 3) Safe last resort
        return accept('US') || (countries[0] && countries[0].iso) || 'US';
    }

    /**
     * Compact timezone → ISO2 map for major zones (plus prefix heuristics).
     * Not exhaustive; locale region is preferred when present.
     */
    function timezoneToCountry(tz) {
        tz = String(tz || '');
        var exact = {
            'Africa/Johannesburg': 'ZA',
            'Africa/Cape_Town': 'ZA',
            'Africa/Bloemfontein': 'ZA',
            'Africa/Lagos': 'NG',
            'Africa/Nairobi': 'KE',
            'Africa/Cairo': 'EG',
            'Africa/Casablanca': 'MA',
            'Africa/Accra': 'GH',
            'Africa/Harare': 'ZW',
            'Africa/Maputo': 'MZ',
            'Africa/Gaborone': 'BW',
            'Africa/Windhoek': 'NA',
            'Africa/Maseru': 'LS',
            'Africa/Mbabane': 'SZ',
            'Africa/Lusaka': 'ZM',
            'Africa/Dar_es_Salaam': 'TZ',
            'Africa/Kampala': 'UG',
            'Africa/Addis_Ababa': 'ET',
            'Africa/Algiers': 'DZ',
            'Africa/Tunis': 'TN',
            'Europe/London': 'GB',
            'Europe/Dublin': 'IE',
            'Europe/Paris': 'FR',
            'Europe/Berlin': 'DE',
            'Europe/Amsterdam': 'NL',
            'Europe/Brussels': 'BE',
            'Europe/Madrid': 'ES',
            'Europe/Rome': 'IT',
            'Europe/Lisbon': 'PT',
            'Europe/Zurich': 'CH',
            'Europe/Vienna': 'AT',
            'Europe/Stockholm': 'SE',
            'Europe/Oslo': 'NO',
            'Europe/Copenhagen': 'DK',
            'Europe/Helsinki': 'FI',
            'Europe/Warsaw': 'PL',
            'Europe/Prague': 'CZ',
            'Europe/Budapest': 'HU',
            'Europe/Bucharest': 'RO',
            'Europe/Athens': 'GR',
            'Europe/Istanbul': 'TR',
            'Europe/Moscow': 'RU',
            'Europe/Kiev': 'UA',
            'Europe/Kyiv': 'UA',
            'America/New_York': 'US',
            'America/Chicago': 'US',
            'America/Denver': 'US',
            'America/Los_Angeles': 'US',
            'America/Phoenix': 'US',
            'America/Anchorage': 'US',
            'America/Honolulu': 'US',
            'America/Toronto': 'CA',
            'America/Vancouver': 'CA',
            'America/Edmonton': 'CA',
            'America/Winnipeg': 'CA',
            'America/Halifax': 'CA',
            'America/Mexico_City': 'MX',
            'America/Sao_Paulo': 'BR',
            'America/Argentina/Buenos_Aires': 'AR',
            'America/Santiago': 'CL',
            'America/Bogota': 'CO',
            'America/Lima': 'PE',
            'America/Caracas': 'VE',
            'America/Jamaica': 'JM',
            'America/Puerto_Rico': 'PR',
            'Asia/Dubai': 'AE',
            'Asia/Riyadh': 'SA',
            'Asia/Qatar': 'QA',
            'Asia/Kuwait': 'KW',
            'Asia/Bahrain': 'BH',
            'Asia/Jerusalem': 'IL',
            'Asia/Beirut': 'LB',
            'Asia/Amman': 'JO',
            'Asia/Baghdad': 'IQ',
            'Asia/Tehran': 'IR',
            'Asia/Karachi': 'PK',
            'Asia/Kolkata': 'IN',
            'Asia/Calcutta': 'IN',
            'Asia/Dhaka': 'BD',
            'Asia/Colombo': 'LK',
            'Asia/Kathmandu': 'NP',
            'Asia/Bangkok': 'TH',
            'Asia/Jakarta': 'ID',
            'Asia/Singapore': 'SG',
            'Asia/Kuala_Lumpur': 'MY',
            'Asia/Manila': 'PH',
            'Asia/Hong_Kong': 'HK',
            'Asia/Shanghai': 'CN',
            'Asia/Taipei': 'TW',
            'Asia/Seoul': 'KR',
            'Asia/Tokyo': 'JP',
            'Australia/Sydney': 'AU',
            'Australia/Melbourne': 'AU',
            'Australia/Brisbane': 'AU',
            'Australia/Perth': 'AU',
            'Australia/Adelaide': 'AU',
            'Pacific/Auckland': 'NZ',
            'Pacific/Fiji': 'FJ'
        };
        if (exact[tz]) {
            return exact[tz];
        }
        // Prefix heuristics for unlisted zones
        if (tz.indexOf('Africa/Johannesburg') === 0 || tz.indexOf('Africa/Cape_Town') === 0) {
            return 'ZA';
        }
        if (tz.indexOf('America/') === 0) {
            // Many America/* are US; leave unset rather than wrong country
            if (/America\/(New_York|Chicago|Denver|Los_Angeles|Phoenix|Detroit|Indiana|Kentucky|Boise|Sitka|Juneau|Nome|Adak|Yakutat|Menominee)/.test(tz)) {
                return 'US';
            }
            if (/America\/(Toronto|Vancouver|Edmonton|Winnipeg|Halifax|Montreal|Regina|St_Johns|Whitehorse|Yellowknife|Iqaluit)/.test(tz)) {
                return 'CA';
            }
        }
        if (tz.indexOf('Europe/London') === 0) {
            return 'GB';
        }
        if (tz.indexOf('Australia/') === 0) {
            return 'AU';
        }
        if (tz.indexOf('Asia/Tokyo') === 0) {
            return 'JP';
        }
        if (tz.indexOf('Asia/Shanghai') === 0 || tz.indexOf('Asia/Urumqi') === 0) {
            return 'CN';
        }
        if (tz.indexOf('Asia/Kolkata') === 0 || tz.indexOf('Asia/Calcutta') === 0) {
            return 'IN';
        }
        return '';
    }

    // Shared with city autocomplete ranking.
    if (typeof window !== 'undefined') {
        window.mjbDetectCountryIso = detectCountryIso;
    }

    function formatAsYouType(iso, raw) {
        if (!lib || !lib.AsYouType) {
            return String(raw || '').replace(/[^\d+()\-\s]/g, '');
        }
        try {
            var formatter = new lib.AsYouType(iso);
            return formatter.input(String(raw || ''));
        } catch (e) {
            return String(raw || '');
        }
    }

    function toE164(iso, nationalInput) {
        if (!lib) {
            return '';
        }
        var raw = String(nationalInput || '').trim();
        if (!raw) {
            return '';
        }
        try {
            // Prefer national number + country.
            var parsed = null;
            if (typeof lib.parsePhoneNumberFromString === 'function') {
                parsed = lib.parsePhoneNumberFromString(raw, iso);
            } else if (typeof lib.parsePhoneNumber === 'function') {
                parsed = lib.parsePhoneNumber(raw, iso);
            }
            if (parsed && typeof parsed.format === 'function') {
                return parsed.format('E.164');
            }
            if (parsed && parsed.number) {
                return parsed.number;
            }
        } catch (e) {
            /* fall through */
        }
        // Fallback: dial + digits.
        var digits = raw.replace(/\D/g, '');
        var c = byIso(iso);
        if (digits.charAt(0) === '0') {
            digits = digits.slice(1);
        }
        if (!digits) {
            return '';
        }
        return '+' + c.dial + digits;
    }

    function closeMenus(except) {
        var menus = document.querySelectorAll('[data-mjb-phone-menu]');
        for (var i = 0; i < menus.length; i++) {
            var menu = menus[i];
            var root = menu.closest('[data-mjb-phone]');
            if (except && root === except) {
                continue;
            }
            menu.hidden = true;
            if (root) {
                root.classList.remove('is-open');
                var btn = root.querySelector('[data-mjb-phone-cc]');
                if (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                }
            }
        }
    }

    function renderMenu(root, filter) {
        var menu = root.querySelector('[data-mjb-phone-menu]');
        if (!menu) {
            return;
        }
        var q = String(filter || '').toLowerCase().trim();
        menu.innerHTML = '';

        // Search box at top of list.
        var searchWrap = document.createElement('div');
        searchWrap.className = 'mjb-phone-field__search';
        var search = document.createElement('input');
        search.type = 'search';
        search.className = 'mjb-phone-field__search-input';
        search.placeholder = 'Search country…';
        search.setAttribute('aria-label', 'Search country');
        search.value = filter || '';
        searchWrap.appendChild(search);
        menu.appendChild(searchWrap);

        var list = document.createElement('div');
        list.className = 'mjb-phone-field__options';
        menu.appendChild(list);

        var countries = ensureCountries();
        var count = 0;
        for (var i = 0; i < countries.length; i++) {
            var c = countries[i];
            var hay = (c.name + ' ' + c.iso + ' +' + c.dial).toLowerCase();
            if (q && hay.indexOf(q) === -1) {
                continue;
            }
            count++;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mjb-phone-field__option mjb-ac__item';
            btn.setAttribute('role', 'option');
            btn.setAttribute('data-iso', c.iso);
            btn.innerHTML =
                '<span class="mjb-phone-field__option-flag" aria-hidden="true">' +
                c.flag +
                '</span>' +
                '<span class="mjb-phone-field__option-name">' +
                c.name +
                '</span>' +
                '<span class="mjb-phone-field__option-dial">+' +
                c.dial +
                '</span>';
            list.appendChild(btn);
        }

        if (!count) {
            var empty = document.createElement('div');
            empty.className = 'mjb-ac__empty';
            empty.textContent = 'No countries found';
            list.appendChild(empty);
        }

        menu.hidden = false;
        root.classList.add('is-open');
        var cc = root.querySelector('[data-mjb-phone-cc]');
        if (cc) {
            cc.setAttribute('aria-expanded', 'true');
        }

        search.addEventListener('input', function () {
            renderMenu(root, search.value);
            var next = root.querySelector('.mjb-phone-field__search-input');
            if (next) {
                next.focus();
                // Keep caret at end.
                try {
                    var len = next.value.length;
                    next.setSelectionRange(len, len);
                } catch (e) {
                    /* ignore */
                }
            }
        });
        search.addEventListener('keydown', function (e) {
            e.stopPropagation();
            if (e.key === 'Escape') {
                closeMenus();
            }
        });
        // Focus search shortly after open.
        window.setTimeout(function () {
            search.focus();
        }, 0);
    }

    function setCountry(root, iso, keepNational) {
        var country = byIso(iso);
        root._mjbPhoneCountry = country;

        var flag = root.querySelector('[data-mjb-phone-flag]');
        var dial = root.querySelector('[data-mjb-phone-dial]');
        var isoInput = root.querySelector('[data-mjb-phone-iso]');
        var national = root.querySelector('[data-mjb-phone-national]');
        var e164 = root.querySelector('[data-mjb-phone-e164]');

        if (flag) {
            flag.textContent = country.flag;
        }
        if (dial) {
            dial.textContent = '+' + country.dial;
        }
        if (isoInput) {
            isoInput.value = country.iso;
        }

        var current = national ? national.value : '';
        if (!keepNational) {
            current = '';
        }
        if (national) {
            national.value = formatAsYouType(country.iso, current);
            // Example placeholder via AsYouType if library has examples — else generic.
            national.placeholder = 'Phone number';
            try {
                if (lib && typeof lib.getExampleNumber === 'function') {
                    // Not always available without examples metadata; ignore.
                }
            } catch (e) {
                /* ignore */
            }
        }
        if (e164) {
            e164.value = toE164(country.iso, national ? national.value : '');
        }

        var cc = root.querySelector('[data-mjb-phone-cc]');
        if (cc) {
            cc.setAttribute('aria-label', country.name + ' +' + country.dial);
            cc.title = country.name + ' (+' + country.dial + ')';
        }
    }

    function syncE164(root) {
        var country = root._mjbPhoneCountry || byIso(root.getAttribute('data-default-country'));
        var national = root.querySelector('[data-mjb-phone-national]');
        var e164 = root.querySelector('[data-mjb-phone-e164]');
        if (e164) {
            e164.value = toE164(country.iso, national ? national.value : '');
        }
    }

    function enhance(root) {
        if (!root || root.getAttribute('data-mjb-phone-ready') === '1') {
            return;
        }
        root.setAttribute('data-mjb-phone-ready', '1');

        var countries = ensureCountries();
        if (!lib || !countries.length) {
            root.classList.add('is-fallback');
        }

        // Auto-detect country (locale + timezone). data-default-country only when server forces one.
        var attrDefault = root.getAttribute('data-default-country') || '';
        var autoDetect = root.getAttribute('data-auto-country') !== '0';
        var defaultIso = autoDetect
            ? detectCountryIso(attrDefault === 'auto' || attrDefault === '' ? '' : attrDefault)
            : detectCountryIso(attrDefault || 'US');
        if (!countries.some(function (c) { return c.iso === defaultIso; })) {
            defaultIso = countries[0] ? countries[0].iso : 'US';
        }
        var initial = root.getAttribute('data-value') || '';
        setCountry(root, defaultIso, false);

        if (initial) {
            try {
                var parsed = lib && lib.parsePhoneNumberFromString
                    ? lib.parsePhoneNumberFromString(initial)
                    : null;
                if (parsed && parsed.country) {
                    setCountry(root, parsed.country, false);
                    var national = root.querySelector('[data-mjb-phone-national]');
                    if (national) {
                        national.value = parsed.formatNational
                            ? parsed.formatNational()
                            : formatAsYouType(parsed.country, parsed.nationalNumber || '');
                    }
                    syncE164(root);
                }
            } catch (e) {
                /* ignore */
            }
        }

        var ccBtn = root.querySelector('[data-mjb-phone-cc]');
        var nationalInput = root.querySelector('[data-mjb-phone-national]');
        var menu = root.querySelector('[data-mjb-phone-menu]');

        if (ccBtn) {
            ccBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (menu && !menu.hidden) {
                    closeMenus();
                    return;
                }
                closeMenus(root);
                renderMenu(root, '');
            });
        }

        if (nationalInput) {
            nationalInput.addEventListener('input', function () {
                var country = root._mjbPhoneCountry || byIso(defaultIso);
                var formatted = formatAsYouType(country.iso, nationalInput.value);
                nationalInput.value = formatted;
                syncE164(root);
            });
            nationalInput.addEventListener('blur', function () {
                syncE164(root);
            });
        }

        if (menu) {
            menu.addEventListener('mousedown', function (e) {
                var opt = e.target.closest('[data-iso]');
                if (!opt) {
                    return;
                }
                e.preventDefault();
                setCountry(root, opt.getAttribute('data-iso'), true);
                closeMenus();
                if (nationalInput) {
                    nationalInput.focus();
                }
            });
        }
    }

    function init() {
        ensureCountries();
        var fields = document.querySelectorAll('[data-mjb-phone]');
        for (var i = 0; i < fields.length; i++) {
            enhance(fields[i]);
        }
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('[data-mjb-phone]')) {
            closeMenus();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeMenus();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.mjbPhoneFieldInit = init;
})();
