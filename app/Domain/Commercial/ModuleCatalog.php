<?php

namespace App\Domain\Commercial;

final class ModuleCatalog
{
    public const MODULES = [
        'pos' => ['POS and billing', 'basic', ['owner', 'manager', 'cashier', 'waiter']],
        'tables' => ['Tables and guest QR', 'basic', ['owner', 'manager', 'cashier', 'waiter']],
        'kitchen' => ['Kitchen display', 'basic', ['owner', 'manager', 'chef']],
        'receipts' => ['Digital receipts', 'advanced', ['owner', 'manager', 'cashier']],
        'reports' => ['Sales and financial reporting', 'basic', ['owner', 'manager']],
        'crm' => ['Customer management', 'advanced', ['owner', 'manager', 'cashier']],
        'reservations' => ['Reservations', 'advanced', ['owner', 'manager', 'cashier', 'waiter']],
        'inventory' => ['Recipes and inventory', 'advanced', ['owner', 'manager', 'chef', 'procurement']],
        'procurement' => ['Procurement and forecasting', 'advanced', ['owner', 'manager', 'procurement']],
        'payroll' => ['HR and payroll', 'advanced', ['owner', 'manager', 'hr']],
        'operations' => ['Advanced operations', 'advanced', ['owner', 'manager']],
    ];

    public const ROLES = ['owner', 'manager', 'cashier', 'chef', 'waiter', 'procurement', 'hr'];
}
