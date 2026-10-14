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
                    ['label' => 'Find Land for Me'],
                    ['label' => 'Matches'],
                ],
            ],
            [
                'key' => 'enquiries',
                'label' => 'Enquiries',
                'items' => [
                    ['label' => 'Enquiries', 'route' => 'buyer.enquiries.index', 'active' => 'buyer.enquiries.*'],
                    ['label' => 'Site Visits'],
                    ['label' => 'Offers'],
                ],
            ],
            [
                'key' => 'records',
                'label' => 'Records',
                'items' => [
                    ['label' => 'Buying profile', 'route' => 'buyer.buying-profile', 'frame' => 'modal'],
                    ['label' => 'Documents'],
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
            ['title' => 'My Active Searches', 'description' => 'Searches you are running.'],
            ['title' => 'Recommended Properties', 'description' => 'Land suggested from a saved search.'],
            ['title' => 'Saved Properties', 'description' => 'Land you have kept to review.', 'route' => 'buyer.saved.index'],
            ['title' => 'My Enquiries', 'description' => 'Questions you have sent.', 'route' => 'buyer.enquiries.index'],
            ['title' => 'Upcoming Site Visits', 'description' => 'Visits that have been arranged.'],
            ['title' => 'Offers in Progress', 'description' => 'Offers that are still open.'],
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
                    ['label' => 'My Listings'],
                    ['label' => 'Submit Property'],
                ],
            ],
            [
                'key' => 'referrals',
                'label' => 'Referrals',
                'items' => [
                    ['label' => 'Register Buyer'],
                    ['label' => 'Referrals'],
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
                    ['label' => 'Notifications'],
                ],
            ],
        ],
        'account' => [
            ['label' => 'Profile', 'route' => 'broker.profile'],
            ['label' => 'Partner application', 'route' => 'account.applications.index'],
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'My Property Listings', 'description' => 'Land you have submitted.'],
            ['title' => 'Buyer Referrals', 'description' => 'Buyers you have introduced.'],
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
                    ['label' => 'My Properties'],
                    ['label' => 'List Property'],
                    ['label' => 'Documents'],
                    ['label' => 'Verification'],
                ],
            ],
            [
                'key' => 'sales',
                'label' => 'Sales',
                'items' => [
                    ['label' => 'Enquiries'],
                    ['label' => 'Offers'],
                    ['label' => 'Transactions'],
                    ['label' => 'Statements'],
                ],
            ],
        ],
        'account' => [
            ['label' => 'Profile', 'route' => 'seller.profile'],
            ['label' => 'Partner application', 'route' => 'account.applications.index'],
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'My Properties', 'description' => 'Land recorded against this account.'],
            ['title' => 'Verification Status', 'description' => 'Document checks in progress.'],
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
                    ['label' => 'Development Tracker'],
                    ['label' => 'Satellite Explorer'],
                    ['label' => 'Planning'],
                    ['label' => 'Land Prices'],
                    ['label' => 'Research'],
                ],
            ],
            [
                'key' => 'watchlists',
                'label' => 'Watchlists',
                'items' => [
                    ['label' => 'Watchlists'],
                    ['label' => 'Alerts'],
                ],
            ],
            [
                'key' => 'subscription',
                'label' => 'Subscription',
                'items' => [
                    ['label' => 'Subscription'],
                ],
            ],
        ],
        'account' => [
            ['label' => 'Profile', 'route' => 'investor.profile'],
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'Latest Intelligence', 'description' => 'Research released to paying subscribers.'],
            ['title' => 'Dangote Development Tracker', 'description' => 'Published notes on the Dangote development.'],
            ['title' => 'LAPSSET Development', 'description' => 'Published notes on LAPSSET.'],
            ['title' => 'Airport Development Monitoring', 'description' => 'Published notes on the airport.'],
            ['title' => 'Satellite Comparison', 'description' => 'Aerial updates after they are published.'],
            ['title' => 'Land Market Trends', 'description' => 'Published price observations.'],
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
                    ['label' => 'Property Register'],
                    ['label' => 'Pending Listings'],
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
                    ['label' => 'Site Visit Calendar'],
                ],
            ],
            [
                'key' => 'sourcing',
                'label' => 'Sourcing',
                'permission' => 'staff.sourcing',
                'items' => [
                    ['label' => 'Buyer Requirements'],
                    ['label' => 'Matching Desk'],
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
                    ['label' => 'Buyer Referrals'],
                    ['label' => 'Commission Agreements'],
                ],
            ],
            [
                'key' => 'sales',
                'label' => 'Sales',
                'permission' => 'staff.sales',
                'items' => [
                    ['label' => 'Sales Pipeline'],
                    ['label' => 'Offers & Negotiations'],
                    ['label' => 'Completion Queue'],
                ],
            ],
            [
                'key' => 'verification',
                'label' => 'Verification',
                'permission' => 'staff.verification',
                'items' => [
                    ['label' => 'Verification Queue'],
                    ['label' => 'Document Reviews'],
                    ['label' => 'Exceptions & Rechecks'],
                ],
            ],
            [
                'key' => 'intelligence',
                'label' => 'Intelligence',
                'permission' => 'staff.intelligence',
                'items' => [
                    ['label' => 'Development Tracker'],
                    ['label' => 'Research Desk'],
                    ['label' => 'Satellite Updates'],
                    ['label' => 'Publishing Calendar'],
                ],
            ],
            [
                'key' => 'finance',
                'label' => 'Finance',
                'permission' => 'staff.finance',
                'items' => [
                    ['label' => 'Subscriptions'],
                    ['label' => 'Commission Payables'],
                    ['label' => 'Payments & Reconciliation'],
                ],
            ],
            [
                'key' => 'communications',
                'label' => 'Communications',
                'permission' => 'staff.communications',
                'items' => [
                    ['label' => 'Notifications'],
                    ['label' => 'Templates'],
                    ['label' => 'Delivery History'],
                ],
            ],
        ],
        'account' => [
            ['label' => 'Profile', 'route' => 'staff.profile'],
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'Assigned work', 'description' => 'Tasks placed on your desk.', 'permission' => 'staff.overview'],
            ['title' => 'Property register', 'description' => 'Land waiting for a listing decision.', 'permission' => 'staff.properties'],
            ['title' => 'Buyer enquiries', 'description' => 'Callbacks, visits, and offers.', 'permission' => 'staff.buyers', 'route' => 'staff.enquiries.index'],
            ['title' => 'Sourcing queue', 'description' => 'Buyer requirements that are not matched yet.', 'permission' => 'staff.sourcing'],
            ['title' => 'Broker introductions', 'description' => 'Applications, referrals, and agreements.', 'permission' => 'staff.brokerage', 'route' => 'staff.applications.index'],
            ['title' => 'Sales pipeline', 'description' => 'Offers through to completion.', 'permission' => 'staff.sales'],
            ['title' => 'Verification queue', 'description' => 'Documents and rechecks.', 'permission' => 'staff.verification'],
            ['title' => 'Research desk', 'description' => 'Drafts and the publication calendar.', 'permission' => 'staff.intelligence'],
            ['title' => 'Finance queue', 'description' => 'Subscriptions and commission settlements.', 'permission' => 'staff.finance'],
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
                    ['label' => 'Counties & Localities'],
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
                    ['label' => 'Subscription Plans'],
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
            ['label' => 'Profile', 'route' => 'admin.profile'],
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'Sales performance', 'description' => 'Completed and open sales.', 'permission' => 'admin.oversight'],
            ['title' => 'Approvals', 'description' => 'Decisions waiting for a controller.', 'permission' => 'admin.approvals'],
            ['title' => 'User access', 'description' => 'Review who can open each workspace.', 'permission' => 'admin.users', 'route' => 'admin.users.index'],
            ['title' => 'Geography', 'description' => 'Counties, localities, and map layers.', 'permission' => 'admin.geography'],
            ['title' => 'Commercial settings', 'description' => 'Commission rules and subscription plans.', 'permission' => 'admin.commercial'],
            ['title' => 'System operations', 'description' => 'Integrations, queues, and activity.', 'permission' => 'admin.settings'],
            ['title' => 'Reports', 'description' => 'Sales, commission, and audit reports.', 'permission' => 'admin.reports'],
        ],
    ],

];
