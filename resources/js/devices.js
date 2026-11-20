/**
 * Plug-and-play hardware: USB barcode scanners and thermal receipt printers.
 *
 * Barcode scanner
 *   USB / Bluetooth scanners act as a keyboard: they "type" the code very fast and
 *   press Enter. We tell a scan apart from a person typing by the speed of the keys,
 *   undo the text the scanner typed into whatever field had focus, and fire a
 *   cancelable `barcode` event on window ({ detail: { code } }).
 *   Screens handle it (POS adds to cart, stock-in adds a line, the product form fills
 *   the SKU) by calling preventDefault(). If nothing handles it, the product with that
 *   code is opened.
 *
 * Thermal printer
 *   printReceipt(url) prints a receipt page. In the ShopApp desktop app it prints
 *   silently to the receipt printer (picked in Settings, or auto-detected). In a normal
 *   browser it opens the print dialog for the receipt only.
 */

// A scan is a run of keys typed far faster than a person can (scanners: 1-30 ms per key,
// people: 80 ms+). Judged on the average speed of the run, so one hiccup mid-code
// (Bluetooth / wireless scanners, long codes) does not split it.
const AVG_KEY_GAP_MS = 35;
const FAST_KEY_GAP_MS = 60;
const MAX_KEY_GAP_MS = 200; // one hiccup this long is allowed inside a scan, never at its start
const MIN_LENGTH = 3;
// Scanners set up without an Enter/Tab suffix: a very fast run followed by silence.
const NO_SUFFIX_IDLE_MS = 300;
const NO_SUFFIX_MIN_LENGTH = 6;
const NO_SUFFIX_AVG_GAP_MS = 25;

function config() {
    const meta = document.querySelector('meta[name="shop-devices"]');
    if (!meta) return null;
    try {
        return JSON.parse(meta.content);
    } catch {
        return null;
    }
}

// ---------------------------------------------------------------- sound

let audio = null;

export function beep(ok = true) {
    if (!config()?.beep) return;
    try {
        audio ??= new (window.AudioContext || window.webkitAudioContext)();
        const tones = ok ? [[1400, 0, 0.08]] : [[300, 0, 0.15], [220, 0.18, 0.2]];
        for (const [freq, start, length] of tones) {
            const osc = audio.createOscillator();
            const gain = audio.createGain();
            osc.type = ok ? 'sine' : 'square';
            osc.frequency.value = freq;
            gain.gain.value = 0.15;
            osc.connect(gain).connect(audio.destination);
            osc.start(audio.currentTime + start);
            osc.stop(audio.currentTime + start + length);
        }
    } catch {
        /* no audio device: ignore */
    }
}

// ---------------------------------------------------------------- scanner

function isEditable(el) {
    return el && (el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && !['checkbox', 'radio', 'button', 'submit'].includes(el.type)) || el.isContentEditable);
}

export function startScanner() {
    const cfg = config();
    if (!cfg) return; // not signed in (login / setup pages)

    // Recent keys: { ch, t, field, before } (before = the field's value before this key landed).
    let keys = [];
    let idleTimer = null;

    /** Earliest start of a trailing run of keys that looks like a scan, or -1. */
    function scanStart(endTime, avgLimit, minLength) {
        const n = keys.length;
        if (endTime - keys[n - 1]?.t > MAX_KEY_GAP_MS) return -1;
        for (let start = 0; start <= n - minLength; start++) {
            const run = keys.slice(start);
            const avg = (run[run.length - 1].t - run[0].t) / (run.length - 1);
            const gaps = run.slice(1).map((k, i) => k.t - run[i].t);
            // Starts fast (so typed letters before it aren't glued on) and has at most one hiccup.
            const steady = gaps[0] <= FAST_KEY_GAP_MS && gaps.every((g) => g <= MAX_KEY_GAP_MS)
                && gaps.filter((g) => g > FAST_KEY_GAP_MS).length <= 1;
            const keyRepeat = run.every((k) => k.ch === run[0].ch); // a held-down key, not a scan
            if (steady && avg <= avgLimit && !keyRepeat) return start;
        }
        return -1;
    }

    function takeScan(start) {
        const first = keys[start];
        const code = keys.slice(start).map((k) => k.ch).join('').trim();
        keys = [];
        // Undo what the scanner typed into the focused field.
        if (first.field && isEditable(first.field) && first.field === document.activeElement) {
            first.field.value = first.before;
            first.field.dispatchEvent(new Event('input', { bubbles: true }));
        }
        dispatch(code, cfg);
    }

    document.addEventListener('keydown', (e) => {
        if (!e.isTrusted) return; // the on-screen keyboard's Enter, not a scanner
        const now = performance.now();
        clearTimeout(idleTimer);

        if (e.key === 'Enter' || e.key === 'Tab') {
            const start = keys.length ? scanStart(now, AVG_KEY_GAP_MS, MIN_LENGTH) : -1;
            if (start === -1) {
                keys = [];
                return;
            }
            // Stop the Enter from submitting forms / picking search results.
            e.preventDefault();
            e.stopImmediatePropagation();
            takeScan(start);
            return;
        }

        // Modifier keys and shortcuts don't break a scan (scanners press Shift for capitals).
        if (['Shift', 'Control', 'Alt', 'Meta', 'CapsLock', 'NumLock'].includes(e.key) || e.ctrlKey || e.altKey || e.metaKey) return;

        // Scanners that type on the number pad while NumLock is off send End, ArrowDown… for digits.
        let ch = e.key;
        if (ch.length !== 1 && /^Numpad\d$/.test(e.code)) {
            ch = e.code.slice(-1);
            e.preventDefault(); // don't move the caret around
        }
        if (ch.length !== 1) {
            keys = [];
            return;
        }

        if (keys.length && now - keys[keys.length - 1].t > MAX_KEY_GAP_MS * 1.25) keys = []; // a pause: start over
        const field = document.activeElement;
        keys.push({ ch, t: now, field, before: isEditable(field) ? field.value : '' });
        if (keys.length > 200) keys.shift();
        if (ch !== e.key && isEditable(field)) {
            // Insert the digit ourselves, since the NumLock-off key would not type it.
            field.value += ch;
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }

        idleTimer = setTimeout(() => {
            if (keys.length < NO_SUFFIX_MIN_LENGTH) return;
            const start = scanStart(keys[keys.length - 1].t, NO_SUFFIX_AVG_GAP_MS, NO_SUFFIX_MIN_LENGTH);
            if (start !== -1) takeScan(start);
        }, NO_SUFFIX_IDLE_MS);
    }, true);
}

function dispatch(code, cfg) {
    if (!code) return;
    const event = new CustomEvent('barcode', { detail: { code }, cancelable: true });
    window.dispatchEvent(event);
    if (event.defaultPrevented) return;

    // Nobody on this screen wants it: open the product with this barcode.
    beep(true);
    window.location = `${cfg.lookupUrl}?code=${encodeURIComponent(code)}`;
}

/** JSON lookup of a product by exact barcode / SKU. Resolves to the product or null. */
export async function lookupBarcode(code) {
    const cfg = config();
    const res = await fetch(`${cfg.lookupUrl}?code=${encodeURIComponent(code)}`, {
        headers: { Accept: 'application/json' },
    });
    return res.ok ? res.json() : null;
}

// ---------------------------------------------------------------- printer

/** The ShopApp desktop bridge (desktop/preload.js), or null in a normal browser. */
export const desktop = window.shopDevice ?? null;

/**
 * Print a receipt page. Resolves to { ok, printer?, error? }.
 */
export async function printReceipt(url, options = {}) {
    if (desktop) {
        try {
            return await desktop.printReceipt(new URL(url, window.location.href).href, options);
        } catch (e) {
            return { ok: false, error: e?.message ?? String(e) };
        }
    }

    // Browser: print the receipt page alone through a hidden iframe.
    return new Promise((resolve) => {
        const frame = document.createElement('iframe');
        frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:90mm;height:400px;border:0';
        frame.setAttribute('aria-hidden', 'true');
        frame.src = url;
        frame.onload = () => {
            const win = frame.contentWindow;
            const cleanup = () => setTimeout(() => frame.remove(), 500);
            win.addEventListener('afterprint', cleanup, { once: true });
            win.focus();
            win.print();
            setTimeout(cleanup, 60000);
            resolve({ ok: true });
        };
        frame.onerror = () => resolve({ ok: false });
        document.body.appendChild(frame);
    });
}

/**
 * Show a receipt at its real paper width with Print / Close buttons.
 * Resolves to the print result ({ ok, printer?, error? }), or null when closed without printing.
 */
export function previewReceipt(url) {
    const t = { preview: 'Receipt preview', print: 'Print', close: 'Close', printing: 'Printing…', failed: 'Printing failed', ...(config().i18n ?? {}) };

    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'no-print fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML = `
            <div class="flex max-h-full w-full max-w-md flex-col overflow-hidden rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <h2 class="font-semibold text-slate-900"></h2>
                    <button type="button" data-close class="rounded-lg px-2 text-2xl leading-none text-slate-400 hover:text-slate-700" aria-label="">×</button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto bg-slate-200 p-4">
                    <div class="mx-auto w-fit bg-white shadow-md">
                        <iframe class="block border-0" style="width:80mm;height:120mm"></iframe>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 px-4 py-3">
                    <span data-status class="mr-auto text-sm"></span>
                    <button type="button" data-close class="btn-secondary"></button>
                    <button type="button" data-print class="btn-primary"></button>
                </div>
            </div>`;
        overlay.querySelector('h2').textContent = t.preview;
        overlay.querySelector('[aria-label]').setAttribute('aria-label', t.close);
        overlay.querySelector('.btn-secondary').textContent = t.close;
        const printButton = overlay.querySelector('[data-print]');
        printButton.textContent = t.print;
        const status = overlay.querySelector('[data-status]');
        const frame = overlay.querySelector('iframe');

        // Size the paper to the receipt: 58 or 80 mm wide, as long as its content.
        frame.addEventListener('load', () => {
            try {
                const body = frame.contentDocument.body;
                frame.style.width = `${body.dataset.paper === '58' ? 58 : 80}mm`;
                requestAnimationFrame(() => {
                    frame.style.height = `${Math.ceil(body.getBoundingClientRect().bottom) + 4}px`;
                });
            } catch {
                // Not readable (should not happen: same origin). Keep the default size.
            }
        });
        frame.src = url;

        let busy = false;
        const close = (result = null) => {
            document.removeEventListener('keydown', onKey, true);
            overlay.remove();
            resolve(result);
        };
        const onKey = (e) => {
            if (e.key === 'Escape' && !busy) {
                e.preventDefault();
                close();
            }
        };
        document.addEventListener('keydown', onKey, true);
        overlay.addEventListener('click', (e) => {
            if (busy) return;
            if (e.target === overlay || e.target.closest('[data-close]')) close();
        });
        printButton.addEventListener('click', async () => {
            busy = true;
            printButton.disabled = true;
            status.className = 'mr-auto text-sm text-slate-500';
            status.textContent = t.printing;
            const result = await printReceipt(url);
            busy = false;
            printButton.disabled = false;
            if (result.ok) return close(result);
            status.className = 'mr-auto text-sm text-rose-700';
            status.textContent = t.failed + (result.error ? ': ' + result.error : '');
        });

        document.body.appendChild(overlay);
        printButton.focus();
    });
}

window.ShopDevices = { beep, printReceipt, previewReceipt, lookupBarcode, desktop };
