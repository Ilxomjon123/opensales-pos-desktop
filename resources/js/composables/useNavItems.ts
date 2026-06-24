import { usePage } from '@inertiajs/vue3';
import {
    Activity,
    Banknote,
    BarChart3,
    BookCheck,
    BookUser,
    Bot,
    Box,
    Boxes,
    SlidersHorizontal,
    Briefcase,
    CalendarClock,
    ClipboardList,
    Clock,
    Coins,
    FolderTree,
    HandCoins,
    Inbox,
    LineChart,
    Map,
    MapPinned,
    Megaphone,
    PackageOpen,
    PackagePlus,
    Receipt,
    RotateCcw,
    Scale,
    ShoppingBag,
    ShoppingCart,
    Store,
    Tag,
    Truck,
    Undo2,
    UserCircle,
    UserCircle2,
    Users,
    Wallet,
    Warehouse,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { NavGroup, NavItem } from '@/types';

export function useNavItems() {
    const page = usePage();
    const { t } = useI18n();
    const role = computed(() => page.props.auth?.role);

    type BadgeProps = {
        badges?: {
            new_orders?: number;
            route_today_remaining?: number;
            carry_orders?: number;
            courier_cash_balance?: number;
        };
    };

    function badgeCount(key: keyof NonNullable<BadgeProps['badges']>): number {
        const badges = (page.props as BadgeProps).badges;
        const n = badges?.[key] ?? 0;

        return typeof n === 'number' && n > 0 ? n : 0;
    }

    function formatShortMoney(amount: number): string {
        if (amount >= 1_000_000) {
            const m = amount / 1_000_000;

            return (m >= 10 ? Math.round(m) : m.toFixed(1).replace(/\.0$/, '')) + 'M';
        }

        if (amount >= 1_000) {
            const k = amount / 1_000;

            return (k >= 10 ? Math.round(k) : k.toFixed(1).replace(/\.0$/, '')) + 'K';
        }

        return String(amount);
    }

    const newOrders = computed<number>(() => badgeCount('new_orders'));
    const routeTodayRemaining = computed<number>(() => badgeCount('route_today_remaining'));
    const carryOrders = computed<number>(() => badgeCount('carry_orders'));
    const courierCashBalance = computed<number>(() => badgeCount('courier_cash_balance'));
    const courierCashBadge = computed<string | null>(() =>
        courierCashBalance.value > 0 ? formatShortMoney(courierCashBalance.value) : null,
    );

    const navLabel = computed(() => {
        if (role.value === 'super_admin') {
            return t('nav.groups.admin');
        }

        if (role.value === 'deliveryman') {
            return t('nav.groups.deliveryman');
        }

        if (role.value === 'warehouse') {
            return t('nav.groups.warehouse');
        }

        if (role.value === 'dealer') {
            return t('nav.groups.owner');
        }

        if (role.value === 'cashier') {
            return t('nav.groups.cashier');
        }

        return '';
    });

    const adminNavGroups = computed<NavGroup[]>(() => [
        {
            label: navLabel.value,
            items: [
                { title: t('nav.dealers'), href: '/admin/dealers', icon: Users },
                { title: t('nav.shops'), href: '/admin/shops', icon: Store },
                { title: t('nav.directory'), href: '/admin/directory', icon: BookUser },
                { title: t('nav.stats'), href: '/admin/stats', icon: BarChart3 },
                { title: t('nav.botHealth'), href: '/admin/bot-health', icon: Activity },
                { title: t('nav.broadcasts'), href: '/admin/broadcasts', icon: Megaphone },
                { title: t('nav.broadcastCampaigns'), href: '/admin/broadcast-campaigns', icon: CalendarClock },
                { title: t('nav.leads'), href: '/admin/leads', icon: Inbox },
                { title: t('nav.billing'), href: '/admin/billing', icon: Wallet },
                { title: t('nav.auditLog'), href: '/admin/audit-log', icon: ClipboardList },
                { title: t('nav.settings'), href: '/admin/settings', icon: SlidersHorizontal },
            ],
        },
        {
            label: t('nav.groups.reports'),
            items: [
                { title: t('nav.platformSalesReport'), href: '/admin/reports/sales', icon: LineChart },
                { title: t('nav.commissionReport'), href: '/admin/reports/commission', icon: HandCoins },
                { title: t('nav.dealerActivityReport'), href: '/admin/reports/dealer-activity', icon: Activity },
            ],
        },
    ]);

    // Offline POS desktop — faqat Mahsulotlar va POS bo'limlari.
    const ownerNavGroups = computed<NavGroup[]>(() => [
        {
            label: t('nav.groups.pos'),
            items: [
                { title: t('nav.posTerminal'), href: '/dealer/pos', icon: ShoppingBag },
                { title: t('nav.posSales'), href: '/dealer/pos/sales', icon: Receipt },
                { title: t('nav.posShifts'), href: '/dealer/pos/shifts', icon: Clock },
                { title: t('nav.posCustomers'), href: '/dealer/pos/customers', icon: UserCircle2 },
                { title: t('nav.posReports'), href: '/dealer/pos/reports', icon: BarChart3 },
            ],
        },
        {
            label: t('nav.groups.catalog'),
            items: [
                { title: t('nav.products'), href: '/dealer/products', icon: Box },
                { title: t('nav.categories'), href: '/dealer/categories', icon: FolderTree },
            ],
        },
    ]);

    const warehouseNav = computed<NavItem[]>(() => [
        { title: t('nav.orders'), href: '/dealer/orders', icon: ShoppingCart, badge: newOrders.value || null, badgeTone: 'warning', badgeTooltip: t('nav.badgeHints.newOrders') },
        { title: t('nav.products'), href: '/dealer/products', icon: Box },
        { title: t('nav.categories'), href: '/dealer/categories', icon: FolderTree },
        { title: t('nav.carry'), href: '/dealer/carry', icon: PackageOpen, badge: carryOrders.value || null, badgeTone: 'info', badgeTooltip: t('nav.badgeHints.carry') },
        { title: t('nav.suppliers'), href: '/dealer/suppliers', icon: Truck },
    ]);

    const deliverymanNav = computed<NavItem[]>(() => [
        { title: t('nav.routesToday'), href: '/dealer/routes/today', icon: Map, badge: routeTodayRemaining.value || null, badgeTone: 'warning', badgeTooltip: t('nav.badgeHints.routeToday') },
        { title: t('nav.orders'), href: '/dealer/orders', icon: ShoppingCart, badge: newOrders.value || null, badgeTone: 'warning', badgeTooltip: t('nav.badgeHints.newOrders') },
        { title: t('nav.carry'), href: '/dealer/carry', icon: PackageOpen, badge: carryOrders.value || null, badgeTone: 'info', badgeTooltip: t('nav.badgeHints.carry') },
        { title: t('nav.courierCash'), href: '/dealer/courier-cash', icon: Banknote, badge: courierCashBadge.value, badgeTone: 'info', badgeTooltip: t('nav.badgeHints.courierCashSelf') },
        { title: t('nav.shops'), href: '/dealer/shops', icon: Store },
    ]);

    const cashierNav = computed<NavItem[]>(() => [
        { title: t('nav.posTerminal'), href: '/dealer/pos', icon: ShoppingBag },
        { title: t('nav.posSales'), href: '/dealer/pos/sales', icon: Receipt },
        { title: t('nav.posShifts'), href: '/dealer/pos/shifts', icon: Clock },
        { title: t('nav.posCustomers'), href: '/dealer/pos/customers', icon: UserCircle2 },
    ]);

    const navGroups = computed<NavGroup[]>(() => {
        if (role.value === 'super_admin') {
            return adminNavGroups.value;
        }

        if (role.value === 'dealer') {
            return ownerNavGroups.value;
        }

        if (role.value === 'warehouse') {
            return [{ label: navLabel.value, items: warehouseNav.value }];
        }

        if (role.value === 'deliveryman') {
            return [{ label: navLabel.value, items: deliverymanNav.value }];
        }

        if (role.value === 'cashier') {
            return [{ label: navLabel.value, items: cashierNav.value }];
        }

        return [];
    });

    const navItems = computed<NavItem[]>(() =>
        navGroups.value.flatMap((group) => group.items),
    );

    const homeUrl = computed(() => {
        if (role.value === 'super_admin') {
            return '/admin/dealers';
        }

        if (role.value === 'deliveryman') {
            return '/dealer/routes/today';
        }

        if (role.value === 'warehouse') {
            return '/dealer/orders';
        }

        if (role.value === 'dealer') {
            return '/dealer/orders';
        }

        if (role.value === 'cashier') {
            return '/dealer/pos';
        }

        return '/dashboard';
    });

    return { navItems, navGroups, homeUrl, navLabel, role };
}
