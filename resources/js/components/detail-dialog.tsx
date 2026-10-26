import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type DetailRow = { label: string; value: ReactNode };

/**
 * Quick view of one table row: a titled list of label / value pairs, with
 * optional actions in the footer. Rows with an empty value are skipped.
 */
export function DetailDialog({
    open,
    onOpenChange,
    title,
    description,
    rows,
    children,
    actions,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: ReactNode;
    description?: ReactNode;
    rows: DetailRow[];
    children?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>
                <dl className="grid grid-cols-[9rem_1fr] gap-x-4 gap-y-2.5 py-2 text-sm">
                    {rows
                        .filter(
                            (row) =>
                                row.value !== null &&
                                row.value !== undefined &&
                                row.value !== '',
                        )
                        .map((row) => (
                            <div key={row.label} className="contents">
                                <dt className="text-muted-foreground">
                                    {row.label}
                                </dt>
                                <dd className="min-w-0 break-words">
                                    {row.value}
                                </dd>
                            </div>
                        ))}
                </dl>
                {children}
                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Close
                    </Button>
                    {actions}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
