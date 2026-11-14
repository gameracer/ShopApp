/**
 * Customer-facing display (second screen).
 *
 * The till (posApp) and the display page talk over a BroadcastChannel, which works
 * between windows of the same app: the desktop app's second-screen window, or a
 * browser popup dragged to the second monitor. No server round trips.
 *
 * Messages from the till:
 *   { type: 'cart', lines: [{ name, quantity, unit, price, subtotal }], total, method, cashGiven, change }
 *   { type: 'paid', total, paid, change, balance }
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
        cart: { lines: [], total: 0 },
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
                this.$nextTick(() => this.$refs.lines?.lastElementChild?.scrollIntoView({ block: 'end' }));
            } else if (data?.type === 'paid') {
                this.paid = data;
                this.state = 'paid';
                clearTimeout(this._timer);
                this._timer = setTimeout(() => {
                    this.state = 'idle';
                    this.cart = { lines: [], total: 0 };
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

        get clock() {
            return this.now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },
    };
}
