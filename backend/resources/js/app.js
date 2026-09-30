// Browser side of card payments (Phase 6, D5). The server creates the intent (POST /payments/intent logic);
// this only opens the provider's checkout. Success is never assumed here: the confirmation page waits for the
// webhook-confirmed status from the backend.

const loadScript = (src) =>
    new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) return resolve();
        const s = Object.assign(document.createElement('script'), { src, onload: resolve, onerror: reject });
        document.head.appendChild(s);
    });

document.addEventListener('alpine:init', () => {
    window.Alpine.data('cardPayment', () => ({
        gateway: null,
        busy: false,
        error: null,
        stripe: null,
        elements: null,
        returnUrl: null,

        async start({ gateway, client, returnUrl, name }) {
            this.error = null;
            this.gateway = gateway;
            this.returnUrl = returnUrl;
            try {
                if (gateway === 'stripe') {
                    await loadScript('https://js.stripe.com/v3/');
                    this.stripe = window.Stripe(client.publishable_key);
                    this.elements = this.stripe.elements({ clientSecret: client.client_secret });
                    this.$nextTick(() => this.elements.create('payment').mount(this.$refs.stripeElement));
                } else if (gateway === 'razorpay') {
                    await loadScript('https://checkout.razorpay.com/v1/checkout.js');
                    new window.Razorpay({
                        key: client.key_id,
                        order_id: client.order_id,
                        name,
                        handler: () => (window.location.href = returnUrl),
                        modal: { ondismiss: () => (this.gateway = null) },
                    }).open();
                }
            } catch (e) {
                this.error = e?.message || String(e);
            }
        },

        async confirmStripe() {
            this.busy = true;
            const { error } = await this.stripe.confirmPayment({ elements: this.elements, confirmParams: { return_url: this.returnUrl } });
            // Only reached when the payment could not be submitted; on success Stripe redirects to returnUrl.
            this.error = error?.message ?? null;
            this.busy = false;
        },
    }));
});
