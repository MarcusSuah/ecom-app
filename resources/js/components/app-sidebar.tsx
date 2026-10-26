import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    BadgeCheck,
    Banknote,
    Bell,
    ChartColumn,
    History,
    Megaphone,
    ClipboardList,
    Building2,
    Coins,
    CreditCard,
    FileText,
    FolderTree,
    Globe,
    Inbox,
    Layers,
    LayoutGrid,
    Mail,
    MapPin,
    MessageSquare,
    Package,
    Receipt,
    Scale,
    Search,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Store,
    UserCog,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { show as adminBilling } from '@/routes/admin/billing';
import { index as adminCategories } from '@/routes/admin/categories';
import { index as adminCompanies } from '@/routes/admin/companies';
import { index as adminContactMessages } from '@/routes/admin/contact-messages';
import { index as adminCurrency } from '@/routes/admin/currency';
import { index as adminActivity } from '@/routes/admin/activity';
import { index as adminAuditLogs } from '@/routes/admin/audit-logs';
import { index as adminNotifications } from '@/routes/admin/notifications';
import { edit as adminSettings } from '@/routes/admin/settings';
import { index as adminDisputes } from '@/routes/admin/disputes';
import { index as adminPayouts } from '@/routes/admin/payouts';
import { index as adminReports } from '@/routes/admin/reports';
import { index as adminPermissions } from '@/routes/admin/permissions';
import { index as adminPlans } from '@/routes/admin/plans';
import { index as adminRoles } from '@/routes/admin/roles';
import { index as adminUsers } from '@/routes/admin/users';
import { index as adminVerifications } from '@/routes/admin/verifications';
import { create as companyCreate, edit as companyEdit } from '@/routes/company';
import { index as addressesIndex } from '@/routes/addresses';
import { show as cartShow } from '@/routes/cart';
import { index as conversationsIndex } from '@/routes/conversations';
import { index as notificationsIndex } from '@/routes/notifications';
import { index as myOrders } from '@/routes/orders';
import { index as productsIndex } from '@/routes/products';
import { index as myRfqs } from '@/routes/rfqs';
import { index as sellerOrders } from '@/routes/seller/orders';
import { index as sellerPayouts } from '@/routes/seller/payouts';
import { index as sellerReports } from '@/routes/seller/reports';
import { index as shopItems } from '@/routes/shop/products';
import { edit as shopSettings } from '@/routes/shop/settings';
import { index as shopsIndex } from '@/routes/shops';
import { index as supplierProducts } from '@/routes/supplier/products';
import { index as supplierRfqs } from '@/routes/supplier/rfqs';
import { index as platformAnalytics } from '@/routes/platform/analytics';
import { index as platformPlans } from '@/routes/platform/plans';
import { index as platformTenants } from '@/routes/platform/tenants';
import type { NavItem, SharedData } from '@/types';

const adminNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: adminDashboard(),
        icon: ShieldCheck,
        permission: 'admin.dashboard',
    },
    {
        title: 'Verifications',
        href: adminVerifications(),
        icon: BadgeCheck,
        permission: 'verifications.review',
    },
    {
        title: 'Disputes',
        href: adminDisputes(),
        icon: Scale,
        permission: 'disputes.resolve',
    },
    {
        title: 'Payouts',
        href: adminPayouts(),
        icon: Banknote,
        permission: 'payouts.manage',
    },
    {
        title: 'Reports',
        href: adminReports(),
        icon: ChartColumn,
        permission: 'reports.view',
    },
    {
        title: 'Contact messages',
        href: adminContactMessages(),
        icon: Mail,
        permission: 'contact.manage',
    },
    {
        title: 'Exchange rates',
        href: adminCurrency(),
        icon: Coins,
        permission: 'currency.manage',
    },
    {
        title: 'Companies',
        href: adminCompanies(),
        icon: Building2,
        permission: 'companies.view-any',
    },
    {
        title: 'Categories',
        href: adminCategories(),
        icon: FolderTree,
        permission: 'categories.manage',
    },
    {
        title: 'Seller plans',
        href: adminPlans(),
        icon: Layers,
        permission: 'seller-plans.manage',
    },
    {
        title: 'Platform plan',
        href: adminBilling(),
        icon: CreditCard,
        permission: 'billing.manage',
    },
    {
        title: 'Users',
        href: adminUsers(),
        icon: UserCog,
        permission: 'users.view',
    },
    {
        title: 'Roles',
        href: adminRoles(),
        icon: ShieldCheck,
        permission: 'roles.view',
    },
    {
        title: 'Permissions',
        href: adminPermissions(),
        icon: ShieldCheck,
        permission: 'permissions.view',
    },
    {
        title: 'Notifications',
        href: adminNotifications(),
        icon: Megaphone,
        permission: 'notifications.send',
    },
    {
        title: 'Audit log',
        href: adminAuditLogs(),
        icon: History,
        permission: 'audit-logs.view',
    },
    {
        title: 'Activity log',
        href: adminActivity(),
        icon: Activity,
        permission: 'activity.view',
    },
    {
        title: 'Settings',
        href: adminSettings(),
        icon: Settings,
        permission: 'settings.manage',
    },
];

const platformNavItems: NavItem[] = [
    { title: 'Marketplaces', href: platformTenants(), icon: Globe },
    { title: 'Analytics', href: platformAnalytics(), icon: ChartColumn },
    { title: 'Marketplace plans', href: platformPlans(), icon: Layers },
];

export function AppSidebar() {
    const {
        auth,
        tenant,
        unreadNotificationsCount,
        unreadMessagesCount,
        cartCount,
    } = usePage<SharedData>().props;
    const { can, isPlatformAdmin } = usePermissions();

    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        auth.company
            ? { title: 'My company', href: companyEdit(), icon: Store }
            : { title: 'Start selling', href: companyCreate(), icon: Store },
        ...(auth.company?.type === 'supplier'
            ? [
                  {
                      title: 'My products',
                      href: supplierProducts(),
                      icon: Package,
                  },
                  {
                      title: 'Quote requests',
                      href: supplierRfqs(),
                      icon: Inbox,
                  },
              ]
            : []),
        ...(auth.company?.type === 'shop'
            ? [
                  {
                      title: 'Shop settings',
                      href: shopSettings(),
                      icon: Settings,
                  },
                  { title: 'Shop items', href: shopItems(), icon: Package },
              ]
            : []),
        ...(auth.company
            ? [
                  {
                      title: 'Orders received',
                      href: sellerOrders(),
                      icon: ClipboardList,
                  },
                  { title: 'Payouts', href: sellerPayouts(), icon: Wallet },
                  {
                      title: 'Reports',
                      href: sellerReports(),
                      icon: ChartColumn,
                  },
              ]
            : []),
        { title: 'Browse products', href: productsIndex(), icon: Search },
        { title: 'Local shops', href: shopsIndex(), icon: Store },
        {
            title: cartCount > 0 ? `Cart (${cartCount})` : 'Cart',
            href: cartShow(),
            icon: ShoppingCart,
        },
        { title: 'My orders', href: myOrders(), icon: Receipt },
        { title: 'My requests', href: myRfqs(), icon: FileText },
        { title: 'Addresses', href: addressesIndex(), icon: MapPin },
        {
            title:
                unreadMessagesCount > 0
                    ? `Messages (${unreadMessagesCount})`
                    : 'Messages',
            href: conversationsIndex(),
            icon: MessageSquare,
        },
        {
            title:
                unreadNotificationsCount > 0
                    ? `Notifications (${unreadNotificationsCount})`
                    : 'Notifications',
            href: notificationsIndex(),
            icon: Bell,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                <NavMain
                    label="Marketplace admin"
                    items={adminNavItems.filter(
                        (item) =>
                            can(item.permission) &&
                            // The platform's own marketplace has no platform plan.
                            !(
                                tenant?.is_system &&
                                item.title === 'Platform plan'
                            ),
                    )}
                />
                {isPlatformAdmin && (
                    <NavMain label="Platform" items={platformNavItems} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
