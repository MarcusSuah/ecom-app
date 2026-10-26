import { router } from '@inertiajs/react';

import { ArrowDownRight, ArrowUpRight, type LucideIcon } from 'lucide-react';

import type { ReactNode } from 'react';

import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    Cell,
    CartesianGrid,
    XAxis,
    YAxis,
} from 'recharts';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

import {
    type ChartConfig,
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';

import { cn } from '@/lib/utils';

export type TrendPoint = {
    date: string;
    label: string;
    revenue: number;
    orders: number;
};

export type Trend = {
    points: TrendPoint[];
    revenue: number;
    previous_revenue: number;
    orders: number;
    previous_orders: number;

    /** Some orders were in another currency and are converted at today's rate. */
    converted: boolean;
};

export type StatusCount = { status: string; label: string; count: number };

/** Minor units to a short label, e.g. "RWF 1.2M". */

export function formatMoney(
    minor: number,

    currency: string,

    subunits: number,

    compact = false,
): string {
    const major = minor / subunits;

    return `${currency} ${major.toLocaleString(undefined, compact ? { notation: 'compact', maximumFractionDigits: 1 } : { maximumFractionDigits: subunits === 1 ? 0 : 2 })}`;
}

export function RangePicker({
    value,

    url,

    extra = {},
}: {
    value: number;

    url: string;

    extra?: Record<string, string>;
}) {
    const options = [
        { days: 7, label: '7 days' },

        { days: 30, label: '30 days' },

        { days: 90, label: '90 days' },

        { days: 365, label: '12 months' },
    ];

    return (
        <div
            role="group"

            aria-label="Period"

            className="inline-flex rounded-md border p-0.5"
        >
            {options.map((option) => (
                <button
                    key={option.days}

                    type="button"

                    aria-pressed={value === option.days}

                    onClick={() =>
                        router.get(
                            url,

                            { ...extra, range: option.days },

                            {
                                preserveScroll: true,

                                preserveState: true,

                                replace: true,
                            },
                        )
                    }

                    className={cn(
                        'rounded px-3 py-1 text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',

                        value === option.days &&
                            'bg-primary text-primary-foreground hover:text-primary-foreground',
                    )}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}

export type KpiColor =
    | 'blue'
    | 'green'
    | 'amber'
    | 'violet'
    | 'rose'
    | 'teal'
    | 'sky'
    | 'orange'
    | 'slate';

/** Icon chip and top edge for each card colour, light and dark. */

const kpiColors: Record<KpiColor, string> = {
    blue: 'text-blue-600 dark:text-blue-400',
    green: 'text-emerald-600 dark:text-emerald-400',
    amber: 'text-amber-600 dark:text-amber-400',
    violet: 'text-violet-600 dark:text-violet-400',
    rose: 'text-rose-600 dark:text-rose-400',
    teal: 'text-teal-600 dark:text-teal-400',
    sky: 'text-sky-600 dark:text-sky-400',
    orange: 'text-orange-600 dark:text-orange-400',
    slate: 'text-slate-600 dark:text-slate-400',
};

/**

 * Each order status keeps one colour on every dashboard: waiting ones warm,

 * moving ones cool, finished ones green, stopped ones grey or red.

 */

export const orderStatusColors: Record<string, string> = {
    pending_payment: '#d97706',

    paid: '#2563eb',

    accepted: '#0891b2',

    preparing: '#7c3aed',

    in_production: '#7c3aed',

    ready_for_pickup: '#0d9488',

    out_for_delivery: '#4f46e5',

    shipped: '#4f46e5',

    delivered: '#16a34a',

    completed: '#15803d',

    cancelled: '#64748b',

    refunded: '#dc2626',
};

/**

 * One number with an icon and, when known, its change from the previous

 * period. Clickable when it opens something (a tab or a page). A colour

 * tells cards apart at a glance.

 */

export function KpiCard({
    icon: Icon,
    label,
    value,
    change,
    hint,
    tone = 'default',
    color,
    active = false,
    onSelect,
}: {
    icon: LucideIcon;
    label: string;
    value: ReactNode;
    change?: number | null;
    hint?: ReactNode;
    tone?: 'default' | 'attention';
    color?: KpiColor;
    active?: boolean;
    onSelect?: () => void;
}) {
    const iconColor =
        tone === 'attention'
            ? 'text-amber-600 dark:text-amber-400'
            : color
              ? kpiColors[color]
              : 'text-primary';

    const body = (
        <>
            <div className="flex items-start justify-between gap-3">
                <span className="text-sm text-muted-foreground">{label}</span>
                <Icon
                    className={cn('size-5 shrink-0', iconColor)}
                    aria-hidden="true"
                />
            </div>

            <div className="mt-1 text-2xl font-semibold tabular-nums">
                {value}
            </div>

            <div className="mt-1 flex min-h-5 flex-wrap items-center gap-2 text-xs text-muted-foreground">
                {change !== undefined && change !== null && (
                    <span
                        className={cn(
                            'inline-flex items-center gap-0.5 font-medium',
                            change >= 0
                                ? 'text-emerald-700 dark:text-emerald-400'
                                : 'text-red-700 dark:text-red-400',
                        )}
                    >
                        {change >= 0 ? (
                            <ArrowUpRight
                                className="size-3.5"
                                aria-hidden="true"
                            />
                        ) : (
                            <ArrowDownRight
                                className="size-3.5"
                                aria-hidden="true"
                            />
                        )}
                        {Math.abs(change)}%
                        <span className="sr-only">
                            {' '}
                            {change >= 0 ? 'up' : 'down'} on the previous period
                        </span>
                    </span>
                )}
                {hint}
            </div>
        </>
    );

    if (onSelect) {
        return (
            <button
                type="button"
                onClick={onSelect}
                aria-pressed={active}
                className={cn(
                    'rounded-xl border bg-card p-4 text-left shadow-xs transition-colors hover:border-primary/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                    active && 'border-primary ring-1 ring-primary',
                )}
            >
                {body}
            </button>
        );
    }

    return (
        <div className="rounded-xl border bg-card p-4 shadow-xs">{body}</div>
    );
}

const revenueConfig = {
    revenue: { label: 'Revenue', color: 'var(--chart-1)' },
} satisfies ChartConfig;

const ordersConfig = {
    orders: { label: 'Orders', color: 'var(--chart-2)' },
} satisfies ChartConfig;

const statusConfig = {
    count: { label: 'Orders', color: 'var(--chart-3)' },
} satisfies ChartConfig;

/** Revenue over the period: one series, so no legend; hover for each day's value. */

export function RevenueChart({
    trend,

    currency,

    subunits,

    title = 'Revenue',
}: {
    trend: Trend;

    currency: string;

    subunits: number;

    title?: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>

                <CardDescription>
                    {formatMoney(trend.revenue, currency, subunits)} in this
                    period
                    {trend.converted
                        ? ', other currencies converted at today’s rate (approx.)'
                        : ''}
                </CardDescription>
            </CardHeader>

            <CardContent>
                <ChartContainer
                    config={revenueConfig}

                    className="aspect-auto h-64 w-full"
                >
                    <AreaChart
                        data={trend.points}

                        margin={{ left: 4, right: 8, top: 8 }}

                        accessibilityLayer
                    >
                        <CartesianGrid vertical={false} strokeOpacity={0.4} />

                        <XAxis
                            dataKey="label"

                            tickLine={false}

                            axisLine={false}

                            minTickGap={24}

                            tickMargin={8}
                        />

                        <YAxis
                            width={72}

                            tickLine={false}

                            axisLine={false}

                            tickFormatter={(value: number) =>
                                formatMoney(value, currency, subunits, true)
                            }
                        />

                        <ChartTooltip
                            cursor={{ strokeOpacity: 0.4 }}

                            content={
                                <ChartTooltipContent
                                    indicator="line"

                                    formatter={(value) =>
                                        formatMoney(
                                            Number(value),

                                            currency,

                                            subunits,
                                        )
                                    }
                                />
                            }
                        />

                        <Area
                            dataKey="revenue"

                            type="monotone"

                            stroke="var(--color-revenue)"

                            strokeWidth={2}

                            fill="var(--color-revenue)"

                            fillOpacity={0.15}

                            activeDot={{ r: 4 }}
                        />
                    </AreaChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}

/** Orders per day or month, on its own scale (never a second axis on the revenue chart). */

export function OrdersChart({ trend }: { trend: Trend }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Orders</CardTitle>

                <CardDescription>
                    {trend.orders.toLocaleString()} paid orders in this period
                </CardDescription>
            </CardHeader>

            <CardContent>
                <ChartContainer
                    config={ordersConfig}

                    className="aspect-auto h-64 w-full"
                >
                    <BarChart
                        data={trend.points}

                        margin={{ left: 4, right: 8, top: 8 }}

                        accessibilityLayer
                    >
                        <CartesianGrid vertical={false} strokeOpacity={0.4} />

                        <XAxis
                            dataKey="label"

                            tickLine={false}

                            axisLine={false}

                            minTickGap={24}

                            tickMargin={8}
                        />

                        <YAxis
                            width={32}

                            allowDecimals={false}

                            tickLine={false}

                            axisLine={false}
                        />

                        <ChartTooltip
                            cursor={{ fillOpacity: 0.3 }}

                            content={<ChartTooltipContent />}
                        />

                        <Bar
                            dataKey="orders"

                            fill="var(--color-orders)"

                            radius={[4, 4, 0, 0]}

                            maxBarSize={28}
                        />
                    </BarChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}

/** How many orders are at each stage; labels on the axis carry identity, not colour. */

export function StatusChart({
    data,

    title = 'Orders by status',
}: {
    data: StatusCount[];

    title?: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>

                <CardDescription>Placed in this period</CardDescription>
            </CardHeader>

            <CardContent>
                {data.length === 0 ? (
                    <p className="py-10 text-center text-sm text-muted-foreground">
                        No orders in this period.
                    </p>
                ) : (
                    <ChartContainer
                        config={statusConfig}

                        className="aspect-auto w-full"

                        style={{ height: Math.max(160, data.length * 36) }}
                    >
                        <BarChart
                            data={data}

                            layout="vertical"

                            margin={{ left: 8, right: 24 }}

                            accessibilityLayer
                        >
                            <XAxis type="number" hide allowDecimals={false} />

                            <YAxis
                                type="category"

                                dataKey="label"

                                width={130}

                                tickLine={false}

                                axisLine={false}
                            />

                            <ChartTooltip
                                cursor={{ fillOpacity: 0.3 }}

                                content={
                                    <ChartTooltipContent hideLabel={false} />
                                }
                            />

                            <Bar
                                dataKey="count"

                                fill="var(--color-count)"

                                radius={[0, 4, 4, 0]}

                                maxBarSize={20}

                                label={{
                                    position: 'right',

                                    className: 'fill-muted-foreground text-xs',
                                }}
                            >
                                {data.map((row) => (
                                    <Cell
                                        key={row.status}

                                        fill={
                                            orderStatusColors[row.status] ??
                                            'var(--color-count)'
                                        }
                                    />
                                ))}
                            </Bar>
                        </BarChart>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}
