<?php

return [

    'buyer' => [
        'eyebrow' => 'Buyer',
        'heading' => 'Dashboard',
        'intro' => 'Start with a search or a published property. Each step shows what to do next.',
        'activity' => [
            'columns' => ['Activity', 'Detail'],
            'empty' => 'Your searches, saved land, enquiries, visits, and offers will be listed here.',
        ],
        'groups' => [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'buyer.dashboard', 'active' => 'buyer.dashboard'],
                ],
            ],
            [
                'key' => 'explore',
                'label' => 'Explore Land',
                'items' => [
                    ['label' => 'Explore Land', 'route' => 'land.index', 'active' => 'land.*'],
                ],
            ],
            [
                'key' => 'saved',
                'label' => 'Saved Properties',
                'items' => [
                    ['label' => 'Saved Properties', 'route' => 'buyer.saved.index', 'active' => 'buyer.saved.*'],
                ],
            ],
            [
                'key' => 'sourcing',
                'label' => 'Find Land for Me',
                'items' => [
                    ['label' => 'Find Land for Me', 'route' => 'buyer.sourcing.index', 'active' => 'buyer.sourcing.*'],
                ],
            ],
            [
                'key' => 'matches',
                'label' => 'My Property Matches',
                'items' => [
                    ['label' => 'My Property Matches', 'route' => 'buyer.matches.index', 'active' => 'buyer.matches.*'],
                ],
            ],
            [
                'key' => 'enquiries',
                'label' => 'My Enquiries',
                'items' => [
                    ['label' => 'My Enquiries', 'route' => 'buyer.enquiries.index', 'active' => 'buyer.enquiries.*'],
                ],
            ],
            [
                'key' => 'visits',
                'label' => 'Site Visits',
                'items' => [
                    ['label' => 'Site Visits', 'route' => 'buyer.visits.index', 'active' => 'buyer.visits.*'],
                ],
            ],
            [
                'key' => 'offers',
                'label' => 'My Offers',
                'items' => [
                    ['label' => 'My Offers', 'route' => 'buyer.offers.index', 'active' => 'buyer.offers.*'],
                ],
            ],
            [
                'key' => 'profile',
                'label' => 'Buying Profile',
                'items' => [
                    ['label' => 'Buying Profile', 'route' => 'buyer.buying-profile', 'active' => 'buyer.buying-profile'],
                ],
            ],
            [
                'key' => 'investor',
                'label' => 'Investor Pass',
                'items' => [
                    ['label' => 'Investor Pass', 'route' => 'buyer.subscription.show', 'active' => 'buyer.subscription.*'],
                ],
            ],
            [
                'key' => 'notifications',
                'label' => 'Notifications',
                'items' => [
                    ['label' => 'Notifications', 'route' => 'buyer.notifications.index', 'active' => 'buyer.notifications.*'],
                ],
            ],
        ],
        'account' => [
            ['label' => 'Account', 'route' => 'buyer.profile', 'active' => 'buyer.profile'],
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'Explore land', 'description' => 'Published properties you can open.', 'route' => 'land.index'],
            ['title' => 'Find land for me', 'description' => 'Tell us the area, size, and budget you want.', 'route' => 'buyer.sourcing.create'],
            ['title' => 'Saved properties', 'description' => 'Land you have kept to review.', 'route' => 'buyer.saved.index'],
            ['title' => 'My enquiries', 'description' => 'Questions you have sent.', 'route' => 'buyer.enquiries.index'],
            ['title' => 'Site visits', 'description' => 'Visits that have been arranged.', 'route' => 'buyer.visits.index'],
            ['title' => 'My offers', 'description' => 'Offers that are still open.', 'route' => 'buyer.offers.index'],
        ],
    ],

    'broker' => [
        'eyebrow' => 'Broker Partner',
        'heading' => 'Dashboard',
        'intro' => 'Submit land, register buyers, and follow the deals and commission records staff have opened for you.',
        'activity' => [
            'columns' => ['Activity', 'Detail'],
            'empty' => 'Listing and referral activity will be listed here after you submit one.',
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
                'key' => 'submit',
                'label' => 'Submit Property',
                'items' => [
                    ['label' => 'Submit Property', 'route' => 'broker.listings.create', 'active' => 'broker.listings.create'],
                ],
            ],
            [
                'key' => 'listings',
                'label' => 'My Listings',
                'items' => [
                    ['label' => 'My Listings', 'route' => 'broker.listings.index', 'active' => 'broker.listings.*'],
                ],
            ],
            [
                'key' => 'register',
                'label' => 'Register a Buyer',
                'items' => [
                    ['label' => 'Register a Buyer', 'route' => 'broker.referrals.create', 'active' => 'broker.referrals.create'],
                ],
            ],
            [
                'key' => 'referrals',
                'label' => 'My Referrals',
                'items' => [
                    ['label' => 'My Referrals', 'route' => 'broker.referrals.index', 'active' => 'broker.referrals.*'],
                ],
            ],
            [
                'key' => 'requirements',
                'label' => 'Buyer Requirements Board',
                'items' => [
                    ['label' => 'Buyer Requirements Board', 'route' => 'broker.requirements.index', 'active' => 'broker.requirements.*'],
                ],
            ],
            [
                'key' => 'deals',
                'label' => 'My Deals',
                'items' => [
                    ['label' => 'My Deals', 'route' => 'broker.deals.index', 'active' => 'broker.deals.*'],
                ],
            ],
            [
                'key' => 'agreements',
                'label' => 'Commission Agreements',
                'items' => [
                    ['label' => 'Commission Agreements', 'route' => 'broker.agreements.index', 'active' => 'broker.agreements.*'],
                ],
            ],
            [
                'key' => 'earnings',
                'label' => 'Earnings & Payments',
                'items' => [
                    ['label' => 'Earnings & Payments', 'route' => 'broker.earnings.index', 'active' => 'broker.earnings.*'],
                ],
            ],
            [
                'key' => 'subscription',
                'label' => 'Investor Pass',
                'items' => [
                    ['label' => 'Investor Pass', 'route' => 'broker.subscription.show', 'active' => 'broker.subscription.*'],
                ],
            ],
            [
                'key' => 'notifications',
                'label' => 'Notifications',
                'items' => [
                    ['label' => 'Notifications', 'route' => 'broker.notifications.index', 'active' => 'broker.notifications.*'],
                ],
            ],
        ],
        'account' => [
            ['label' => 'Profile', 'route' => 'broker.profile', 'active' => 'broker.profile'],
            ['label' => 'Partner application', 'route' => 'account.applications.index'],
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'My Property Listings', 'description' => 'Land you have submitted.', 'route' => 'broker.listings.index'],
            ['title' => 'Buyer Referrals', 'description' => 'Buyers you have introduced.', 'route' => 'broker.referrals.index'],
            ['title' => 'Deals in Progress', 'description' => 'Sales linked to an accepted introduction.', 'route' => 'broker.deals.index'],
            ['title' => 'Commission Agreements', 'description' => 'Terms staff have accepted for you.', 'route' => 'broker.agreements.index'],
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
                    ['label' => 'Documents', 'route' => 'seller.properties.index', 'fragment' => 'returned'],
                    ['label' => 'Verification', 'route' => 'seller.properties.index', 'fragment' => 'review'],
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
            [
                'key' => 'notifications',
                'label' => 'Notifications',
                'items' => [
                    ['label' => 'Notifications', 'route' => 'seller.notifications.index', 'active' => 'seller.notifications.*'],
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
                'label' => 'Intelligence Dashboard',
                'items' => [
                    ['label' => 'Intelligence Dashboard', 'route' => 'investor.dashboard', 'active' => 'investor.dashboard'],
                ],
            ],
            [
                'key' => 'tracker',
                'label' => 'Development Tracker',
                'items' => [
                    ['label' => 'Development Tracker', 'route' => 'investor.projects.index', 'active' => 'investor.projects.*'],
                ],
            ],
            [
                'key' => 'imagery',
                'label' => 'Satellite & Aerial Imagery',
                'items' => [
                    ['label' => 'Satellite & Aerial Imagery', 'route' => 'investor.imagery.index', 'active' => 'investor.imagery.*'],
                ],
            ],
            [
                'key' => 'planning',
                'label' => 'Planning & Zoning',
                'items' => [
                    ['label' => 'Planning & Zoning', 'route' => 'investor.planning.index', 'active' => 'investor.planning.*'],
                ],
            ],
            [
                'key' => 'prices',
                'label' => 'Land Price Observatory',
                'items' => [
                    ['label' => 'Land Price Observatory', 'route' => 'investor.prices', 'active' => 'investor.prices'],
                ],
            ],
            [
                'key' => 'library',
                'label' => 'Research Reports',
                'items' => [
                    ['label' => 'Research Reports', 'route' => 'investor.reports.index', 'active' => 'investor.reports.*'],
                ],
            ],
            [
                'key' => 'watchlists',
                'label' => 'My Watchlists',
                'items' => [
                    ['label' => 'My Watchlists', 'route' => 'investor.watchlists.index', 'active' => 'investor.watchlists.*'],
                ],
            ],
            [
                'key' => 'alerts',
                'label' => 'Alerts & Archive',
                'items' => [
                    ['label' => 'Alerts & Archive', 'route' => 'investor.archive.index', 'active' => 'investor.archive.*'],
                ],
            ],
            [
                'key' => 'subscription',
                'label' => 'Subscription',
                'items' => [
                    ['label' => 'Subscription', 'route' => 'investor.subscription.show', 'active' => 'investor.subscription.*'],
                ],
            ],
            [
                'key' => 'notifications',
                'label' => 'Notifications',
                'items' => [
                    ['label' => 'Notifications', 'route' => 'investor.notifications.index', 'active' => 'investor.notifications.*'],
                ],
            ],
        ],
        'account' => [
            ['label' => 'Profile', 'route' => 'investor.profile', 'active' => 'investor.profile'],
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'Development Tracker', 'description' => 'Published infrastructure projects.', 'route' => 'investor.projects.index'],
            ['title' => 'Satellite Comparison', 'description' => 'Published imagery comparisons.', 'route' => 'investor.imagery.index'],
            ['title' => 'Land Market Trends', 'description' => 'Asking prices from published listings.', 'route' => 'investor.prices'],
            ['title' => 'Research', 'description' => 'Published report summaries.', 'route' => 'investor.reports.index'],
            ['title' => 'Watched Locations', 'description' => 'Places you have asked to follow.', 'route' => 'investor.watchlists.index'],
            ['title' => 'Alerts and archive', 'description' => 'Published alerts delivered to this account.', 'route' => 'investor.archive.index'],
            ['title' => 'Subscription Status', 'description' => 'Whether a verified payment is on file.', 'route' => 'investor.subscription.show'],
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
                    ['label' => 'Dashboard', 'route' => 'staff.dashboard', 'active' => 'staff.dashboard'],
                    ['label' => 'Tasks', 'route' => 'staff.tasks', 'active' => 'staff.tasks'],
                    ['label' => 'Calendar', 'route' => 'staff.calendar', 'active' => 'staff.calendar'],
                ],
            ],
            [
                'key' => 'properties',
                'label' => 'Properties',
                'permission' => 'staff.properties',
                'items' => [
                    ['label' => 'Property Register', 'route' => 'staff.properties.index', 'active' => 'staff.properties.*'],
                    ['label' => 'Listing Reviews', 'route' => 'staff.listings.index', 'active' => 'staff.listings.*'],
                    ['label' => 'Property Media', 'route' => 'staff.media.index', 'active' => 'staff.media.*'],
                ],
            ],
            [
                'key' => 'buyers',
                'label' => 'Buyers & CRM',
                'permission' => 'staff.buyers',
                'items' => [
                    ['label' => 'Enquiries', 'route' => 'staff.enquiries.index', 'active' => 'staff.enquiries.*'],
                    ['label' => 'Viewings', 'route' => 'staff.visits.index', 'active' => 'staff.visits.*'],
                    ['label' => 'Document Requests', 'route' => 'staff.documents.index', 'active' => 'staff.documents.*'],
                ],
            ],
            [
                'key' => 'sourcing',
                'label' => 'Sourcing',
                'permission' => 'staff.sourcing',
                'items' => [
                    ['label' => 'Requirements', 'route' => 'staff.sourcing.index', 'active' => 'staff.sourcing.index'],
                    ['label' => 'Matching', 'route' => 'staff.sourcing.matching', 'active' => 'staff.sourcing.matching'],
                    ['label' => 'Approvals', 'route' => 'staff.sourcing.approvals', 'active' => 'staff.sourcing.approvals'],
                ],
            ],
            [
                'key' => 'brokerage',
                'label' => 'Brokers & Sellers',
                'permission' => 'staff.brokerage',
                'items' => [
                    ['label' => 'Applications', 'route' => 'staff.applications.index', 'active' => 'staff.applications.*'],
                    ['label' => 'Referrals', 'route' => 'staff.referrals.index', 'active' => 'staff.referrals.*'],
                    ['label' => 'Seller Authority', 'route' => 'staff.authorities.index', 'active' => 'staff.authorities.*'],
                ],
            ],
            [
                'key' => 'sales',
                'label' => 'Sales',
                'permission' => 'staff.sales',
                'items' => [
                    ['label' => 'Pipeline', 'route' => 'staff.sales.index', 'active' => 'staff.sales.index'],
                    ['label' => 'Create Sale', 'route' => 'staff.sales.create', 'active' => 'staff.sales.create'],
                    ['label' => 'Offers', 'route' => 'staff.offers.index', 'active' => 'staff.offers.*'],
                    ['label' => 'Completion', 'route' => 'staff.sales.completion', 'active' => 'staff.sales.completion'],
                ],
            ],
            [
                'key' => 'verification',
                'label' => 'Verification',
                'permission' => 'staff.verification',
                'items' => [
                    ['label' => 'Review Queue', 'route' => 'staff.verification.index', 'active' => 'staff.verification.index'],
                    ['label' => 'New Reviews', 'route' => 'staff.verification.new', 'active' => 'staff.verification.new'],
                    ['label' => 'Evidence', 'route' => 'staff.verification.missing', 'active' => 'staff.verification.missing'],
                    ['label' => 'Exceptions', 'route' => 'staff.verification.exceptions', 'active' => 'staff.verification.exceptions'],
                    ['label' => 'Expiring Checks', 'route' => 'staff.verification.expiring', 'active' => 'staff.verification.expiring'],
                    ['label' => 'Completed Reviews', 'route' => 'staff.verification.completed', 'active' => 'staff.verification.completed'],
                ],
            ],
            [
                'key' => 'intelligence',
                'label' => 'Intelligence',
                'permission' => 'staff.intelligence',
                'items' => [
                    ['label' => 'Projects', 'route' => 'staff.projects.index', 'active' => 'staff.projects.*'],
                    ['label' => 'Imagery', 'route' => 'staff.imagery.index', 'active' => 'staff.imagery.*'],
                    ['label' => 'Planning', 'route' => 'staff.planning.index', 'active' => 'staff.planning.*'],
                    ['label' => 'Prices', 'route' => 'staff.prices.index', 'active' => 'staff.prices.*'],
                    ['label' => 'Research', 'route' => 'staff.reports.index', 'active' => 'staff.reports.*'],
                    ['label' => 'Compliance', 'route' => 'staff.commitments.index', 'active' => 'staff.commitments.*'],
                ],
            ],
            [
                'key' => 'finance',
                'label' => 'Finance',
                'permission' => 'staff.finance',
                'items' => [
                    ['label' => 'Subscriptions', 'route' => 'staff.memberships.index', 'active' => 'staff.memberships.*'],
                    ['label' => 'Commissions', 'route' => 'staff.commissions.index', 'active' => 'staff.commissions.*'],
                    ['label' => 'Settlements', 'route' => 'staff.settlements.index', 'active' => 'staff.settlements.*'],
                ],
            ],
            [
                'key' => 'notifications',
                'label' => 'Notifications',
                'permission' => 'staff.overview',
                'items' => [
                    ['label' => 'Messages and work updates', 'route' => 'staff.notifications.index', 'active' => 'staff.notifications.*'],
                    ['label' => 'Templates', 'route' => 'staff.messages.templates', 'active' => 'staff.messages.templates', 'permission' => 'staff.communications'],
                    ['label' => 'Delivery History', 'route' => 'staff.messages.deliveries', 'active' => 'staff.messages.deliveries', 'permission' => 'staff.communications'],
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
            ['title' => 'Communications', 'description' => 'Messages waiting to be sent.', 'permission' => 'staff.communications', 'route' => 'staff.messages.deliveries'],
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
                    ['label' => 'Sales', 'route' => 'admin.oversight.sales', 'active' => 'admin.oversight.sales'],
                    ['label' => 'Inventory', 'route' => 'admin.oversight.inventory', 'active' => 'admin.oversight.inventory'],
                    ['label' => 'Buyers', 'route' => 'admin.oversight.buyers', 'active' => 'admin.oversight.buyers'],
                    ['label' => 'Brokers', 'route' => 'admin.oversight.brokers', 'active' => 'admin.oversight.brokers'],
                    ['label' => 'Subscriptions', 'route' => 'admin.oversight.subscriptions', 'active' => 'admin.oversight.subscriptions'],
                ],
            ],
            [
                'key' => 'approvals',
                'label' => 'Approvals',
                'permission' => 'admin.approvals',
                'items' => [
                    ['label' => 'Approvals Centre', 'route' => 'admin.approvals.index', 'active' => 'admin.approvals.*'],
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
                    ['label' => 'Counties & Localities', 'route' => 'admin.geography.index', 'active' => ['admin.geography.index', 'admin.geography.counties.*', 'admin.geography.localities.*', 'admin.geography.aliases.*']],
                    ['label' => 'Map Layers', 'route' => 'admin.geography.layers.index', 'active' => 'admin.geography.layers.*'],
                    ['label' => 'Infrastructure Reference Data', 'route' => 'admin.geography.infrastructure.index', 'active' => 'admin.geography.infrastructure.*'],
                ],
            ],
            [
                'key' => 'commercial',
                'label' => 'Commercial Settings',
                'permission' => 'admin.commercial',
                'items' => [
                    ['label' => 'Investor Pass Plans', 'route' => 'admin.plans.edit', 'active' => 'admin.plans.*', 'modal' => false],
                    ['label' => 'Commission Policy', 'route' => 'admin.commercial.commissions', 'active' => 'admin.commercial.commissions', 'modal' => false],
                    ['label' => 'Listing Rules', 'route' => 'admin.commercial.listings', 'active' => 'admin.commercial.listings', 'modal' => false],
                    ['label' => 'Verification Standards', 'route' => 'admin.commercial.verification', 'active' => 'admin.commercial.verification', 'modal' => false],
                ],
            ],
            [
                'key' => 'settings',
                'label' => 'System Operations',
                'permission' => 'admin.settings',
                'items' => [
                    ['label' => 'Application Health', 'route' => 'admin.operations.index', 'active' => 'admin.operations.*', 'fragment' => 'health'],
                    ['label' => 'Background Jobs', 'route' => 'admin.operations.index', 'active' => 'admin.operations.jobs', 'fragment' => 'jobs'],
                    ['label' => 'Scheduled Tasks', 'route' => 'admin.operations.index', 'active' => 'admin.operations.schedules', 'fragment' => 'schedules'],
                    ['label' => 'Integrations', 'route' => 'admin.operations.index', 'active' => 'admin.operations.integrations', 'fragment' => 'integrations'],
                    ['label' => 'Audit', 'route' => 'admin.operations.index', 'active' => 'admin.operations.audit', 'fragment' => 'audit'],
                ],
            ],
            [
                'key' => 'reports',
                'label' => 'Reports',
                'permission' => 'admin.reports',
                'items' => [
                    ['label' => 'Sales Reports', 'route' => 'admin.reports.sales', 'active' => 'admin.reports.sales', 'modal' => false],
                    ['label' => 'Commission Reports', 'route' => 'admin.reports.commissions', 'active' => 'admin.reports.commissions', 'modal' => false],
                    ['label' => 'Financial Reports', 'route' => 'admin.reports.financial', 'active' => 'admin.reports.financial', 'modal' => false],
                    ['label' => 'Audit Reports', 'route' => 'admin.reports.audit', 'active' => 'admin.reports.audit', 'modal' => false],
                ],
            ],
            [
                'key' => 'notifications',
                'label' => 'Notifications',
                'items' => [
                    ['label' => 'Notifications', 'route' => 'admin.notifications.index', 'active' => 'admin.notifications.*'],
                ],
            ],
        ],
        'account' => [
            ['label' => 'Logout', 'route' => 'logout', 'method' => 'post'],
        ],
        'panels' => [
            ['title' => 'Sales performance', 'description' => 'Completed and open sales.', 'permission' => 'admin.oversight', 'route' => 'admin.oversight.sales'],
            ['title' => 'Approvals', 'description' => 'Decisions waiting for a controller.', 'permission' => 'admin.approvals', 'route' => 'admin.approvals.index'],
            ['title' => 'User access', 'description' => 'Review who can open each workspace.', 'permission' => 'admin.users', 'route' => 'admin.users.index'],
            ['title' => 'Geography', 'description' => 'Counties, localities, and map layers.', 'permission' => 'admin.geography', 'route' => 'admin.geography.index'],
            ['title' => 'Commercial settings', 'description' => 'Investor Pass, recorded commissions, listing rules, and verification standards.', 'permission' => 'admin.commercial', 'route' => 'admin.plans.edit', 'modal' => false],
            ['title' => 'System operations', 'description' => 'Integrations, queues, and activity.', 'permission' => 'admin.settings', 'route' => 'admin.operations.index'],
            ['title' => 'Reports', 'description' => 'Sales, commission, financial, and audit reports.', 'permission' => 'admin.reports', 'route' => 'admin.reports.sales', 'modal' => false],
        ],
    ],

];
