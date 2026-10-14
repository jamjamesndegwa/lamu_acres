<?php

/*
| Financial authority is separate from super administrator access.
| commissions.propose, commissions.approve, and commissions.record are not
| granted to the super administrator.
*/

$staff = [
    'staff.overview',
    'staff.properties',
    'staff.buyers',
    'staff.sourcing',
    'staff.brokerage',
    'staff.sales',
    'staff.verification',
    'staff.intelligence',
    'staff.finance',
    'staff.communications',
];

$admin = [
    'admin.oversight',
    'admin.approvals',
    'admin.users',
    'admin.geography',
    'admin.commercial',
    'admin.settings',
    'admin.reports',
];

return [

    'permissions' => [
        'workspace.buyer' => ['label' => 'Buyer workspace', 'group' => 'workspace'],
        'workspace.broker' => ['label' => 'Broker workspace', 'group' => 'workspace'],
        'workspace.seller' => ['label' => 'Seller workspace', 'group' => 'workspace'],
        'workspace.staff' => ['label' => 'Staff workspace', 'group' => 'workspace'],
        'workspace.admin' => ['label' => 'Administration workspace', 'group' => 'workspace'],

        'staff.overview' => ['label' => 'Operations overview', 'group' => 'staff'],
        'staff.properties' => ['label' => 'Property desk', 'group' => 'staff'],
        'staff.buyers' => ['label' => 'Buyer desk', 'group' => 'staff'],
        'staff.sourcing' => ['label' => 'Sourcing desk', 'group' => 'staff'],
        'staff.brokerage' => ['label' => 'Broker and seller desk', 'group' => 'staff'],
        'staff.sales' => ['label' => 'Sales desk', 'group' => 'staff'],
        'staff.verification' => ['label' => 'Verification desk', 'group' => 'staff'],
        'staff.intelligence' => ['label' => 'Intelligence desk', 'group' => 'staff'],
        'staff.finance' => ['label' => 'Finance desk', 'group' => 'staff'],
        'staff.communications' => ['label' => 'Communications desk', 'group' => 'staff'],

        'admin.oversight' => ['label' => 'Business oversight', 'group' => 'admin'],
        'admin.approvals' => ['label' => 'Approvals and control', 'group' => 'admin'],
        'admin.users' => ['label' => 'Users and access', 'group' => 'admin'],
        'admin.geography' => ['label' => 'Geography and GIS', 'group' => 'admin'],
        'admin.commercial' => ['label' => 'Commercial settings', 'group' => 'admin'],
        'admin.settings' => ['label' => 'System operations', 'group' => 'admin'],
        'admin.reports' => ['label' => 'Reports', 'group' => 'admin'],

        'commissions.propose' => ['label' => 'Propose commissions', 'group' => 'finance'],
        'commissions.approve' => ['label' => 'Approve commissions', 'group' => 'finance'],
        'commissions.record' => ['label' => 'Record commission payments', 'group' => 'finance'],

        'intelligence.publish' => ['label' => 'Publish intelligence', 'group' => 'intelligence'],
    ],

    'roles' => [
        'buyer' => [
            'label' => 'Buyer',
            'workspace' => 'buyer',
            'description' => 'Searches for land and follows enquiries, visits, and offers.',
            'permissions' => ['workspace.buyer'],
        ],
        'broker' => [
            'label' => 'Broker',
            'workspace' => 'broker',
            'description' => 'Introduces land and buyers under a commission agreement.',
            'permissions' => ['workspace.broker'],
        ],
        'seller' => [
            'label' => 'Landowner',
            'workspace' => 'seller',
            'description' => 'Holds property ownership and sale instructions.',
            'permissions' => ['workspace.seller'],
        ],
        'staff' => [
            'label' => 'Staff',
            'workspace' => 'staff',
            'description' => 'Opens operations. Desk access is granted as separate permissions.',
            'permissions' => ['workspace.staff', 'staff.overview'],
        ],
        'admin' => [
            'label' => 'Administrator',
            'workspace' => 'admin',
            'description' => 'Opens administration and the approval desks. Assigning roles and recording commission payments stay separate.',
            'permissions' => [
                'workspace.admin',
                'workspace.staff',
                'admin.oversight',
                'admin.approvals',
                'admin.geography',
                'admin.commercial',
                'admin.settings',
                'admin.reports',
                'staff.properties',
                'staff.brokerage',
                'staff.verification',
                'staff.sourcing',
                'staff.buyers',
                'intelligence.publish',
            ],
        ],
        'super_admin' => [
            'label' => 'Super Administrator',
            'workspace' => 'admin',
            'description' => 'Opens operations and administration. Commission approval stays a separate permission.',
            'permissions' => array_merge(
                ['workspace.staff', 'workspace.admin'],
                $staff,
                $admin,
                ['intelligence.publish'],
            ),
        ],
    ],

];
