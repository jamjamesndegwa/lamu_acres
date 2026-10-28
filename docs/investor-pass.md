# Lamu Acres Investor Pass — What subscribers get

Investor Pass is a **research subscription** on Lamu Acres. It is **not** a purchase of land, a share in a fund, or a guarantee of profit. It unlocks the **Investor workspace** and **premium published research** when staff have released that content.

Public visitors can still browse the land directory and **free intelligence previews**. Subscribers with an **active pass** get deeper tools and analysis in `/investor`.

---

## What is included (when content is published)

### 1. Investor workspace

- Dedicated dashboard at **`/investor`** with research-focused navigation.
- KPIs and summaries: latest reports, infrastructure updates, imagery, price context, watchlist snippet, and recent archive activity.
- Same account as buyer, broker, or seller roles—no separate “investor account type.”

### 2. Premium research (vs public summaries)

Released intelligence with **eligible premium fields** (not shown on public preview pages alone):

| Area | Investor workspace | Public preview |
|------|-------------------|----------------|
| **Infrastructure tracker** | Full project intelligence, milestones, premium notes where published | Summary and basic project information |
| **Planning & zoning** | Premium planning observations and due-diligence framing | Public planning summaries |
| **Land price intelligence** | Price observatory with locality context (asking prices, not sale deeds) | Public price context where available |
| **Satellite intelligence** | Comparisons and annotations where licence allows | Public comparisons where redistribution is permitted |
| **Exclusive research reports** | Full analysis, citations, **authorized downloads** where permitted | Report summaries only |

Premium content appears **only after publication**. Draft or unreleased research is never exposed.

### 3. Investment Opportunities

- Browse **published land** in an investor-only listing with **location intelligence** layered on each opportunity: area prices, infrastructure, planning, imagery, and linked research where available.
- This is **research context on listings**, not a separate property sale through the subscription.

### 4. Watchlists and alerts

- **Follow localities** and **infrastructure projects**.
- Receive notifications when relevant research is **published** (in-app and email where configured).
- **Research archive** of publications delivered to the account.

### 5. Subscription management

- View status, paid-through date, payment history, invoices, and receipts.
- **Billing methods** on the Investor Pass page (buyer, broker, or seller workspace):
  - **M-Pesa STK Push** — pays in **Kenyan shillings (KES)** on the phone.
  - **Manual finance request** — staff records payment outside M-Pesa.
- **List price** may be in USD or another currency on the plan; **M-Pesa checkout converts to KES** at checkout using configured rates (`config/subscription.php`), unless an administrator sets a fixed KES checkout amount on the plan.

---

## What Investor Pass is not

- **Not** buying or owning land through the subscription.
- **Not** a substitute for a **lawyer, surveyor, valuer, or official land search**.
- **Not** a promise of **returns**, a fixed **update schedule**, or a minimum number of reports.
- **Not** access to **unpublished** research, private seller identity, or exact parcel coordinates on the public site.

**Cancellation** ends premium access **immediately**—access does not continue until the end of a billing period.

---

## How to subscribe

1. Create or sign in to a Lamu Acres account (buyer, broker, or seller workspace).
2. Open **Investor Pass**:
   - Buyer: `/buyer/subscription`
   - Broker: `/broker/subscription`
   - Seller: `/seller/subscription`
3. Choose **M-Pesa** or **manual finance request**.
4. For M-Pesa: approve the STK prompt; the pass activates only after **payment is verified** (not when the prompt is sent).
5. Switch to the **Investor** workspace when the pass is active.

Marketing overview for visitors: **`/investor-intelligence`**.

Staff create and publish research from the staff intelligence tools (developments, reports, planning, imagery)—subscribers consume what is **released**.

---

## Local development demo data

After `php artisan migrate:fresh --seed` in the **local** environment:

| Email | Role / pass | Purpose |
|-------|-------------|---------|
| `investor@lamuacres.test` | Buyer + active pass | Full investor workspace + seeded intelligence demo |
| `mburu@lamuacres.test` | Buyer + active pass | Example **completed M-Pesa payment** and membership history |
| `research@lamuacres.test` | Staff (create drafts) | Create intelligence records |
| `editor@lamuacres.test` | Staff (publish) | Publish intelligence records |

Default password for local accounts: **`password`**.

Demo intelligence content is labelled **(demo)** and is for development only.

---

## Product name and pricing

- Plan code: **`investor_pass`**
- Display name and list price: configured in admin (**Investor Pass Plans**) and shown on the subscription page.
- M-Pesa amount: fixed KES on the plan **or** automatic conversion from the list price using configured FX rates.

For technical implementation details, see the application code under `routes/investor.php`, `app/Services/Subscription/`, and `app/Http/Controllers/Intelligence/`.
