import {
    BadgeCheck,
    Bell,
    CheckCircle2,
    FileText,
    Inbox,
    Mail,
    Megaphone,
    Package,
    PackageX,
    Scale,
    ShieldX,
    UserCheck,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export type NotificationKind = {
    icon: LucideIcon;
    /** What the notification is about, e.g. "Order". */
    label: string;
    /** Icon chip colours, light and dark. */
    tone: string;
};

/** Each notification type (its databaseType) keeps one icon and colour. */
const kinds: Record<string, NotificationKind> = {
    'order.status': {
        icon: Package,
        label: 'Order',
        tone: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    },
    'quote.received': {
        icon: FileText,
        label: 'Quote',
        tone: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
    },
    'quote.accepted': {
        icon: CheckCircle2,
        label: 'Quote',
        tone: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    },
    'quote.rejected': {
        icon: XCircle,
        label: 'Quote',
        tone: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    },
    'rfq.received': {
        icon: Inbox,
        label: 'Request',
        tone: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    },
    'dispute.opened': {
        icon: Scale,
        label: 'Dispute',
        tone: 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300',
    },
    'stock.low': {
        icon: PackageX,
        label: 'Stock',
        tone: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
    },
    'verification.approved': {
        icon: BadgeCheck,
        label: 'Verification',
        tone: 'bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-300',
    },
    'verification.rejected': {
        icon: ShieldX,
        label: 'Verification',
        tone: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
    },
    announcement: {
        icon: Megaphone,
        label: 'Announcement',
        tone: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    },
    'contact.received': {
        icon: Mail,
        label: 'Message',
        tone: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    },
    'account.pending': {
        icon: UserCheck,
        label: 'Account',
        tone: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    },
};

const fallback: NotificationKind = {
    icon: Bell,
    label: 'Update',
    tone: 'bg-muted text-muted-foreground',
};

export function notificationKind(type: string): NotificationKind {
    return kinds[type] ?? fallback;
}
