<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum Permission: string
{
    use Enumerable;
    case PRODUCTS_CREATE = 'products.create';
    case PRODUCTS_UPDATE = 'products.update';
    case PRODUCTS_DELETE = 'products.delete';


    // Orders
    case ORDERS_VIEW_ANY = 'orders.viewAny';
    case ORDERS_VIEW = 'orders.view';
    case ORDERS_UPDATE = 'orders.update';

    case STOCK_TRANSFER_VIEW_ANY = 'stock_transfers.viewAny';
    case STOCK_TRANSFER_VIEW_OWN = 'stock_transfers.viewOwn';
    case STOCK_TRANSFER_VIEW = 'stock_transfers.view';

    case STOCK_TRANSFER_CREATE = 'stock_transfers.create';
    case STOCK_TRANSFER_UPDATE = 'stock_transfers.update';
    case STOCK_TRANSFER_DELETE = 'stock_transfers.delete';
    case STOCK_TRANSFER_RECEIVE = 'stock_transfers.receive';


    // Inventory
    case INVENTORIES_VIEW_ANY = 'inventories.viewAny';
    case INVENTORIES_VIEW = 'inventories.view';
    case INVENTORIES_UPDATE_STOCK = 'inventories.update-stock';

    // POS
    case POS_CHECKOUT = 'pos.checkout';

    // Customers
    case CUSTOMERS_VIEW_ANY = 'customers.viewAny';
    case CUSTOMERS_VIEW = 'customers.view';
    case CUSTOMERS_CREATE = 'customers.create';
    case CUSTOMERS_UPDATE = 'customers.update';
    case CUSTOMERS_DELETE = 'customers.delete';

    case SUPPLIERS_VIEW_ANY = 'suppliers.viewAny';
    case SUPPLIERS_VIEW = 'suppliers.view';
    case SUPPLIERS_CREATE = 'suppliers.create';
    case SUPPLIERS_UPDATE = 'suppliers.update';
    case SUPPLIERS_DELETE = 'suppliers.delete';

    case STAFF_VIEW_ANY = 'staff.viewAny';
    case STAFF_VIEW = 'staff.view';
    case STAFF_CREATE = 'staff.create';
    case STAFF_UPDATE = 'staff.update';
    case STAFF_DELETE = 'staff.delete';

    case ROLES_VIEW_ANY = 'roles.viewAny';
    case ROLES_VIEW = 'roles.view';
    case ROLES_CREATE = 'roles.create';
    case ROLES_UPDATE = 'roles.update';
    case ROLES_DELETE = 'roles.delete';
    case ROLES_ASSIGN_PERMISSIONS = 'roles.assign-permissions';


    // Users
    case USERS_VIEW_ANY = 'users.viewAny';
    case USERS_VIEW = 'users.view';
    case USERS_CREATE = 'users.create';
    case USERS_UPDATE = 'users.update';
    case USERS_DELETE = 'users.delete';
    case USERS_UPDATE_PASSWORD = 'users.update-password';


    public function label(): string
    {
        return match($this) {

            // Products
            self::PRODUCTS_CREATE => 'Add Products',
            self::PRODUCTS_UPDATE => 'Edit Products',
            self::PRODUCTS_DELETE => 'Delete Products',

            // Orders
            self::ORDERS_VIEW_ANY => 'View All Orders',
            self::ORDERS_VIEW => 'View Order Details',
            self::ORDERS_UPDATE => 'Update Orders',

            // Stock Transfers
            self::STOCK_TRANSFER_VIEW_ANY => 'View All Stock Transfers',
            self::STOCK_TRANSFER_VIEW_OWN => 'View Own Stock Transfers',
            self::STOCK_TRANSFER_VIEW => 'View Stock Transfer Details',

            self::STOCK_TRANSFER_CREATE => 'Create Stock Transfer',
            self::STOCK_TRANSFER_UPDATE => 'Update Stock Transfer',
            self::STOCK_TRANSFER_DELETE => 'Delete Stock Transfer',
            self::STOCK_TRANSFER_RECEIVE => 'Receive Stock Transfer',

            // Inventories
            self::INVENTORIES_VIEW_ANY => 'View All Inventories',
            self::INVENTORIES_VIEW => 'View Inventory Details',
            self::INVENTORIES_UPDATE_STOCK => 'Update Inventory Stock',

            // POS
            self::POS_CHECKOUT => 'Process POS Checkout',

            // Customers
            self::CUSTOMERS_VIEW_ANY => 'View All Customers',
            self::CUSTOMERS_VIEW => 'View Customer Details',
            self::CUSTOMERS_CREATE => 'Add Customers',
            self::CUSTOMERS_UPDATE => 'Edit Customers',
            self::CUSTOMERS_DELETE => 'Delete Customers',

            // Suppliers
            self::SUPPLIERS_VIEW_ANY => 'View All Suppliers',
            self::SUPPLIERS_VIEW => 'View Supplier Details',
            self::SUPPLIERS_CREATE => 'Add Suppliers',
            self::SUPPLIERS_UPDATE => 'Edit Suppliers',
            self::SUPPLIERS_DELETE => 'Delete Suppliers',

            // Staff
            self::STAFF_VIEW_ANY => 'View All Staff',
            self::STAFF_VIEW => 'View Staff Details',
            self::STAFF_CREATE => 'Add Staff',
            self::STAFF_UPDATE => 'Edit Staff',
            self::STAFF_DELETE => 'Delete Staff',

            // Roles
            self::ROLES_VIEW_ANY => 'View All Roles',
            self::ROLES_VIEW => 'View Role Details',
            self::ROLES_CREATE => 'Add Roles',
            self::ROLES_UPDATE => 'Edit Roles',
            self::ROLES_DELETE => 'Delete Roles',
            self::ROLES_ASSIGN_PERMISSIONS => 'Assign Permissions to Roles',

            // Users
            self::USERS_VIEW_ANY => 'View All Users',
            self::USERS_VIEW => 'View User Details',
            self::USERS_CREATE => 'Add Users',
            self::USERS_UPDATE => 'Edit Users',
            self::USERS_DELETE => 'Delete Users',
            self::USERS_UPDATE_PASSWORD => 'Update User Password',
        };
    }

}
