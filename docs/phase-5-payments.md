# Phase 5 payment foundation

Phase 5 resolves an `AwaitingPayment` order and exercises the future payment lifecycle with a development-only fake provider.

## Initial configuration

- Provider: `fake`
- Shipping: `free_uk` (£0 for GB addresses)
- Tax: `zero_uk` (£0, provisional pending the final UK VAT decision)
- Currency: GBP

Fake payment requires `PAYMENT_PROVIDER=fake`, `FAKE_PAYMENTS_ENABLED=true`, and a `local` or `testing` application environment. It is rejected in production even if the flag is accidentally enabled.

The shipping and tax decisions live behind resolver contracts. `ResolveOrderTotals` stores the amounts used for the order, while the authoritative payability check validates the order again before each payment attempt. Prices and payment amounts use integer minor units.

`FakePaymentProvider` returns the same provider-neutral result consumed by the payment actions. Successful verified outcomes pass through `CompleteSuccessfulPayment`, which atomically marks the Payment succeeded and uses the audited Order transition to reach `Paid`. A future verified Stripe webhook can call this same completion action.

Phase 5 does not implement Stripe, real webhooks, refunds, print-ready processing or fulfilment.
