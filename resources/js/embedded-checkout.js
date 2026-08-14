let stripeJsPromise;

function loadStripeJs() {
    if (window.Stripe) return Promise.resolve(window.Stripe);
    if (stripeJsPromise) return stripeJsPromise;
    stripeJsPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://js.stripe.com/v3/';
        script.async = true;
        script.onload = () => window.Stripe ? resolve(window.Stripe) : reject(new Error('Stripe.js did not load.'));
        script.onerror = () => reject(new Error('Stripe.js could not be loaded.'));
        document.head.appendChild(script);
    });
    return stripeJsPromise;
}

window.embeddedCheckout = (config) => ({
    loading: true, confirming: false, error: '', checkout: null, statusTimer: null,
    async start() {
        if (!config.publishableKey) return this.fail('Payment is not currently available.');
        this.loading = true; this.error = ''; await this.destroyCheckout();
        try {
            const Stripe = await loadStripeJs();
            this.checkout = await Stripe(config.publishableKey).initEmbeddedCheckout({
                fetchClientSecret: async () => {
                    const response = await this.post(config.sessionUrl, {idempotency_key: config.idempotencyKey});
                    if (response.status === 'paid' && response.confirmation_url) {
                        window.location.assign(response.confirmation_url);
                        throw new Error('Payment is already complete.');
                    }
                    if (!response.client_secret) throw new Error('No payment session was returned.');
                    return response.client_secret;
                },
                onComplete: () => this.confirmPayment(),
            });
            this.checkout.mount('#stripe-embedded-checkout');
            this.loading = false;
        } catch (_) {
            this.fail("We couldn't load the secure payment form. Please try again.");
        }
    },
    async confirmPayment() {
        this.confirming = true; this.loading = false; this.error = '';
        await this.destroyCheckout(); await this.checkStatus();
    },
    async checkStatus() {
        try {
            const response = await this.post(config.statusUrl, {});
            if (response.status === 'paid' && response.confirmation_url) return window.location.assign(response.confirmation_url);
            if (response.status === 'failed' || response.status === 'cancelled') {
                this.confirming = false; return this.fail('Payment was not completed. Please try again.');
            }
        } catch (_) {}
        this.statusTimer = window.setTimeout(() => this.checkStatus(), 3000);
    },
    async retry() {
        this.confirming = false; window.clearTimeout(this.statusTimer);
        config.idempotencyKey = crypto.randomUUID(); await this.start();
    },
    async post(url, body) {
        const response = await fetch(url, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf}, body: JSON.stringify(body)});
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Payment request failed.');
        return data;
    },
    fail(message) { this.loading = false; this.error = message; },
    async destroyCheckout() {
        window.clearTimeout(this.statusTimer);
        if (this.checkout) { this.checkout.destroy(); this.checkout = null; }
    },
});
