/**
 * Shared behaviour for screens that search products (POS + stock in).
 * Searches by name, SKU/barcode or tag, and narrows by selected tag chips.
 */
function productSearch(config) {
    return {
        query: '',
        selectedTags: [],
        results: [],
        loading: false,
        highlighted: 0,
        errors: {},
        submitting: false,
        _timer: null,

        init() {
            this.search();
        },

        toggleTag(id) {
            const i = this.selectedTags.indexOf(id);
            i === -1 ? this.selectedTags.push(id) : this.selectedTags.splice(i, 1);
            this.search();
        },

        onQuery() {
            clearTimeout(this._timer);
            this._timer = setTimeout(() => this.search(), 200);
        },

        async search() {
            this.loading = true;
            const params = new URLSearchParams({ q: this.query });
            this.selectedTags.forEach((t) => params.append('tags[]', t));
            if (config.inStockOnly) params.append('in_stock', '1');

            try {
                const res = await fetch(`${config.searchUrl}?${params}`, {
                    headers: { Accept: 'application/json' },
                });
                this.results = await res.json();
                this.highlighted = 0;
            } finally {
                this.loading = false;
            }
        },

        move(step) {
            if (!this.results.length) return;
            this.highlighted = (this.highlighted + step + this.results.length) % this.results.length;
        },

        pickHighlighted() {
            const p = this.results[this.highlighted];
            if (p) this.add(p);
        },

        money(value) {
            return `${config.currency} ${Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
        },

        async post(url, payload) {
            this.submitting = true;
            this.errors = {};
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => ({}));
                if (res.status === 422) {
                    this.errors = data.errors || {};
                    return null;
                }
                if (!res.ok) {
                    this.errors = { general: [data.message || config.i18n.failed] };
                    return null;
                }
                return data;
            } catch (e) {
                this.errors = { general: [config.i18n.failed] };
                return null;
            } finally {
                this.submitting = false;
            }
        },

        get errorList() {
            return Object.values(this.errors).flat();
        },
    };
}

export function posApp(config) {
    return {
        ...productSearch({ ...config, inStockOnly: true }),
        cart: [],
        paymentMethod: 'cash',
        amountPaid: '',
        customerId: '',
        newCustomerName: '',
        newCustomerPhone: '',
        dueDate: '',
        cashGiven: '',

        add(product) {
            const line = this.cart.find((l) => l.product_id === product.id);
            if (line) {
                if (line.quantity < product.quantity) line.quantity++;
            } else if (product.quantity > 0) {
                this.cart.push({
                    product_id: product.id,
                    name: product.name,
                    unit: product.unit,
                    stock: product.quantity,
                    unit_price: product.selling_price,
                    quantity: 1,
                });
            }
            this.query = '';
            this.$nextTick(() => this.$refs.search?.focus());
        },

        remove(index) {
            this.cart.splice(index, 1);
        },

        clampQty(line) {
            line.quantity = Math.max(0.01, Math.min(Number(line.quantity) || 1, line.stock));
        },

        get total() {
            return this.cart.reduce((sum, l) => sum + Number(l.unit_price) * Number(l.quantity), 0);
        },

        get change() {
            return Math.max(0, Number(this.cashGiven || 0) - this.total);
        },

        get isCredit() {
            return this.paymentMethod === 'credit';
        },

        get balanceDue() {
            return Math.max(0, this.total - Number(this.amountPaid || 0));
        },

        async checkout() {
            if (!this.cart.length) return;
            const data = await this.post(config.storeUrl, {
                items: this.cart.map((l) => ({
                    product_id: l.product_id,
                    quantity: l.quantity,
                    unit_price: config.canEditPrice ? l.unit_price : null,
                })),
                payment_method: this.paymentMethod,
                amount_paid: this.isCredit ? this.amountPaid || 0 : null,
                customer_id: this.customerId || null,
                new_customer_name: this.customerId ? null : this.newCustomerName || null,
                new_customer_phone: this.customerId ? null : this.newCustomerPhone || null,
                due_date: this.isCredit ? this.dueDate || null : null,
            });
            if (data && data.redirect) window.location = data.redirect;
        },
    };
}

export function stockApp(config) {
    return {
        ...productSearch(config),
        lines: [],
        supplier: '',

        init() {
            this.search();
            if (config.preselected) this.add(config.preselected);
        },

        add(product) {
            if (this.lines.find((l) => l.product_id === product.id)) return;
            this.lines.push({
                product_id: product.id,
                name: product.name,
                unit: product.unit,
                current: product.quantity,
                quantity: 1,
                buying_price: product.buying_price,
                selling_price: product.selling_price,
                expiry_date: '',
            });
            this.query = '';
            this.$nextTick(() => this.$refs.search?.focus());
        },

        remove(index) {
            this.lines.splice(index, 1);
        },

        get totalCost() {
            return this.lines.reduce((s, l) => s + Number(l.buying_price || 0) * Number(l.quantity || 0), 0);
        },

        async save() {
            if (!this.lines.length) return;
            const data = await this.post(config.storeUrl, {
                supplier: this.supplier || null,
                items: this.lines.map((l) => ({
                    product_id: l.product_id,
                    quantity: l.quantity,
                    buying_price: config.canEditPrice ? l.buying_price : null,
                    selling_price: config.canEditPrice ? l.selling_price : null,
                    expiry_date: l.expiry_date || null,
                })),
            });
            if (data && data.redirect) window.location = data.redirect;
        },
    };
}
