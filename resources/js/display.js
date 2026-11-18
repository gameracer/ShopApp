/**
 * Customer-facing display (second screen).
 *
 * The till (posApp) and the display page talk over a BroadcastChannel, which works
 * between windows of the same app: the desktop app's second-screen window, or a
 * browser popup dragged to the second monitor. No server round trips.
 *
 * Messages from the till:
 *   { type: 'cart', lines: [{ name, quantity, unit, price, subtotal }], last, total, units, method (code only, for layout),
 *     customer, cashier, cashGiven, change, amountPaid, balance }
 *   { type: 'paid', ...the same order fields, invoice, paid, change, balance }
 * From the display:
 *   { type: 'hello' }  -> the till answers with its current cart
 */

const CHANNEL = 'shopapp-customer-display';
const THANK_YOU_MS = 15000;

export function displayChannel() {
    try {
        return new BroadcastChannel(CHANNEL);
    } catch {
        return null; // very old browser: no customer display
    }
}

export function customerDisplay(config) {
    return {
        state: 'idle', // idle | cart | paid
        cart: { lines: [], total: 0, last: -1 },
        paid: null,
        now: new Date(),
        _timer: null,

        init() {
            const channel = displayChannel();
            if (channel) {
                channel.onmessage = ({ data }) => this.receive(data);
                channel.postMessage({ type: 'hello' });
            }
            setInterval(() => (this.now = new Date()), 1000);
        },

        receive(data) {
            if (data?.type === 'cart') {
                // An empty cart (e.g. the till page reloading) must not cut the thank-you screen short.
                if (!data.lines.length && this.state === 'paid') return;
                this.cart = data;
                this.state = data.lines.length ? 'cart' : 'idle';
                this.$nextTick(() => this.$refs.lines?.querySelector('[data-last]')?.scrollIntoView({ block: 'nearest' }));
            } else if (data?.type === 'paid') {
                this.paid = data;
                this.state = 'paid';
                clearTimeout(this._timer);
                this._timer = setTimeout(() => {
                    this.state = 'idle';
                    this.cart = { lines: [], total: 0, last: -1 };
                }, THANK_YOU_MS);
            }
        },

        money(value) {
            return `${config.currency} ${Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
        },

        qty(value) {
            return Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 });
        },

        /** The item just scanned or tapped. */
        get lastLine() {
            return this.cart.lines[this.cart.last] ?? null;
        },

        /** Lines and details shown on the left: the open cart, or the order just paid. */
        get order() {
            return this.state === 'paid' && this.paid?.lines ? this.paid : this.cart;
        },

        get date() {
            return this.now.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
        },

        get clock() {
            return this.now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },
    };
}
