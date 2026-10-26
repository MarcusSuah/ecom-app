import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ReportPeriod = { from: string; to: string };

function isoDate(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/** Quick ranges, worked out in the viewer's own calendar. */
function presets(): { label: string; period: ReportPeriod }[] {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth();
    const ninetyDaysAgo = new Date(today);
    ninetyDaysAgo.setDate(today.getDate() - 89);

    return [
        {
            label: 'This month',
            period: {
                from: isoDate(new Date(year, month, 1)),
                to: isoDate(today),
            },
        },
        {
            label: 'Last month',
            period: {
                from: isoDate(new Date(year, month - 1, 1)),
                to: isoDate(new Date(year, month, 0)),
            },
        },
        {
            label: 'Last 90 days',
            period: { from: isoDate(ninetyDaysAgo), to: isoDate(today) },
        },
        {
            label: 'This year',
            period: { from: isoDate(new Date(year, 0, 1)), to: isoDate(today) },
        },
    ];
}

/**
 * From/to dates for a report, with quick ranges. Reloads the page with
 * ?from=&to= so the totals and the download links follow the same period.
 */
export function PeriodFilter({
    period,
    url,
}: {
    period: ReportPeriod;
    url: string;
}) {
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);

    const visit = (next: ReportPeriod) => {
        setFrom(next.from);
        setTo(next.to);
        router.get(url, next, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        visit({ from, to });
    };

    return (
        <form
            onSubmit={submit}
            className="flex flex-wrap items-end gap-3 rounded-md border p-3"
        >
            <div className="grid gap-1">
                <Label htmlFor="report-from">From</Label>
                <Input
                    id="report-from"
                    type="date"
                    value={from}
                    max={to}
                    onChange={(event) => setFrom(event.target.value)}
                    required
                />
            </div>
            <div className="grid gap-1">
                <Label htmlFor="report-to">To</Label>
                <Input
                    id="report-to"
                    type="date"
                    value={to}
                    min={from}
                    onChange={(event) => setTo(event.target.value)}
                    required
                />
            </div>
            <Button type="submit">Show</Button>
            <div
                role="group"
                aria-label="Quick ranges"
                className="flex flex-wrap gap-1"
            >
                {presets().map((preset) => (
                    <Button
                        key={preset.label}
                        type="button"
                        variant="ghost"
                        size="sm"
                        aria-pressed={
                            preset.period.from === period.from &&
                            preset.period.to === period.to
                        }
                        className="aria-pressed:bg-accent"
                        onClick={() => visit(preset.period)}
                    >
                        {preset.label}
                    </Button>
                ))}
            </div>
        </form>
    );
}
