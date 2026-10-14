<?php

return [

    'buyer' => [
        'eyebrow' => 'Buyer',
        'heading' => 'Dashboard',
        'intro' => 'Enquiries, site visits, and offers will appear here once you start a search.',
        'activity' => [
            'columns' => ['Activity', 'Detail'],
            'empty' => 'Saved properties, enquiries, visits, and offers will be listed here when those modules are switched on.',
        ],
        'groups' => [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'buyer.dashboard'],
                ],
            ],
            [
                'key' => 'properties',
                'label' => 'Properties',
                'items' => [
                    ['label' => 'Saved Properties', 'route' => 'buyer.saved.index', 'active' => 'buyer.saved.*'],
                    ['label' => 'Find Land for Me', 'route' => 'buyer.sourcing.index', 'active' => 'buyer.sourcing.*'],
                    ['label' => 'Matches', 'route' => 'buyer.matches.index', 'active' => 'buyer.matches.*'],
                ],
            ],
            [
                'key' => 'enquiries',
                'label' => 'Enquiries',
                'items' => [
                    ['label' => 'Enquiries', 'route' => 'buyer.enquiries.index', 'active' => 'buyer.enquiries.*'],
                    ['label' => 'Site Visits', 'route' => 'buyer.visits.index', 'active' => 'buyer.visits.*'],
                    ['label' => 'Offers', 'route' => 'buyer.offers.index', 'active' => 'buyer.offers.*'],
                ],
            ],
            [
                'key' => 'records',
                'label' => 'Records',
                'items' => [
                    ['label' => 'Buying profile', 'route' => 'buyer.buying-profile', 'frame' => 'modal'],
                    ['label' => 'Documents'],
                    ['label' => 'Investor Pass', 'route' => 'buyer.subscription.show', 'active' => 'buyer.subscription.*'],
                    ['label' => 'Notifications'],
                ],
            ],
        ],
        'account' => [
            ['label' => 'Profile', 'route' => 'buyer.profile'],
            ['label' => 'Partner application', 'route' => 'account.applications.index'],
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'My Active Searches', 'description' => 'Searches you are running.', 'route' => 'buyer.sourcing.index'],
            ['title' => 'Recommended Properties', 'description' => 'Land suggested from a saved search.'],
            ['title' => 'Saved Properties', 'description' => 'Land you have kept to review.', 'route' => 'buyer.saved.index'],
            ['title' => 'My Enquiries', 'description' => 'Questions you have sent.', 'route' => 'buyer.enquiries.index'],
            ['title' => 'Upcoming Site Visits', 'description' => 'Visits that have been arranged.', 'route' => 'buyer.visits.index'],
            ['title' => 'Offers in Progress', 'description' => 'Offers that are still open.', 'route' => 'buyer.offers.index'],
            ['title' => 'Action Required', 'description' => 'Steps waiting on you.'],
        ],
    ],

    'broker' => [
        'eyebrow' => 'Broker Partner',
        'heading' => 'Dashboard',
        'intro' => 'Listings, referrals, and commission agreements will appear here as they are opened.',
        'activity' => [
            'columns' => ['Activity', 'Detail'],
            'empty' => 'Listings, referrals, deals, and agreements will be listed here when those modules are switched on.',
        ],
        'groups' => [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'broker.dashboard'],
                ],
            ],
            [
                'key' => 'listings',
                'label' => 'Listings',
                'items' => [
                    ['label' => 'My Listings', 'route' => 'broker.listings.index', 'active' => 'broker.listings.*'],
                    ['label' => 'Submit Property', 'route' => 'broker.listings.create'],
                ],
            ],
            [
                'key' => 'referrals',
                'label' => 'Referrals',
                'items' => [
                    ['label' => 'Register Buyer', 'route' => 'broker.referrals.create'],
                    ['label' => 'Referrals', 'route' => 'broker.referrals.index', 'active' => 'broker.referrals.*'],
                    ['label' => 'Buyer Requirements'],
                ],
            ],
            [
                'key' => 'deals',
                'label' => 'Deals',
                'items' => [
                    ['label' => 'Deals'],
                    ['label' => 'Agreements'],
                    ['label' => 'Earnings'],
                    ['label' => 'Investor Pass', 'route' => 'broker.subscription.show', 'active' => 'broker.subscription.*'],
                    ['label' => 'Notifications', 'route' => 'broker.notifications.index', 'active' => 'broker.notifications.*'],
                ],
            ],
        ],
        'account' => [
            ['label' => 'Partner application', 'route' => 'account.applications.index'],
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'My Property Listings', 'description' => 'Land you have submitted.', 'route' => 'broker.listings.index'],
            ['title' => 'Buyer Referrals', 'description' => 'Buyers you have introduced.', 'route' => 'broker.referrals.index'],
            ['title' => 'Deals in Progress', 'description' => 'Introductions that are still open.'],
            ['title' => 'Commission Agreements', 'description' => 'Agreements tied to your introductions.'],
        ],
    ],

    'seller' => [
        'eyebrow' => 'Landowner',
        'heading' => 'Dashboard',
        'intro' => 'Your properties, documents, and sale instructions will appear here as they are recorded.',
        'activity' => [
            'columns' => ['Activity', 'Detail'],
            'empty' => 'Properties, documents, enquiries, and offers will be listed here when those modules are switched on.',
        ],
        'groups' => [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'seller.dashboard'],
                ],
            ],
            [
                'key' => 'properties',
                'label' => 'Properties',
                'items' => [
                    ['label' => 'Seller profile', 'route' => 'seller.holding.edit', 'active' => 'seller.holding.*'],
                    ['label' => 'My Properties', 'route' => 'seller.properties.index', 'active' => 'seller.properties.*'],
                    ['label' => 'List Property', 'route' => 'seller.properties.create', 'active' => 'seller.properties.create'],
                    ['label' => 'Documents', 'route' => 'seller.properties.index'],
                    ['label' => 'Verification', 'route' => 'seller.properties.index'],
                ],
            ],
            [
                'key' => 'sales',
                'label' => 'Sales',
                'items' => [
                    ['label' => 'Enquiries', 'route' => 'seller.enquiries.index', 'active' => 'seller.enquiries.*'],
                    ['label' => 'Offers', 'route' => 'seller.offers.index', 'active' => 'seller.offers.*'],
                    ['label' => 'Transactions', 'route' => 'seller.transactions.index', 'active' => 'seller.transactions.*'],
                    ['label' => 'Statements', 'route' => 'seller.transactions.index'],
                ],
            ],
        ],
        'account' => [
            ['label' => 'Partner application', 'route' => 'account.applications.index'],
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'My Properties', 'description' => 'Land recorded against this account.', 'route' => 'seller.properties.index'],
            ['title' => 'Verification Status', 'description' => 'Document checks in progress.', 'route' => 'seller.properties.index'],
            ['title' => 'Buyer Enquiries', 'description' => 'Questions about your land.'],
            ['title' => 'Viewings & Offers', 'description' => 'Visits and offers on your land.'],
        ],
    ],

    'investor' => [
        'eyebrow' => 'Investor',
        'heading' => 'Intelligence Dashboard',
        'intro' => 'Premium research opens only after a verified subscription payment. An Investor Pass does not release private property records.',
        'activity' => [
            'columns' => ['Activity', 'Detail'],
            'empty' => 'Research, alerts, and subscription activity will be listed here when a verified subscription is on file. Nothing has been published to this account.',
        ],
        'groups' => [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'items' => [
                    ['label' => 'Intelligence Dashboard', 'route' => 'investor.dashboard'],
                ],
            ],
            [
                'key' => 'intelligence',
                'label' => 'Intelligence',
                'items' => [
                    ['label' => 'Development Tracker', 'route' => 'investor.projects.index', 'active' => 'investor.projects.*'],
                    ['label' => 'Satellite Explorer', 'route' => 'investor.imagery.index', 'active' => 'investor.imagery.*'],
                    ['label' => 'Planning', 'route' => 'investor.planning.index', 'active' => 'investor.planning.*'],
                    ['label' => 'Land Prices', 'route' => 'investor.prices', 'active' => 'investor.prices'],
                    ['label' => 'Research', 'route' => 'investor.reports.index', 'active' => 'investor.reports.*'],
                ],
            ],
            [
                'key' => 'watchlists',
                'label' => 'Watchlists',
                'items' => [
                    ['label' => 'Watchlists', 'route' => 'investor.watchlists.index', 'active' => 'investor.watchlists.*'],
                    ['label' => 'Alerts', 'route' => 'investor.archive.index', 'active' => 'investor.archive.*'],
                ],
            ],
            [
                'key' => 'subscription',
                'label' => 'Subscription',
                'items' => [
                    ['label' => 'Subscription', 'route' => 'investor.subscription.show', 'active' => 'investor.subscription.*'],
                ],
            ],
        ],
        'account' => [
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'Development Tracker', 'description' => 'Published infrastructure projects.', 'route' => 'investor.projects.index'],
            ['title' => 'Satellite Comparison', 'description' => 'Published imagery comparisons.', 'route' => 'investor.imagery.index'],
            ['title' => 'Land Market Trends', 'description' => 'Asking prices from published listings.', 'route' => 'investor.prices'],
            ['title' => 'Research', 'description' => 'Published report summaries.', 'route' => 'investor.reports.index'],
            ['title' => 'Watched Locations', 'description' => 'Places you have asked to follow.'],
            ['title' => 'Report Publication Calendar', 'description' => 'Reports scheduled for release.'],
            ['title' => 'Subscription Status', 'description' => 'Whether a verified payment is on file.'],
        ],
    ],

    'staff' => [
        'eyebrow' => 'Lamu Acres Operations',
        'heading' => 'My Dashboard',
        'intro' => 'Nothing is waiting on you yet.',
        'activity' => [
            'columns' => ['Activity', 'Detail'],
            'empty' => 'Assigned work will be listed here when a desk places it on you.',
        ],
        'groups' => [
            [
                'key' => 'overview',
                'label' => 'Overview',
                'permission' => 'staff.overview',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'staff.dashboard'],
                    ['label' => 'Tasks'],
                    ['label' => 'Calendar & Follow-ups'],
                ],
            ],
            [
                'key' => 'properties',
                'label' => 'Properties',
                'permission' => 'staff.properties',
                'items' => [
                    ['label' => 'Property Register', 'route' => 'staff.properties.index', 'active' => 'staff.properties.*'],
                    ['label' => 'Pending Listings', 'route' => 'staff.listings.index', 'active' => 'staff.listings.*'],
                    ['label' => 'Availability & Locations'],
                ],
            ],
            [
                'key' => 'buyers',
                'label' => 'Buyers',
                'permission' => 'staff.buyers',
                'items' => [
                    ['label' => 'Buyer Directory'],
                    ['label' => 'Enquiries & Leads', 'route' => 'staff.enquiries.index', 'active' => 'staff.enquiries.*'],
                    ['label' => 'Site Visit Calendar', 'route' => 'staff.visits.index', 'active' => 'staff.visits.*'],
                    ['label' => 'Document access', 'route' => 'staff.documents.index', 'active' => 'staff.documents.*'],
                ],
            ],
            [
                'key' => 'sourcing',
                'label' => 'Sourcing',
                'permission' => 'staff.sourcing',
                'items' => [
                    ['label' => 'Buyer Requirements', 'route' => 'staff.sourcing.index', 'active' => 'staff.sourcing.*'],
                    ['label' => 'Matching Desk', 'route' => 'staff.sourcing.index', 'active' => 'staff.sourcing.*'],
                    ['label' => 'Broker Sourcing'],
                ],
            ],
            [
                'key' => 'brokerage',
                'label' => 'Brokers & Sellers',
                'permission' => 'staff.brokerage',
                'items' => [
                    ['label' => 'Partner applications', 'route' => 'staff.applications.index', 'active' => 'staff.applications.*'],
                    ['label' => 'Brokers'],
                    ['label' => 'Sellers'],
                    ['label' => 'Buyer Referrals', 'route' => 'staff.referrals.index', 'active' => 'staff.referrals.*'],
                    ['label' => 'Commission Agreements', 'route' => 'staff.commissions.index', 'active' => 'staff.commissions.*'],
                ],
            ],
            [
                'key' => 'sales',
                'label' => 'Sales',
                'permission' => 'staff.sales',
                'items' => [
                    ['label' => 'Sales Pipeline', 'route' => 'staff.sales.index', 'active' => 'staff.sales.index'],
                    ['label' => 'Offers & Negotiations', 'route' => 'staff.offers.index', 'active' => 'staff.offers.*'],
                    ['label' => 'Completion Queue', 'route' => 'staff.sales.completion', 'active' => 'staff.sales.completion'],
                ],
            ],
            [
                'key' => 'verification',
                'label' => 'Verification',
                'permission' => 'staff.verification',
                'items' => [
                    ['label' => 'Verification Queue', 'route' => 'staff.verification.index', 'active' => 'staff.verification.index'],
                    ['label' => 'New Reviews', 'route' => 'staff.verification.new', 'active' => 'staff.verification.new'],
                    ['label' => 'Document Reviews', 'route' => 'staff.verification.missing', 'active' => 'staff.verification.missing'],
                    ['label' => 'Exceptions & Rechecks', 'route' => 'staff.verification.exceptions', 'active' => 'staff.verification.exceptions'],
                    ['label' => 'Expiring Checks', 'route' => 'staff.verification.expiring', 'active' => 'staff.verification.expiring'],
                    ['label' => 'Completed Reviews', 'route' => 'staff.verification.completed', 'active' => 'staff.verification.completed'],
                ],
            ],
            [
                'key' => 'intelligence',
                'label' => 'Intelligence',
                'permission' => 'staff.intelligence',
                'items' => [
                    ['label' => 'Development Tracker', 'route' => 'staff.projects.index', 'active' => 'staff.projects.*'],
                    ['label' => 'Research Desk', 'route' => 'staff.reports.index', 'active' => 'staff.reports.*'],
                    ['label' => 'Publication calendar', 'route' => 'staff.commitments.index', 'active' => 'staff.commitments.*'],
                    ['label' => 'Satellite Updates', 'route' => 'staff.imagery.index', 'active' => 'staff.imagery.*'],
                    ['label' => 'Planning', 'route' => 'staff.planning.index', 'active' => 'staff.planning.*'],
                    ['label' => 'Price Observatory', 'route' => 'staff.prices.index', 'active' => 'staff.prices.*'],
                ],
            ],
            [
                'key' => 'finance',
                'label' => 'Finance',
                'permission' => 'staff.finance',
                'items' => [
                    ['label' => 'Subscriptions', 'route' => 'staff.memberships.index', 'active' => 'staff.memberships.*'],
                    ['label' => 'Commission Payables', 'route' => 'staff.settlements.index', 'active' => 'staff.settlements.*'],
                    ['label' => 'Payments & Reconciliation', 'route' => 'staff.settlements.index', 'active' => 'staff.settlements.*'],
                ],
            ],
            [
                'key' => 'communications',
                'label' => 'Communications',
                'permission' => 'staff.communications',
                'items' => [
                    ['label' => 'Notifications', 'route' => 'staff.notifications.index', 'active' => 'staff.notifications.*'],
                    ['label' => 'Templates'],
                    ['label' => 'Delivery History'],
                ],
            ],
        ],
        'account' => [
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'Assigned work', 'description' => 'Tasks placed on your desk.', 'permission' => 'staff.overview'],
            ['title' => 'Property register', 'description' => 'Land waiting for a listing decision.', 'permission' => 'staff.properties', 'route' => 'staff.properties.index'],
            ['title' => 'Buyer enquiries', 'description' => 'Callbacks, visits, and offers.', 'permission' => 'staff.buyers', 'route' => 'staff.enquiries.index'],
            ['title' => 'Sourcing queue', 'description' => 'Buyer requirements that are not matched yet.', 'permission' => 'staff.sourcing', 'route' => 'staff.sourcing.index'],
            ['title' => 'Broker introductions', 'description' => 'Applications, referrals, and agreements.', 'permission' => 'staff.brokerage', 'route' => 'staff.applications.index'],
            ['title' => 'Sales pipeline', 'description' => 'Offers through to completion.', 'permission' => 'staff.sales', 'route' => 'staff.sales.index'],
            ['title' => 'Verification queue', 'description' => 'Documents and rechecks.', 'permission' => 'staff.verification', 'route' => 'staff.verification.index'],
            ['title' => 'Research desk', 'description' => 'Drafts and the publication calendar.', 'permission' => 'staff.intelligence'],
            ['title' => 'Finance queue', 'description' => 'Subscriptions and commission settlements.', 'permission' => 'staff.finance', 'route' => 'staff.memberships.index'],
            ['title' => 'Communications', 'description' => 'Messages waiting to be sent.', 'permission' => 'staff.communications'],
        ],
    ],

    'admin' => [
        'eyebrow' => 'Administration',
        'heading' => 'Executive Dashboard',
        'intro' => 'Administration tools open as each control is switched on.',
        'activity' => [
            'columns' => ['Activity', 'Detail'],
            'empty' => 'Approvals and reports will be listed here when those controls are switched on.',
        ],
        'groups' => [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'items' => [
                    ['label' => 'Executive Dashboard', 'route' => 'admin.dashboard'],
                ],
            ],
            [
                'key' => 'oversight',
                'label' => 'Business Oversight',
                'permission' => 'admin.oversight',
                'items' => [
                    ['label' => 'Sales Performance'],
                    ['label' => 'Inventory Performance'],
                    ['label' => 'Buyer Demand'],
                    ['label' => 'Broker Performance'],
                    ['label' => 'Subscription Performance'],
                ],
            ],
            [
                'key' => 'approvals',
                'label' => 'Approvals',
                'permission' => 'admin.approvals',
                'items' => [
                    ['label' => 'Listing Approvals'],
                    ['label' => 'Commission Authorizations'],
                    ['label' => 'Verification Exceptions'],
                    ['label' => 'Publication Approvals'],
                ],
            ],
            [
                'key' => 'users',
                'label' => 'Users',
                'permission' => 'admin.users',
                'items' => [
                    ['label' => 'User Directory', 'route' => 'admin.users.index', 'active' => 'admin.users.*'],
                    ['label' => 'Roles'],
                    ['label' => 'Access History'],
                ],
            ],
            [
                'key' => 'geography',
                'label' => 'Geography',
                'permission' => 'admin.geography',
                'items' => [
                    ['label' => 'Counties & Localities', 'route' => 'admin.geography.index', 'active' => 'admin.geography.*'],
                    ['label' => 'Map Layers'],
                    ['label' => 'Infrastructure Reference Data'],
                ],
            ],
            [
                'key' => 'commercial',
                'label' => 'Commercial Settings',
                'permission' => 'admin.commercial',
                'items' => [
                    ['label' => 'Commission Rules'],
                    ['label' => 'Agreement Templates'],
                    ['label' => 'Subscription Plans', 'route' => 'admin.plans.edit', 'active' => 'admin.plans.*'],
                    ['label' => 'Verification Checklists'],
                ],
            ],
            [
                'key' => 'settings',
                'label' => 'System Operations',
                'permission' => 'admin.settings',
                'items' => [
                    ['label' => 'Integrations'],
                    ['label' => 'Audit'],
                ],
            ],
            [
                'key' => 'reports',
                'label' => 'Reports',
                'permission' => 'admin.reports',
                'items' => [
                    ['label' => 'Sales Reports'],
                    ['label' => 'Commission Reports'],
                    ['label' => 'Financial Reports'],
                    ['label' => 'Audit Reports'],
                ],
            ],
        ],
        'account' => [
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'Sales performance', 'description' => 'Completed and open sales.', 'permission' => 'admin.oversight'],
            ['title' => 'Approvals', 'description' => 'Decisions waiting for a controller.', 'permission' => 'admin.approvals'],
            ['title' => 'User access', 'description' => 'Review who can open each workspace.', 'permission' => 'admin.users', 'route' => 'admin.users.index'],
            ['title' => 'Geography', 'description' => 'Counties, localities, and map layers.', 'permission' => 'admin.geography', 'route' => 'admin.geography.index'],
            ['title' => 'Commercial settings', 'description' => 'Commission rules and subscription plans.', 'permission' => 'admin.commercial', 'route' => 'admin.plans.edit'],
            ['title' => 'System operations', 'description' => 'Integrations, queues, and activity.', 'permission' => 'admin.settings'],
            ['title' => 'Reports', 'description' => 'Sales, commission, and audit reports.', 'permission' => 'admin.reports'],
        ],
    ],

];
