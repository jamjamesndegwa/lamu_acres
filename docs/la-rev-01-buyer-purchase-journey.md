# LA-REV-01 — Buyer purchase journey

This document describes how Lamu Acres records buyer purchase interest without creating a separate purchase engine.

## Buyer actions (published, available land)

| Action | Route | Record |
|--------|--------|--------|
| Enquire | `POST land.enquiries.store` | `Enquiry` |
| Book site visit | `POST land.visits.store` | `SiteVisit` |
| Make an offer | `POST land.offers.store` | `Offer` |
| **I'm interested in buying** | `POST land.purchase-requests.store` | `Enquiry` (`purchase_request`) + `SalesTransaction` + offer row |
| Track progress | `GET buyer.enquiries.show` | Linked sales case timeline |
| Withdraw interest | `POST buyer.enquiries.withdraw` | Closes enquiry; sales outcome `withdrawn` |

Purchase interest requires a signed-in buyer with buyer workspace access. It is not a binding sale.

## Staff pipeline

- New purchase requests open (or reuse) a `SalesTransaction` with origin `enquiry`.
- Staff work: `staff.enquiries.*`, `staff.sales.*`.
- Duplicate active requests for the same buyer and property are blocked.

## Tests

- `tests/Feature/Buyer/PurchaseRequestJourneyTest.php`
- `tests/Feature/Buyer/PurchaseRequestTest.php`
- Related: `BuyerInterestTest`, `BuyerInterestNotificationTest`, `OfferSalesOriginationTest`

## Out of scope (LA-REV-02+)

Payments, escrow, completion settlement, commission recognition changes.
