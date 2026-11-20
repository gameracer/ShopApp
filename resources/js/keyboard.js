/**
 * On-screen keyboard for touch screens.
 *
 * Windows only opens its own touch keyboard when no keyboard is attached, and a
 * USB barcode scanner counts as one, so POS touch screens need their own.
 * It appears when a text or number field is tapped with a finger or pen (never
 * for mouse clicks or fields focused by code), types into that field like real
 * keys (Alpine x-model and @keydown.enter keep working), and hides when focus
 * leaves the fields. Number fields get a number pad.
 *
 * Per-computer setting in localStorage: 'auto' (default) or 'off'.
 */

const PREF_KEY = 'shopapp.keyboard';
const TEXT_TYPES = ['text', 'search', 'email', 'tel', 'url', 'password', 'number', ''];
const NUMERIC_MODES = ['numeric', 'decimal', 'tel'];

const LETTERS = [
    ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', 'back'],
    ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'],
    ['a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l', "'"],
    ['shift', 'z', 'x', 'c', 'v', 'b', 'n', 'm', '-', '.'],
    ['numpad', '@', ',', 'space', '#', '/', 'hide', 'enter'],
];
const NUMBERS = [
    ['7', '8', '9', 'back'],
    ['4', '5', '6', 'clear'],
    ['1', '2', '3', 'enter'],
    ['abc', '0', '.', 'hide'],
];
const LABELS = { back: '⌫', clear: 'C', shift: '⇧', space: '', hide: '⌄', enter: '↵', numpad: '123', abc: 'ABC' };

export function keyboardPref() {
    try {
        return localStorage.getItem(PREF_KEY) === 'off' ? 'off' : 'auto';
    } catch {
        return 'auto';
    }
}

export function setKeyboardPref(value) {
    try {
        localStorage.setItem(PREF_KEY, value === 'off' ? 'off' : 'auto');
    } catch {
        // Private window: the choice lasts until the page is closed.
    }
    if (value === 'off') hide();
}

function editable(el) {
    if (!el || el.disabled || el.readOnly) return false;
    if (el.tagName === 'TEXTAREA') return true;
    return el.tagName === 'INPUT' && TEXT_TYPES.includes((el.getAttribute('type') ?? '').toLowerCase());
}

function wantsNumbers(el) {
    return el.type === 'number' || NUMERIC_MODES.includes(el.dataset.oskMode ?? el.getAttribute('inputmode'));
}

let panel = null;
let target = null;
let startValue = '';
let layout = 'letters';
let shift = false;
let pendingTouch = null;
let holdDelay = null;
let holdRepeat = null;

/** Insert text at the caret (number inputs have no caret API: append). */
function insert(text) {
    if (!target) return;
    if (target.type === 'number' || target.type === 'email') {
        target.value += text;
    } else {
        const start = target.selectionStart ?? target.value.length;
        const end = target.selectionEnd ?? start;
        target.setRangeText(text, start, end, 'end');
    }
    changed();
}

function backspace() {
    if (!target) return;
    if (target.type === 'number' || target.type === 'email') {
        target.value = target.value.slice(0, -1);
    } else {
        const start = target.selectionStart ?? target.value.length;
        const end = target.selectionEnd ?? start;
        if (start === end && start > 0) target.setRangeText('', start - 1, end, 'end');
        else if (start !== end) target.setRangeText('', start, end, 'end');
    }
    changed();
}

function changed() {
    target.dispatchEvent(new Event('input', { bubbles: true }));
}

function enter() {
    const field = target;
    const event = new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true, cancelable: true });
    if (!field.dispatchEvent(event)) return; // handled by the screen (e.g. till search)
    if (field.tagName === 'TEXTAREA') return insert('\n');
    commit();
    if (field.form) {
        hide();
        field.form.requestSubmit();
    } else {
        hide();
        field.blur();
    }
}

/** Fire `change` like a real blur would, so @change handlers run. */
function commit() {
    if (target && target.value !== startValue) {
        target.dispatchEvent(new Event('change', { bubbles: true }));
        startValue = target.value;
    }
}

function press(key) {
    switch (key) {
        case 'back': return backspace();
        case 'clear':
            target.value = '';
            return changed();
        case 'shift':
            shift = !shift;
            return render();
        case 'space': return insert(' ');
        case 'enter': return enter();
        case 'hide':
            commit();
            target?.blur();
            return hide();
        case 'numpad':
        case 'abc':
            layout = key === 'numpad' ? 'numbers' : 'letters';
            return render();
        default:
            insert(shift ? key.toUpperCase() : key);
            if (shift) {
                shift = false;
                render();
            }
    }
}

function render() {
    panel.innerHTML = '';
    panel.dataset.layout = layout;
    for (const row of layout === 'numbers' ? NUMBERS : LETTERS) {
        const line = document.createElement('div');
        line.className = 'osk-row';
        for (const key of row) {
            const button = document.createElement('button');
            button.type = 'button';
            button.tabIndex = -1;
            button.dataset.key = key;
            button.className = 'osk-key' + (key in LABELS ? ' osk-key-' + key : '') + (key === 'shift' && shift ? ' osk-on' : '');
            button.textContent = key in LABELS ? LABELS[key] : shift ? key.toUpperCase() : key;
            line.appendChild(button);
        }
        panel.appendChild(line);
    }
}

function build() {
    panel = document.createElement('div');
    panel.className = 'osk no-print';
    panel.setAttribute('aria-hidden', 'true');
    // Keys act on pointerdown and cancel it, so the field never loses focus.
    panel.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        const key = e.target.closest('.osk-key')?.dataset.key;
        if (!key || !target) return;
        press(key);
        if (key === 'back') {
            // Hold ⌫ to keep deleting.
            stop();
            holdDelay = setTimeout(() => (holdRepeat = setInterval(backspace, 70)), 450);
        }
    });
    const stop = () => {
        clearTimeout(holdDelay);
        clearInterval(holdRepeat);
    };
    panel.addEventListener('pointerup', stop);
    panel.addEventListener('pointercancel', stop);
    panel.addEventListener('pointerleave', stop);
    document.body.appendChild(panel);
}

/** Give a field its own inputmode back. */
function restore(field) {
    if (field?.dataset.oskMode === undefined) return;
    if (field.dataset.oskMode) field.setAttribute('inputmode', field.dataset.oskMode);
    else field.removeAttribute('inputmode');
    delete field.dataset.oskMode;
}

function show(field) {
    if (target && target !== field) {
        commit();
        restore(target);
    }
    if (!panel) build();
    target = field;
    startValue = field.value;
    layout = wantsNumbers(field) ? 'numbers' : 'letters';
    shift = false;
    render();
    panel.classList.add('osk-open');
    // Keep the field visible above the keyboard.
    requestAnimationFrame(() => {
        document.body.style.paddingBottom = panel.offsetHeight + 'px';
        const rect = field.getBoundingClientRect();
        if (rect.bottom > window.innerHeight - panel.offsetHeight - 16) {
            field.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    });
}

function hide() {
    restore(target);
    target = null;
    if (!panel) return;
    panel.classList.remove('osk-open');
    document.body.style.paddingBottom = '';
}

export function startKeyboard() {
    document.addEventListener('pointerdown', (e) => {
        if (panel?.contains(e.target)) return;
        const field = e.target.closest?.('input, textarea');
        if (e.pointerType === 'mouse' || keyboardPref() === 'off' || !editable(field)) {
            pendingTouch = null;
            return;
        }
        // Stop Windows' own touch keyboard from opening on top of ours.
        if (field.dataset.oskMode === undefined) {
            field.dataset.oskMode = field.getAttribute('inputmode') ?? '';
            field.setAttribute('inputmode', 'none');
        }
        pendingTouch = field;
        if (document.activeElement === field) show(field); // tapping a field that already has focus
    }, true);

    document.addEventListener('focusin', (e) => {
        if (e.target === pendingTouch) show(e.target);
        else if (target && e.target !== target) {
            commit();
            hide();
        }
        pendingTouch = null;
    });

    document.addEventListener('focusout', (e) => {
        if (e.target !== target) return;
        // Wait to see where focus went: another touched field takes over in focusin.
        setTimeout(() => {
            if (target && document.activeElement !== target) {
                commit();
                hide();
            }
        }, 0);
    });

    // Coming back to a page from the browser cache: start closed.
    window.addEventListener('pageshow', () => hide());
}

window.ShopKeyboard = { pref: keyboardPref, setPref: setKeyboardPref };
