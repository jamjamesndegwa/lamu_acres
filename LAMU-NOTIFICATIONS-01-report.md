# LAMU-NOTIFICATIONS-01 — Completion report

Working branch: `feature/unified-notifications`. Reference: `main` at the start of this task.

## A. Existing implementation reused

The shared workspace layout already rendered `x-notification-centre`. Laravel database notifications, recipient relationships, workspace middleware, record policies, six workspace history/open routes, MessageDesk, MessageDelivery idempotency keys, DeliverMessage, PublicationDelivery and existing workflow events were reused.

The bell was previously a text link. The layout composer counted unread notifications only for broker and staff accounts. History had no filters or mark-all action. Opening checked recipient ownership and URL origin but did not preflight record availability or permissions. Several workflows sent both a direct notification and a MessageDesk notification for the same event; document and viewing alerts sometimes sent staff to buyer pages.

## B. Improvements and changed files

- One shared accessible bell for Buyer, Seller, Broker, Staff, Admin and Investor; actual account-wide unread counts, hidden zero badge and `99+` cap.
- Seven recent updates, relative times, read state, short escaped descriptions, current attention labels and View all. Outside click and Escape close the panel; ordinary Tab navigation reaches its links. The panel is constrained to the mobile viewport using scoped CSS and existing Alpine support.
- Existing history now has All, Unread and Needs attention filters, 20-item pagination and a CSRF-protected Mark all as read action.
- NotificationInbox reads existing business states with SQL subqueries. Only displayed recent/history IDs are loaded to label attention; the layout does not load all notifications. Reading is separate from completing work. Optional verification documents do not keep a completed critical review flagged.
- Open remains scoped to the authenticated recipient, marks the alert read, checks the URL, workspace, route permissions, bound records and policies, then redirects. Deleted/inaccessible/invalid destinations return to the current authorized notification history with a clear message. Only known simple query parameters survive. Localhost and 127.0.0.1 are accepted as local development aliases on the configured port; external origins are refused.
- Staff document, viewing and offer alerts select the actual record through existing filtered review pages and anchors, including older notifications carrying their record IDs. This avoids losing an old item behind pagination.
- Application submission review, returned listings, sourcing status, sales assignments, verification assignments and buyer sales-stage progress are connected to existing workflows. Publication alerts are observed at the shared PropertyInventoryChanged event instead of duplicated in individual approval entry points.
- MessageDesk can deliver an existing typed in-app notification under its existing delivery idempotency key. Stable notification IDs prevent a retried job from adding another inbox entry. Repeated assignments/status submissions are guarded. PublicationDelivery prevents repeat development/research alerts. Optional WhatsApp share-link delivery enriches the existing inbox entry instead of creating a duplicate.
- In-app database writes participate in the business transaction. External email/SMS dispatch waits until commit. Synchronous delivery failures remain recorded without turning a saved business action into an apparent failure; worker jobs still throw for their normal retry handling. Delivery errors retain the exception class rather than potentially sensitive provider exception text.
- Existing approval requirements, capabilities, policies and private evidence storage remain in use. Application/listing alerts use generic text and direct users to protected records for sensitive staff details. Signed email-verification links are not added to inbox payloads.

Routes: each of the six workspaces retains its existing GET history and GET open routes and adds `POST /{workspace}/notifications/read-all`, named `{workspace}.notifications.read-all`. All 18 routes were inspected. The new listing listener is discovered by Laravel's existing event discovery.

No migrations, tables, dependencies, new portals, real-time infrastructure, paid services or frontend build were introduced.

Exact changed implementation/test files relative to the starting main, including formatting required by the project:

- [app/Enums/MessageEvent.php](C:/projects/Prady/lamu_acres/app/Enums/MessageEvent.php)
- [app/Http/Controllers/Buyer/OfferController.php](C:/projects/Prady/lamu_acres/app/Http/Controllers/Buyer/OfferController.php)
- [app/Http/Controllers/Staff/DocumentAccessController.php](C:/projects/Prady/lamu_acres/app/Http/Controllers/Staff/DocumentAccessController.php)
- [app/Http/Controllers/Staff/OfferController.php](C:/projects/Prady/lamu_acres/app/Http/Controllers/Staff/OfferController.php)
- [app/Http/Controllers/Staff/SellerDeskController.php](C:/projects/Prady/lamu_acres/app/Http/Controllers/Staff/SellerDeskController.php)
- [app/Http/Controllers/Staff/SiteVisitController.php](C:/projects/Prady/lamu_acres/app/Http/Controllers/Staff/SiteVisitController.php)
- [app/Http/Controllers/WorkspaceNotificationController.php](C:/projects/Prady/lamu_acres/app/Http/Controllers/WorkspaceNotificationController.php)
- [app/Jobs/DeliverMessage.php](C:/projects/Prady/lamu_acres/app/Jobs/DeliverMessage.php)
- [app/Listeners/NotifyApplicantOfApplicationDecision.php](C:/projects/Prady/lamu_acres/app/Listeners/NotifyApplicantOfApplicationDecision.php)
- [app/Listeners/NotifyEnquiryDesk.php](C:/projects/Prady/lamu_acres/app/Listeners/NotifyEnquiryDesk.php)
- [app/Listeners/NotifyListingSubmitter.php](C:/projects/Prady/lamu_acres/app/Listeners/NotifyListingSubmitter.php)
- [app/Notifications/ApplicationOutcomeNotification.php](C:/projects/Prady/lamu_acres/app/Notifications/ApplicationOutcomeNotification.php)
- [app/Notifications/ApplicationSubmittedNotification.php](C:/projects/Prady/lamu_acres/app/Notifications/ApplicationSubmittedNotification.php)
- [app/Notifications/CommissionSettlementNotification.php](C:/projects/Prady/lamu_acres/app/Notifications/CommissionSettlementNotification.php)
- [app/Notifications/DocumentAccessDecidedNotification.php](C:/projects/Prady/lamu_acres/app/Notifications/DocumentAccessDecidedNotification.php)
- [app/Notifications/ListingStatusNotification.php](C:/projects/Prady/lamu_acres/app/Notifications/ListingStatusNotification.php)
- [app/Notifications/OfferStatusNotification.php](C:/projects/Prady/lamu_acres/app/Notifications/OfferStatusNotification.php)
- [app/Notifications/OfferSubmittedNotification.php](C:/projects/Prady/lamu_acres/app/Notifications/OfferSubmittedNotification.php)
- [app/Notifications/PropertyInformationRequestedNotification.php](C:/projects/Prady/lamu_acres/app/Notifications/PropertyInformationRequestedNotification.php)
- [app/Notifications/ResearchPublishedNotification.php](C:/projects/Prady/lamu_acres/app/Notifications/ResearchPublishedNotification.php)
- [app/Notifications/SaleCompletedNotification.php](C:/projects/Prady/lamu_acres/app/Notifications/SaleCompletedNotification.php)
- [app/Notifications/SiteVisitUpdatedNotification.php](C:/projects/Prady/lamu_acres/app/Notifications/SiteVisitUpdatedNotification.php)
- [app/Notifications/SourcingUpdatedNotification.php](C:/projects/Prady/lamu_acres/app/Notifications/SourcingUpdatedNotification.php)
- [app/Notifications/ViewingAcknowledgedNotification.php](C:/projects/Prady/lamu_acres/app/Notifications/ViewingAcknowledgedNotification.php)
- [app/Notifications/WorkAssignedNotification.php](C:/projects/Prady/lamu_acres/app/Notifications/WorkAssignedNotification.php)
- [app/Providers/AppServiceProvider.php](C:/projects/Prady/lamu_acres/app/Providers/AppServiceProvider.php)
- [app/Services/Access/CapabilityReviewer.php](C:/projects/Prady/lamu_acres/app/Services/Access/CapabilityReviewer.php)
- [app/Services/Broker/PropertySubmission.php](C:/projects/Prady/lamu_acres/app/Services/Broker/PropertySubmission.php)
- [app/Services/Broker/ReferralRecorder.php](C:/projects/Prady/lamu_acres/app/Services/Broker/ReferralRecorder.php)
- [app/Services/Buyer/InterestWorkflow.php](C:/projects/Prady/lamu_acres/app/Services/Buyer/InterestWorkflow.php)
- [app/Services/Communications/MessageDesk.php](C:/projects/Prady/lamu_acres/app/Services/Communications/MessageDesk.php)
- [app/Services/Communications/NotificationInbox.php](C:/projects/Prady/lamu_acres/app/Services/Communications/NotificationInbox.php)
- [app/Services/Intelligence/PublicationDesk.php](C:/projects/Prady/lamu_acres/app/Services/Intelligence/PublicationDesk.php)
- [app/Services/Research/CommitmentDesk.php](C:/projects/Prady/lamu_acres/app/Services/Research/CommitmentDesk.php)
- [app/Services/Sales/ClosingDesk.php](C:/projects/Prady/lamu_acres/app/Services/Sales/ClosingDesk.php)
- [app/Services/Sales/SalesPipeline.php](C:/projects/Prady/lamu_acres/app/Services/Sales/SalesPipeline.php)
- [app/Services/Staff/EnquiryDesk.php](C:/projects/Prady/lamu_acres/app/Services/Staff/EnquiryDesk.php)
- [app/Services/Staff/SourcingDesk.php](C:/projects/Prady/lamu_acres/app/Services/Staff/SourcingDesk.php)
- [app/Services/Subscription/Memberships.php](C:/projects/Prady/lamu_acres/app/Services/Subscription/Memberships.php)
- [app/Services/Verification/VerificationOperations.php](C:/projects/Prady/lamu_acres/app/Services/Verification/VerificationOperations.php)
- [resources/views/buyer/offers/index.blade.php](C:/projects/Prady/lamu_acres/resources/views/buyer/offers/index.blade.php)
- [resources/views/components/notification-centre.blade.php](C:/projects/Prady/lamu_acres/resources/views/components/notification-centre.blade.php)
- [resources/views/staff/documents/index.blade.php](C:/projects/Prady/lamu_acres/resources/views/staff/documents/index.blade.php)
- [resources/views/staff/offers/index.blade.php](C:/projects/Prady/lamu_acres/resources/views/staff/offers/index.blade.php)
- [resources/views/staff/visits/_card.blade.php](C:/projects/Prady/lamu_acres/resources/views/staff/visits/_card.blade.php)
- [resources/views/workspace/notifications/index.blade.php](C:/projects/Prady/lamu_acres/resources/views/workspace/notifications/index.blade.php)
- [routes/admin.php](C:/projects/Prady/lamu_acres/routes/admin.php)
- [routes/broker.php](C:/projects/Prady/lamu_acres/routes/broker.php)
- [routes/buyer.php](C:/projects/Prady/lamu_acres/routes/buyer.php)
- [routes/investor.php](C:/projects/Prady/lamu_acres/routes/investor.php)
- [routes/seller.php](C:/projects/Prady/lamu_acres/routes/seller.php)
- [routes/staff.php](C:/projects/Prady/lamu_acres/routes/staff.php)
- [tests/Feature/Account/PartnerVerificationTest.php](C:/projects/Prady/lamu_acres/tests/Feature/Account/PartnerVerificationTest.php)
- [tests/Feature/Communications/CommunicationsTest.php](C:/projects/Prady/lamu_acres/tests/Feature/Communications/CommunicationsTest.php)
- [tests/Feature/UnifiedNotificationsTest.php](C:/projects/Prady/lamu_acres/tests/Feature/UnifiedNotificationsTest.php)

## C. Event coverage matrix

“Existing” means delivery already existed before this task; the last column distinguishes the upgrades.

| Event | Trigger | Recipient | Destination | Implemented |
|---|---|---|---|---|
| Partner application submitted/resubmitted | CapabilityReviewer submission | Authorized brokerage review staff, excluding applicant | Staff application review record | New alert |
| Seller/broker application decision or request for information | Existing ApplicationDecided event | Applicant | Partner application status, or capability-specific correction form | Existing; unified, deduplicated and private text |
| Broker approved/renewed | Actual approved decision | Applicant broker | Application status | Existing; one typed inbox alert per actual decision |
| Seller/broker listing submitted or information supplied | Existing submission/response services | Property review department | Staff listing detail | Existing; repeated identical replies suppressed |
| Listing published | Shared inventory status event | Authorized listing submitter | Seller property or broker listing detail | Existing; centralized for publication entry points |
| Listing returned for correction | Review to draft transition | Submitter | Existing listing correction page | New alert; current draft state drives attention |
| Staff listing question | Existing information request | Submitter | Existing listing detail with protected question | Existing; current unanswered request drives attention |
| Buyer enquiry submitted | EnquirySubmitted | Authorized assignee, otherwise buyer department | Staff enquiry detail | Existing; correct assignment fallback and one inbox entry |
| Enquiry assignment | EnquiryDesk assignment | Assigned staff | Staff enquiry detail | Existing; identical reassignment suppressed |
| Staff enquiry response | Existing EnquiryResponded listener | Enquiry buyer | Buyer enquiry detail | Existing, retained |
| Sourcing assignment | SourcingDesk assignment | Assigned staff | Staff sourcing detail | Existing; no duplicate unchanged assignment |
| Sourcing status changed | SourcingDesk status update | Requesting buyer | Buyer sourcing detail | New alert; unchanged status suppressed |
| Shortlist match approved | InterestWorkflow approval | Requesting buyer | Buyer match detail | Existing; duplicate direct/MessageDesk delivery removed |
| Document access requested | InterestWorkflow request | Buyer review department | Staff document review filtered to request | Existing; corrected destination and pending-state attention |
| Document access approved/declined | Existing document decision | Requesting buyer | Related buyer match/documents page | Existing; isolated recipient and resolved-state filtering |
| Viewing requested/assigned/updated | Existing viewing workflow | Responsible staff/assignee or buyer according to actor | Staff selected visit or buyer match detail | Existing; correct workspace and deduplicated confirmation |
| Viewing acknowledged | Existing acknowledgment | Assignee or buyer department | Staff selected visit | Existing; exact record selection added |
| Offer submitted/status changed | Existing offer workflow | Sales department or requesting buyer as applicable | Existing offer page filtered to offer | Existing; typed deduplication and recipient-appropriate destination |
| Sales assignment | Initial assignment to another staff member or reassignment | Authorized assigned sales staff | Staff sales detail | New alert; unchanged assignment suppressed |
| Sale stage progressed | Existing valid stage transition | Transaction buyer | Related buyer enquiry, or existing matches page if no enquiry detail exists | New progress alert |
| Closing completed | Existing closing completion workflow | Transaction buyer, linked owners and participating brokers, deduplicated by user | Authorized related enquiry, seller listing, broker deal/agreement or closest existing workspace page | Existing; one inbox alert and recipient-appropriate navigation |
| Commission payable/paid | Existing finance/closing workflow | Eligible agreement broker | Broker agreement detail | Existing; paid alert retains existing notification class |
| Referral submitted/decided | Existing referral service | Brokerage department or submitting broker | Staff/broker referral detail | Existing; accepted duplicate and unchanged decisions suppressed |
| Verification assignment | VerificationOperations assignment | Authorized assignee | Staff verification record | New alert; critical outstanding checks drive attention |
| Reverification due | Existing reminder workflow | Verification department | Staff verification record | Existing; current expiry/review state drives attention |
| Development published | Existing subscribed watch delivery | Relevant active Investor Pass watcher | Investor development detail | Existing; repeated publication delivery suppressed |
| Report/research released | Existing publication/commitment workflow | Eligible active subscribers | Investor report detail or existing archive for other research | Existing; shared idempotency and corrected destination |
| Subscription renewed/expired | Existing membership workflow | Subscription holder | Buyer subscription page | Existing; useful destination replaces home |
| Account registered/email confirmation requested | Existing registration and account notifications | Account holder | Existing account/workspace page | Existing, retained; no signed verification token in inbox |

No separate general messaging or listing-rejection engine was invented. Existing record replies, requests and actual review-to-draft transitions provide those communications within the application's supported workflows.

## D. User experience

The same top-bar bell works in all six workspaces. Its badge reflects the user's whole inbox and updates with existing server/Turbo navigation and read actions. There is no new polling or push infrastructure. Opening an alert takes the user to an authorized destination workspace, whose existing middleware sets the workspace session.

Unread marks whether an update has been opened. Needs attention is derived independently from current business records, permissions and assignments. An alert can be read and still require action; completed, reassigned or inaccessible work no longer counts as pending. Events without reliable current-state metadata remain ordinary updates.

## E. Verification

Final combined regression run: **93 tests passed, 962 assertions**, in 58.630 seconds. No failures remain in the listed regression set. The dedicated inbox suite accounts for 31 cases, including its role and unsafe-URL data sets.

The combined regression command is:

```powershell
php artisan test --compact tests/Feature/UnifiedNotificationsTest.php tests/Feature/Communications/CommunicationsTest.php tests/Feature/Buyer/BuyerInterestTest.php tests/Feature/Buyer/BuyerEnquiryTest.php tests/Feature/Buyer/SourcingRequestTest.php tests/Feature/Account/PartnerVerificationTest.php tests/Feature/Sales/ClosingSettlementTest.php tests/Feature/Sales/SalesPipelineTest.php tests/Feature/Verification/VerificationOperationsTest.php tests/Feature/Broker/BrokerReferralTest.php tests/Feature/Broker/BrokerListingTest.php tests/Feature/Seller/SellerListingTest.php tests/Feature/Subscription/PublicationCommitmentTest.php tests/Feature/Intelligence/DevelopmentPublicationTest.php tests/Feature/Intelligence/InvestorPassTest.php
```

The dedicated inbox suite covers six workspace headers; zero/capped unread counts; seven recent records; pagination; escaped output; owned read/open; other-user denial; unsafe/deleted/inaccessible destinations; safe query handling; cross-workspace permissions; scoped mark-all; resolved attention; department/assignee selection; documents; offers; sourcing; application review; seller publication/correction; verification assignment; sales assignment/progress; job retry deduplication; and transaction rollback.

Existing communications fixtures were updated to supply the identity, professional and ownership evidence now required by the existing approval rules. The email-delivery test now specifically asserts that no verification email is sent, while allowing the newly required staff submission alert. The SMS test checks that business work returns successfully, failed delivery remains recorded and the worker job can throw for retry. No approval guard was weakened.

Other checks: `vendor/bin/pint --dirty --format agent`, `git diff --check`, notification route inspection and listing-listener event discovery. Tests use isolated SQLite in memory; no production migration was executed.

Not verified: a live mobile/keyboard browser session, actual email/SMS inbox delivery, production queue-worker operation, and unrelated tests outside the listed regression set. Responsive constraints and accessibility markup are implemented and server-rendered, but this is not a claim of real-browser interaction testing.

## F. Remaining operational limitations

- Real email/SMS delivery still depends on the existing mail transport, OTP/SMS provider and queue-worker configuration. No external delivery provider was installed or configured.
- Some sales have no buyer enquiry or independently viewable seller transaction page. Alerts use the closest existing authorized matches/listings/agreements page; no unrelated transaction CRUD was added.
- Historical generic notifications without record IDs cannot reliably be classified as outstanding work. They remain readable updates; invalid or inaccessible historical URLs fail gracefully. Existing duplicates were not destructively deleted.
- Counts refresh through normal portal navigation/read actions; a page left idle does not receive live push updates.

Commit recommendation: review the complete branch/worktree diff against main, including the current staged and unstaged changes, then use `feat: unify portal notifications and action navigation`. This agent did not stage, commit or deploy the work. Existing staging and any commits made during the session were preserved.
