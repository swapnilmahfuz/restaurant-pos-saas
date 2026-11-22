# Delivery roadmap

| Phase | Reviewable increment | Acceptance risks |
| --- | --- | --- |
| 0 | Laravel, isolation, authentication, central provisioning | Cross-tenant identifiers, revocation |
| 0.5 | Approvals and role/entitlement gates, then demo/trial lifecycle and audit | Concurrent decisions, foreign tenant targets, revoked grants, expired sandboxes |
| 1 | Menu and fast counter POS, branch billing modes, order/payment state machine | Live cart isolation, immutable money and prices, retry/idempotency, concurrent settlement, void/refund authority |
| 2 | Table master/visits, guest QR, waiter assignment | Opaque/revoked tokens, physical approval, duplicate acceptance, occupied tables |
| 3 | KDS, delivery status, thermal and digital receipts | Pay First release gate, Pay Later preparation, no financial leakage, printer failure |
| 4 | Sales dashboard, cash closing, expenses, P&L and XLSX | Asia/Dhaka date boundaries, gross totals and refunds, exact locked expense formula |
| 5 | Optional customer capture and CRM | Consent, export privacy, cross-restaurant isolation |
| 6 | Reservations, conflicts, arrival and advances | Concurrency, occupancy and sales reconciliation |
| 7 | Recipe versions, costing, ingredient ledger/wastage | Unit accuracy, one-time consumption, reversals |
| 8 | Supplier, procurement, forecasting and transfers | Lead-time updates, transparent demand, payable and expense consistency |
| 9 | Employee/leave, advances, payroll, manual attendance | Approval and authorization, unpaid deduction, monthly and advance reconciliation |
| 10 | Advanced integrations and ERP | Device and gateway security, paid integration costs and restore testing |

Owner decisions before dependent work include tax rates and rounding, refund and reservation advance rules, inventory posting trigger, forecasting inputs, payroll deduction and correction rules, trial retention and renewal policy, and external integration providers. Request approval for each new phase before starting it. Review hosting independently from application development.
