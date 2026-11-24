/**
 * Owner dashboard sales chart: one bar per day, month or year, switched without a reload.
 *
 * config: { currency, series: { daily: [...], monthly: [...], yearly: [...] } }
 * Each point: { label, short, total, count } (see ReportService::salesTrend).
 */

const STORE_KEY = 'shopapp.dashboard.salesPeriod';
const PERIODS = ['daily', 'monthly', 'yearly'];

/** Round a maximum up to a tidy axis top (1, 2, 2.5, 5 × 10^n) so gridlines land on even values. */
function niceMax(value) {
    if (value <= 0) return 1;
    const step = 10 ** Math.floor(Math.log10(value));
    for (const m of [1, 2, 2.5, 5, 10]) {
        if (m * step >= value) return m * step;
    }
    return 10 * step;
}

export function salesChart(config) {
    let saved = null;
    try {
        saved = localStorage.getItem(STORE_KEY);
    } catch {
        // storage blocked: fall back to the default view
    }

    return {
        period: PERIODS.includes(saved) ? saved : 'daily',
        active: null, // index of the hovered/focused bar

        setPeriod(period) {
            this.period = period;
            this.active = null;
            try {
                localStorage.setItem(STORE_KEY, period);
            } catch {
                // not remembered; fine
            }
        },

        get points() {
            return config.series[this.period] ?? [];
        },

        get top() {
            return niceMax(Math.max(0, ...this.points.map((p) => p.total)));
        },

        /** Four gridlines from the baseline to the axis top. */
        get ticks() {
            return [0, 0.25, 0.5, 0.75, 1].map((f) => this.top * f);
        },

        get sum() {
            return this.points.reduce((s, p) => s + p.total, 0);
        },

        get count() {
            return this.points.reduce((s, p) => s + p.count, 0);
        },

        height(point) {
            return `${(point.total / this.top) * 100}%`;
        },

        /** Thin out x labels so they never collide: every bar for 12 or fewer, else about every 5th. */
        showLabel(i) {
            const n = this.points.length;
            if (n <= 12) return true;
            const every = Math.ceil(n / 6);
            return (n - 1 - i) % every === 0; // always label the latest bar
        },

        /** Keep the tooltip inside the plot near the left and right edges. */
        tipStyle(i) {
            const n = this.points.length;
            const centre = ((i + 0.5) / n) * 100;
            if (centre < 15) return `left: ${(i / n) * 100}%`;
            if (centre > 85) return `right: ${((n - 1 - i) / n) * 100}%`;
            return `left: ${centre}%; transform: translateX(-50%)`;
        },

        money(value) {
            return `${config.currency} ${Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            })}`;
        },

        compact(value) {
            return Number(value).toLocaleString(undefined, { notation: 'compact', maximumFractionDigits: 1 });
        },
    };
}
