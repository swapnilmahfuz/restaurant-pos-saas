# Restaurant ERP SaaS V2

This is the architecture for the locked product rules. [Commercial foundation](phase-0-5-technical-spec.md) describes the approval and entitlement contracts. [Core POS](phase-1-core-pos.md) describes the Phase 1 billing contracts. [Roadmap](roadmap.md) describes delivery order.

## Tenancy and commercial controls

A restaurant email identifies the tenant. Each staff member signs in with their own email and password and receives explicit branch assignments. The Super Admin uses a separate identity and approves restaurant registrations and all branches. Branch operations and ledgers are independent. Owners can consolidate data across authorized branches. Super Admins manually grant basic and advanced modules at both levels. Revocation preserves history. Subscription expiry and manual renewals precede any gateway integration.

Public demos use per-session sample tenants. Private trials have isolated tenants, selected modules, expiry, reset, and a controlled path to a clean production tenant. External orders, payments, and messages require a production tenant and the appropriate module and role. Sample transactions never enter production financial reports.

All browser workflows require a live server response. Keep customer and operational data out of browser persistence, offline queues, background sync, and service workers. For transactional modules, show connection loss and unknown write outcomes, use idempotency keys and status lookup for retries, and keep confirmed data intact. Desktop cashier workflows prioritize keyboard speed and isolated unsent carts per tab. Use touch-friendly layouts for management, waiter, delivery, and kitchen where appropriate.

## Billing and fulfillment

Each branch chooses Pay First or Pay Later. Block incompatible changes while active orders or table visits exist. Cashier, optional waiter, and guest QR are sources. Counter service works independently of waiter assignment.

| Flow | State and accounting rules |
| --- | --- |
| Pay First | Confirm payment before kitchen release. Order numbers are unique and distinct from wooden collection tokens. Additional sales on the same token create separate bills and tickets. |
| Pay Later | A table visit can accumulate separate kitchen tickets and defaults to one consolidated final bill. Kitchen, item delivery, and settlement states are independent. An unpaid bill or payment slip has no receipt or sales recognition until settlement. |
| Payments | Configurable Cash, bKash, Nagad, Card, Other. Preserve price snapshots, exact decimal amounts, idempotent payments, and authorized, audited voids, cancels, and refunds. |
| Delivery | Track item pending, preparing, ready, served and support marking an entire order delivered. Keep preparation and delivery distinct. |

Persistent table definitions are separate from visits. Tables support Single, 2-Seater, 4-Seater, and Family types, editable capacity, an identifier, optional floor/section, and active status. A created table receives an opaque, revocable QR token. Support viewing, individual PNG, print-ready PDF, bulk branch PDF, labeled output, and regeneration. The public route shows availability for the token's branch, limits spam and duplicates, and opens a pending request with notes. The cashier or manager receives a notification and physically verifies the table before accepting or rejecting. Audit the decision. Pending requests create zero bills, stock movements, and tickets. Acceptance follows the branch payment mode. Waiter assignment is optional and reassignments retain history.

Reservations support name, phone, date, time, party size, table, notes, and optional advance. Check table conflicts and capacity. Track pending, confirmed, arrived, seated, completed, cancelled, and no-show. Arrival can open a table visit. An advance is reconciled without double sales recognition and uses the branch refund policy.

The KDS shows accepted branch tickets with table/token, item quantities and notes, timers, preparing and ready actions. Hide financial and HR details. Offer KDS, printed tickets, or both, with station routing as an advanced capability. Preserve large item names, quantities, and token on printed kitchen tickets, with a prominent Additional Order marker.

Receipts are printed, digital, or hybrid per branch and entitlement. Support 58/80 mm templates and hardware-dependent cutting. The default digital delivery is a secured web receipt with share QR and download. Automated SMS and WhatsApp require a later approved integration. Keep payment slips distinct from paid receipts.

## Analytics, customers, and finance

The sales dashboard opens after login, in fresh tabs, and on normal workspace refresh. Special routes and open drafts retain their behavior. Show cards for gross total sales, discount, orders, average order value and items sold, then an interactive local hourly graph and top items by descending quantity with sales amount. Use Asia/Dhaka initially, include zero-sale hours and hour/amount tooltips, and aggregate multi-day data by local hour. Include valid additional orders. Use one dropdown for Today, Yesterday, This Week, Last Week, This Month and Lifetime. Every new tab and refresh defaults to Today. A separate From/To and Apply control filters the current view. Apply filters consistently. Recognize Pay Later sales at settlement, exclude voids, and reconcile refunds once.

**Operational P&L = Total Sales including VAT, SD and service charge minus recorded Total Expenses.** Actual VAT/SD paid is an ordinary expense on the owner's chosen expense date. Count the full recorded expense, including its unpaid portion, with no second automatic tax or recipe-cost/COGS deduction. Track amount, paid and unpaid, category, date, duplicate prevention and audited owner edits. Graph P&L daily, weekly, monthly, yearly or by custom range at branch and consolidated owner scope. Keep statutory accounting outside this feature.

Generate matching permissioned server-side Excel reports for orders, sales, items, payments, expenses, cash, waiter/table sales, reservations, P&L and payroll with date and branch filters. Daily closing supports denomination counts, autosaved editable drafts, explicit final close, expected versus counted, shift handover, checklists and owner-controlled sensitive edits.

Name and phone collection is optional for cashier, waiter and guest. Marketing consent is separate from receipt collection. Restaurant-wide CRM includes branch visits, spending and last visit with permissioned exports. Loyalty and offers are later extensions.

## Inventory, procurement, and workforce

Chefs draft recipes by ingredient, units, portion/yield, waste allowance, sub-recipes and estimated cost. Managers or owners approve versioned recipes. Support branch versions and preserve historical inputs. Ingredient inventory tracks unit conversion, locations, purchase receipts, waste/corrections and optional two-sided branch transfers. Consumption posts exactly once at the selected trigger with audited reversals. Pending or rejected guest requests have no stock effect.

Procurement tracks preferred supplier, ingredient/supplier lead time, safety buffer, package minimums and expected receipts. Recompute warnings on lead-time changes. Use transparent demand baselines from sales, seasonality, weekday, promotions and optional available weather, showing assumptions and confidence. Recommend date and quantity with reorder soon, order now and stockout risk. Use human-approved requisition and purchase orders, receipt and payable records. Avoid duplicate expense recognition.

Owner and authorized HR/manager handle employee profiles independent of POS logins, branch assignments, joining dates, salaries, roster, shifts, holidays, paid and unpaid leave, daily deduction policy, timestamped advances and multi-month recovery. Payroll goes from preview to owner approval to finalization with PDF payslips, Excel reports, payment status, and audited corrections. Expense integration counts advances and disbursements once. Manual attendance and corrections precede any fingerprint or RFID device integration.

## Portability

Use the existing Laravel 13, PHP 8.4 dependency floor, relational migrations, and MySQL/MariaDB-compatible schema. Start with five customers and size indexes and transaction boundaries around tenant/branch access. Keep environment settings portable. Production hosting, payment gateways, messaging, and hardware are selected separately. Backups, restore tests, and shared session/cache configuration belong to deployment validation.
